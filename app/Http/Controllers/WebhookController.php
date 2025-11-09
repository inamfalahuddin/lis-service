<?php

namespace App\Http\Controllers;

use App\Enums\StatusControlEnum;
use App\Services\HttpClientService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ApiLog;
use Dingo\Api\Http\Middleware\Auth;
use Illuminate\Support\Str;

class WebhookController extends MshController
{
    const LOG_CHANNEL   = 'order';
    const LOG_PREFIX    = 'WEBHOOK_ORDER';

    public function order(Request $request)
    {
        $startTime = microtime(true);
        $requestId = Str::uuid()->toString();

        $data = $request->json()->all();

        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Request received', [
            'request_id' => $requestId,
            'payload' => $data
        ]);

        $apiLog = ApiLog::create([
            'service_name' => 'LIS_WEBHOOK_ORDER',
            'method' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'payload' => $data,
            'status_code' => null,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $requestId,
            'response_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            if (!isset($data['response'])) {
                throw new \Exception('Invalid payload, missing response key');
            }

            if ($data['response']['code'] !== "200") {
                throw new \Exception('Response code is not 200');
            }

            $response = $data['response'];
            if (!isset($response['sampel'])) {
                throw new \Exception('Invalid payload, missing sampel data');
            }

            if (!isset($response['sampel']['order_lab'])) {
                return response()->json([
                    "response" => [
                        "code" => "404",
                        "message" => "Tidak Ada Data",
                        "product" => "SOFTMEDIX LIS",
                        "version" => "ws.003",
                        "id" => ""
                    ]
                ]);
            }

            $sampel = $response['sampel'];
            $kodeTransaksi = $response['sampel']['order_lab'];

            $hasResults = isset($sampel['result_test']) && is_array($sampel['result_test']) && count($sampel['result_test']) > 0;

            DB::beginTransaction();

            // Ambil semua data sekaligus dengan satu query
            $results = DB::connection('mysql2')
                ->table('t_lab_pelaksanaan_pemeriksaan')
                ->select(
                    't_lab_pelaksanaan.id as pelaksanaan_id',
                    't_lab_pelaksanaan_pemeriksaan.id as pelaksanaan_pemeriksaan_id',
                    't_lab_pelaksanaan_pemeriksaan.pelaksanaan_id',
                    't_lab_pelaksanaan_pemeriksaan.pemeriksaan_id',
                    'm_lab_pemeriksaan.id as pemeriksaan_id',
                    'm_lab_pemeriksaan.tarif_pelayanan_id',
                    'm_lab_pemeriksaan.kode as pemeriksaan_kode',
                    'm_lab_pemeriksaan.nama as pemeriksaan_nama',
                    'm_lab_pemeriksaan_item.id as item_id',
                    'm_lab_pemeriksaan_item.kode as item_kode',
                    'm_lab_pemeriksaan_item.nama as item_nama',
                    'm_lab_pemeriksaan_item.status as item_status'
                )
                ->join('m_lab_pemeriksaan', 'm_lab_pemeriksaan.id', '=', 't_lab_pelaksanaan_pemeriksaan.pemeriksaan_id')
                ->join('t_lab_pelaksanaan', 't_lab_pelaksanaan.id', '=', 't_lab_pelaksanaan_pemeriksaan.pelaksanaan_id')
                ->join('t_lab_register', 't_lab_register.id', '=', 't_lab_pelaksanaan.register_id')
                ->join('m_lab_pemeriksaan_fk', 'm_lab_pemeriksaan_fk.pemeriksaan_id', '=', 'm_lab_pemeriksaan.id')
                ->join('m_lab_pemeriksaan_item', 'm_lab_pemeriksaan_item.id', '=', 'm_lab_pemeriksaan_fk.pemeriksaan_item_id')
                ->where('t_lab_register.kode_transaksi', $kodeTransaksi)
                ->get();

            // Buat lookup maps dari hasil query
            $lookup_pemeriksaan_id = [];
            $lookup_items = [];

            foreach ($results as $item) {
                // Map untuk pemeriksaan_id
                $lookup_pemeriksaan_id[$item->pemeriksaan_kode] = $item->pelaksanaan_pemeriksaan_id;

                // Map untuk items
                $key = $item->pemeriksaan_kode . '||' . $item->item_kode;
                $lookup_items[$key] = (object)[
                    'kode' => $item->item_kode,
                    'nama' => $item->item_nama,
                    'status' => $item->item_status
                ];
            }

            $finalResults = [];
            foreach ($sampel['result_test'] as $test) {
                $key = $test['kode_paket'] . '||' . $test['test_id'];

                if (isset($lookup_items[$key]) && isset($lookup_pemeriksaan_id[$test['kode_paket']])) {
                    $item = $lookup_items[$key];
                    $finalResults[] = [
                        'pemeriksaan_id' => $lookup_pemeriksaan_id[$test['kode_paket']],
                        'kode'           => $item->kode,
                        'nama'           => $item->nama,
                        'datatype'       => $test['jenis_hasil'],
                        'value'          => $test['hasil'],
                        'satuan'         => $test['satuan'],
                        'nilai_normal'   => $test['nilai_normal'],
                        'lis_flag_sign'  => $test['flag'],
                        'lis'            => 1,
                    ];
                }
            }

            if (!empty($finalResults)) {
                // Optimasi: Gunakan array_unique untuk mengurangi duplikasi
                $uniquePemeriksaanIds = array_unique(array_column($finalResults, 'pemeriksaan_id'));
                $uniqueKodes = array_unique(array_column($finalResults, 'kode'));

                // Query existing records yang lebih efisien
                $existingRecords = DB::connection('mysql2')
                    ->table('t_lab_pelaksanaan_pemeriksaan_hasil')
                    ->select('pemeriksaan_id', 'kode')
                    ->whereIn('pemeriksaan_id', $uniquePemeriksaanIds)
                    ->whereIn('kode', $uniqueKodes)
                    ->get()
                    ->keyBy(function ($item) {
                        return $item->pemeriksaan_id . '||' . $item->kode;
                    });

                $toUpdate = [];
                $toInsert = [];

                foreach ($finalResults as $result) {
                    $key = $result['pemeriksaan_id'] . '||' . $result['kode'];

                    if (isset($existingRecords[$key])) {
                        $toUpdate[] = $result;
                    } else {
                        $toInsert[] = array_merge($result, [
                            'created_at' => now(),
                            'update_at' => now()
                        ]);
                    }
                }

                // Bulk Insert untuk data baru
                if (!empty($toInsert)) {
                    DB::connection('mysql2')
                        ->table('t_lab_pelaksanaan_pemeriksaan_hasil')
                        ->insert($toInsert);
                }

                // Batch Update untuk data yang sudah ada dengan chunking
                if (!empty($toUpdate)) {
                    $chunks = array_chunk($toUpdate, 50); // Process 50 records at a time

                    foreach ($chunks as $chunk) {
                        $caseStatements = [];
                        $bindings = [];
                        $chunkPemeriksaanIds = [];
                        $chunkKodes = [];

                        // Build CASE statements untuk setiap field
                        foreach (['nama', 'datatype', 'value', 'satuan', 'nilai_normal', 'lis_flag_sign', 'lis'] as $field) {
                            $caseSql = "{$field} = CASE ";
                            foreach ($chunk as $result) {
                                $caseSql .= "WHEN pemeriksaan_id = ? AND kode = ? THEN ? ";
                                $bindings[] = $result['pemeriksaan_id'];
                                $bindings[] = $result['kode'];
                                $bindings[] = $result[$field];
                            }
                            $caseSql .= "ELSE {$field} END";
                            $caseStatements[] = $caseSql;
                        }

                        // echo json_encode($caseStatements);

                        // Collect unique IDs untuk WHERE clause
                        foreach ($chunk as $result) {
                            $chunkPemeriksaanIds[$result['pemeriksaan_id']] = true;
                            $chunkKodes[$result['kode']] = true;
                        }

                        $chunkPemeriksaanIds = array_keys($chunkPemeriksaanIds);
                        $chunkKodes = array_keys($chunkKodes);

                        $bindings = array_merge($bindings, $chunkPemeriksaanIds, $chunkKodes);

                        $updateSql = "UPDATE t_lab_pelaksanaan_pemeriksaan_hasil SET "
                            . implode(', ', $caseStatements)
                            . " WHERE pemeriksaan_id IN (" . implode(',', array_fill(0, count($chunkPemeriksaanIds), '?')) . ")"
                            . " AND kode IN (" . implode(',', array_fill(0, count($chunkKodes), '?')) . ")";

                        DB::connection('mysql2')->update($updateSql, $bindings);
                    }
                }

                if ($hasResults) {
                    DB::connection('mysql2')
                        ->table('t_lab_register')
                        ->where('kode_transaksi', $kodeTransaksi)
                        ->update([
                            'is_lis_hasil' => 1,
                            'update_at' => now()
                        ]);
                }

                // Update t_lab_pelaksanaan set status = 'Selesai'
                $updatePelaksanaan = [
                    'hasil_flag'    => 1,
                    'hasil_at'      => now(),
                    'hasil_by'      => env('USER_SOFTMEDIX_ID', 2),
                    'dokter_flag'   => 1,
                    'dokter_at'     => now(),
                    'dokter_by'     => env('USER_SOFTMEDIX_ID', 2),
                    'status'        => 3,
                    'update_at'     => now(),
                    'updated_by'    => env('USER_SOFTMEDIX_ID', 2),
                ];

                DB::connection('mysql2')->table('t_lab_pelaksanaan')->whereIn('id', $uniquePemeriksaanIds)->update($updatePelaksanaan);
            }

            DB::commit();

            $responseTime = round((microtime(true) - $startTime) * 1000, 3);

            $apiLog->update([
                'status_code' => 200,
                'status' => 'success',
                'response_time' => $responseTime,
                'updated_at' => now(),
                'response' => ['message' => 'Data received and processed']
            ]);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Data processed successfully', [
                'request_id' => $requestId,
                'lis_sampel' => $sampel['lis_sampel'],
                'processed_items' => count($finalResults)
            ]);

            // Response sukses sesuai format yang diminta
            return response()->json([
                "response" => [
                    "code" => "200",
                    "message" => "berhasil",
                    "product" => "SOFTMEDIX LIS",
                    "version" => "ws.003",
                    "id" => $sampel['lis_sampel'] // menggunakan lis_sampel sebagai ID
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $responseTime = round((microtime(true) - $startTime) * 1000, 3);

            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Failed processing webhook', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $apiLog->update([
                'status_code' => 404,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'response_time' => $responseTime,
                'updated_at' => now(),
            ]);

            return response()->json([
                "response" => [
                    "code" => "404",
                    "message" => "Tidak Ada Data",
                    "product" => "SOFTMEDIX LIS",
                    "version" => "ws.003",
                    "id" => ""
                ]
            ]);
        }
    }
}
