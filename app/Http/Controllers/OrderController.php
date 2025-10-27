<?php

namespace App\Http\Controllers;

use App\Enums\PayloadLength;
use App\Enums\StatusControlEnum;
use App\Services\HttpClientService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ApiLog;
use Illuminate\Support\Str;

class OrderController extends MshController
{
    const LOG_CHANNEL   = 'order';
    const LOG_PREFIX    = 'ORDER_CONTROLLER';

    public function order(Request $request)
    {
        $startTime = microtime(true);
        $requestId = Str::uuid()->toString();

        $validated = $request->validate([
            'order_control' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
            'status_pasien' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
            'kode_transaksi' => ['required', 'string', 'max:50'],
        ]);

        $transactionCodes = [$validated['kode_transaksi']];
        $raw = $this->get_data_register($transactionCodes);

        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Request received', [
            'method' => 'order',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'input' => $request->all(),
            'request_id' => $requestId
        ]);

        // Buat log awal dengan status pending
        $apiLog = ApiLog::create([
            'service_name' => 'LIS_ORDER_SERVICE',
            'method' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'payload' => ['message' => 'Kode lab sepertinya tidak ada di database kami'],
            'status_code' => null,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $requestId,
            'response_time' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        try {
            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Validation success', [
                'kode_transaksi' => $validated['kode_transaksi'],
                'order_control' => $validated['order_control'],
                'status_pasien' => $validated['status_pasien']
            ]);

            // Update log dengan info validasi sukses
            $apiLog->update([
                'payload->validation' => $validated,
                'updated_at' => now()
            ]);

            // Cek jika data tidak ditemukan
            if ($raw->isEmpty()) {
                Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - Data not found', [
                    'kode_transaksi' => $validated['kode_transaksi'],
                    'data_count' => $raw->count()
                ]);

                $responseTime = round((microtime(true) - $startTime) * 1000, 3);

                // Update log untuk response 404
                $apiLog->update([
                    'response' => [
                        'code' => '404',
                        'message' => 'Tidak Ada Data',
                        'product' => 'SOFTMEDIX LIS',
                        'version' => 'ws.003',
                        'id' => ''
                    ],
                    'status_code' => 404,
                    'status' => 'error',
                    'error_message' => 'Data not found for kode_transaksi: ' . $validated['kode_transaksi'],
                    'response_time' => $responseTime,
                    'updated_at' => now()
                ]);

                return response()->json([
                    'response' => [
                        'code' => '404',
                        'message' => 'Tidak Ada Data',
                        'product' => 'SOFTMEDIX LIS',
                        'version' => 'ws.003',
                        'id' => ''
                    ]
                ], 404);
            }

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Data retrieved successfully', [
                'kode_transaksi' => $validated['kode_transaksi'],
                'data_count' => $raw->count(),
                'no_rm' => $raw->first()->no_rm ?? 'N/A',
                'pasien_nama' => $raw->first()->pasien_nama ?? 'N/A'
            ]);

            $payload = $this->orderPayload($raw->toArray(), $validated);

            Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - Payload constructed', [
                'kode_transaksi' => $validated['kode_transaksi'],
                'payload_keys' => array_keys($payload),
                'reg_no' => $payload['order']['obr']['reg_no'] ?? 'N/A'
            ]);

            $httpClient = app(HttpClientService::class);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Sending to LIS', [
                'kode_transaksi' => $validated['kode_transaksi'],
                'endpoint' => 'bridging/order',
                'order_control' => $validated['order_control']
            ]);

            $apiLog->update([
                'payload' => $payload,
                'updated_at' => now()
            ]);

            $response = $httpClient->sendToLIS('bridging/order', $payload, 'POST');

            $responseTime = round((microtime(true) - $startTime) * 1000, 3);

            // Handle response dari LIS
            if ($response['success']) {
                if ($response['data']['response']['code'] != '200') {
                    $response['status'] = 400;

                    Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - LIS response indicates failure', [
                        'kode_transaksi' => $validated['kode_transaksi'],
                        'status_code' => $response['status'],
                    ]);

                    $apiLog->update([
                        'response' => $response['data'],
                        'status_code' => $response['status'],
                        'status' => 'error',
                        'error_message' => 'LIS returned error code: ' . $response['data']['response']['code'],
                        'response_time' => $responseTime,
                        'updated_at' => now()
                    ]);

                    return response()->json($response['data'], $response['status'])->header('Content-Type', 'application/json');
                }

                Log::channel(self::LOG_PREFIX . ' - LIS response success', [
                    'kode_transaksi' => $validated['kode_transaksi'],
                    'status_code' => $response['status'],
                    'response_type' => gettype($response['data'])
                ]);

                // Update log untuk success response
                $apiLog->update([
                    'response' => is_array($response['data']) ? $response['data'] : ['raw_response' => $response['body']],
                    'status_code' => $response['status'],
                    'status' => 'success',
                    'response_time' => $responseTime,
                    'updated_at' => now()
                ]);

                // Return response langsung dari LIS
                if (is_array($response['data'])) {
                    return response()->json($response['data'], $response['status']);
                }

                return response($response['body'], $response['status'])
                    ->header('Content-Type', 'application/json');
            }

            // Jika gagal kirim ke LIS
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - LIS request failed', [
                'kode_transaksi' => $validated['kode_transaksi'],
                'status_code' => $response['status'],
                'error' => $response['error'] ?? 'Unknown error',
                'response_body' => $response['body'] ?? null
            ]);

            // Update log untuk error response
            $apiLog->update([
                'response' => [
                    'code' => (string) $response['status'],
                    'message' => $response['error'] ?? 'Gagal terhubung ke server LIS',
                    'product' => 'SOFTMEDIX LIS',
                    'version' => 'ws.003',
                    'id' => ''
                ],
                'status_code' => $response['status'],
                'status' => 'error',
                'error_message' => $response['error'] ?? 'Gagal terhubung ke server LIS',
                'response_time' => $responseTime,
                'updated_at' => now()
            ]);

            return response()->json([
                'response' => [
                    'code' => (string) $response['status'],
                    'message' => $response['error'] ?? 'Gagal terhubung ke server LIS',
                    'product' => 'SOFTMEDIX LIS',
                    'version' => 'ws.003',
                    'id' => ''
                ]
            ], $response['status']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 3);

            Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            // Update log untuk validation error
            $apiLog->update([
                'response' => ['errors' => $e->errors()],
                'status_code' => 422,
                'status' => 'error',
                'error_message' => 'Validation failed: ' . json_encode($e->errors()),
                'response_time' => $responseTime,
                'updated_at' => now()
            ]);

            throw $e;
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 3);

            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Unexpected error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'kode_transaksi' => $request->input('kode_transaksi')
            ]);

            // Update log untuk unexpected error
            $apiLog->update([
                'status_code' => 500,
                'status' => 'error',
                'error_message' => $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                'response_time' => $responseTime,
                'updated_at' => now()
            ]);

            throw $e;
        }
    }

    public function order_me(Request $request)
    {
        try {
            $validated = $request->validate([
                'response.code' => ['required', 'string', 'max:20'],
                'response.sampel.reg_no' => ['required', 'string', 'max:20'],
            ]);


            // Simulasi proses pencarian data
            // $dataExists = $this->checkDataExists($validated); // Method untuk cek data di database

            // if (!$dataExists) {
            //     return response()->json([
            //         'response' => [
            //             'code' => '404',
            //             'message' => 'Tidak Ada Data',
            //             'product' => 'SOFTMEDIX LIS',
            //             'version' => 'ws.003',
            //             'id' => ''
            //         ]
            //     ], 404);
            // }

            return response()->json([
                'message' => 'Body valid',
                'data' => $validated
            ]);

            // Jika data ditemukan, return response 200 dengan format yang diharapkan
            // return response()->json([
            //     'response' => [
            //         'code' => '200',
            //         'message' => 'Sukses',
            //         'product' => 'SOFTMEDIX LIS',
            //         'version' => 'ws.003',
            //         'id' => ''
            //     ],
            //     'payload' => $this->orderPayload($validated)
            // ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'response' => [
                    'code' => '404',
                    'message' => 'Tidak Ada Data',
                    'product' => 'SOFTMEDIX LIS',
                    'version' => 'ws.003',
                    'id' => '',
                    'errors' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'response' => [
                    'code' => '500',
                    'message' => 'Terjadi kesalahan sistem',
                    'product' => 'SOFTMEDIX LIS',
                    'version' => 'ws.003',
                    'id' => ''
                ]
            ], 500);
        }
    }

    private function orderPayload($data, $payload)
    {
        // Pindahkan helper functions ke luar closure untuk efisiensi
        $truncate = function ($value, $length) {
            if ($value === null || $value === '') return '';
            return substr((string)$value, 0, $length);
        };

        $formatDate = function ($date, $format) {
            if (empty($date)) return null;
            try {
                return \Carbon\Carbon::parse($date)->format($format);
            } catch (\Exception $e) {
                return null;
            }
        };

        $default = function ($value, $default = '') {
            return empty($value) ? $default : $value;
        };

        // Pre-process payload data yang sama untuk semua item
        $orderControl = $truncate(StatusControlEnum::fromName($payload['order_control'])->value, PayloadLength::ORDER_CONTROL);
        $patientType = $truncate(StatusControlEnum::fromName($payload['status_pasien'])->value, PayloadLength::PTYPE);
        $mshData = $this->getMshData(); // Panggil sekali saja

        $build = function ($item) use ($truncate, $formatDate, $default, $orderControl, $patientType, $mshData) {
            // Safe property access dengan null coalescing
            $birthDate = $formatDate($item->tanggal_lahir ?? null, 'd.m.Y');
            $orderDate = $formatDate($item->created_at ?? null, 'd.m.Y H:i:s');

            // Optimasi pemeriksaan list
            $pemeriksaanList = [];
            $labPemeriksaan = $item->lab_pemeriksaan ?? '';
            if (!empty($labPemeriksaan)) {
                $tests = explode(',', $labPemeriksaan);
                foreach ($tests as $test) {
                    if (!empty(trim($test))) {
                        $pemeriksaanList[] = $truncate(trim($test), PayloadLength::ORDER_TEST);
                    }
                }
            }

            return [
                "order" => [
                    "msh" => $mshData, // Gunakan yang sudah diproses
                    "pid" => [
                        "pmrn"      => $truncate($item->no_rm ?? '', PayloadLength::PMRN),
                        "pname"     => $truncate($item->pasien_nama ?? '', PayloadLength::PNAME),
                        "sex"       => $truncate($item->jenis_kelamin ?? '', PayloadLength::SEX),
                        "birth_dt"  => $truncate($birthDate, PayloadLength::BIRTH_DT),
                        "address"   => $truncate($item->alamat ?? '', PayloadLength::ADDRESS),
                        "no_tlp"    => $truncate($default($item->no_telepon_1 ?? null, '000000000'), PayloadLength::NO_TLP),
                        "no_hp"     => $truncate($default($item->no_telepon_2 ?? null, '000000000'), PayloadLength::NO_HP),
                        "email"     => $truncate($default($item->email ?? null, 'none@mail.com'), PayloadLength::EMAIL),
                        "nik"       => $truncate($item->no_identitas ?? '', PayloadLength::NIK)
                    ],
                    "obr" => [
                        "order_control"     => $orderControl, // Gunakan yang sudah diproses
                        "ptype"             => $patientType, // Gunakan yang sudah diproses
                        "reg_no"            => $truncate($item->pelayanan_id ?? '', PayloadLength::REG_NO),
                        "order_lab"         => $truncate($item->kode_transaksi ?? '', PayloadLength::ORDER_LAB),
                        "provider_id"       => $truncate(str_pad($default($item->cara_bayar_id ?? null, '0'), 3, '0', STR_PAD_LEFT), PayloadLength::PROVIDER_ID),
                        "provider_name"     => $truncate($item->cara_bayar_nama ?? '', PayloadLength::PROVIDER_NAME),
                        "order_date"        => $truncate($orderDate, PayloadLength::ORDER_DATE),
                        "clinician_id"      => $truncate($default($item->dokter_id ?? null, '000'), PayloadLength::CLINICIAN_ID),
                        "clinician_name"    => $truncate($default($item->dokter_nama ?? null, '000'), PayloadLength::CLINICIAN_NAME),
                        "bangsal_id"        => $truncate($default($item->layanan_id ?? null, '000'), PayloadLength::BANGSAL_ID),
                        "bangsal_name"      => $truncate($item->layanan_nama ?? '', PayloadLength::BANGSAL_NAME),
                        "bed_id"            => $truncate($default($item->bed_id ?? null, '000'), PayloadLength::BED_ID),
                        "bed_name"          => $truncate($default($item->bed_nama ?? null, '000'), PayloadLength::BED_NAME),
                        "class_id"          => $truncate($default($item->kelas_id ?? null, '000'), PayloadLength::CLASS_ID),
                        "class_name"        => $truncate($item->kelas_nama ?? '', PayloadLength::CLASS_NAME),
                        "cito"              => $truncate(($item->cito ?? false) ? 'Y' : 'N', PayloadLength::CITO),
                        "med_legal"         => 'N',
                        "user_id"           => $truncate($default($item->created_by ?? null, '000'), PayloadLength::USER_ID_OBR),
                        "reserve1"          => "",
                        "reserve2"          => "",
                        "reserve3"          => "",
                        "reserve4"          => "",
                        "order_test"        => $pemeriksaanList
                    ]
                ]
            ];
        };

        // Handle data
        if (is_iterable($data)) {
            // Batasi jumlah data jika terlalu banyak
            $limitedData = is_array($data) ? array_slice($data, 0, 100) : $data; // Max 100 records

            if (count($limitedData) > 1) {
                return array_map($build, $limitedData);
            }
            return $build($limitedData[0]);
        }

        return $build($data);
    }

    private function get_data_register(array $kode_transaksi)
    {
        $data = DB::connection('mysql2')->table('t_lab_register')
            ->select([
                't_lab_register.kode_transaksi',
                't_lab_register.cara_bayar_id',
                't_lab_register.created_at',
                't_lab_register.dokter_id',
                't_lab_register.cito',
                't_lab_register.created_by',
                't_pelayanan.id as pelayanan_id',
                't_pelayanan.no_register',
                't_pelayanan.layanan_id',
                't_pelayanan.bed_id',
                't_pelayanan.kelas_id',
                'm_pasien.no_rm',
                'm_pasien.nama as pasien_nama',
                DB::raw("
                    CASE 
                        WHEN m_pasien.jenis_kelamin = 1 THEN 'L'
                        WHEN m_pasien.jenis_kelamin = 2 THEN 'P'
                        ELSE '-' 
                    END as jenis_kelamin
                "),
                'm_pasien.tanggal_lahir',
                // 'm_pasien.alamat',
                DB::raw("SUBSTRING_INDEX(m_pasien.alamat, ' ', 5) as alamat"),
                'm_pasien.no_telepon_1',
                'm_pasien.no_telepon_2',
                'm_pasien.no_identitas',
                'm_cara_bayar.nama as cara_bayar_nama',
                'hrd_karyawan.nama as dokter_nama',
                'm_layanan.nama as layanan_nama',
                'm_kelas.nama as kelas_nama',
                'm_bed.nama as bed_nama',
                DB::raw("
                    (SELECT GROUP_CONCAT(kode SEPARATOR ',')
                    FROM m_lab_pemeriksaan 
                    WHERE id IN (
                        SELECT pemeriksaan_id 
                        FROM t_lab_register_pemeriksaan 
                        WHERE reg_id = t_lab_register.id AND batal = 0
                    )) AS lab_pemeriksaan
                ")
            ])
            ->join('t_pelayanan', 't_pelayanan.id', '=', 't_lab_register.pelayanan_id')
            ->leftJoin('m_pasien', 'm_pasien.id', '=', 't_pelayanan.pasien_id')
            ->leftJoin('m_cara_bayar', 'm_cara_bayar.id', '=', 't_lab_register.cara_bayar_id')
            ->leftJoin('hrd_karyawan', 'hrd_karyawan.id', '=', 't_lab_register.dokter_id')
            ->leftJoin('m_layanan', 'm_layanan.id', '=', 't_pelayanan.layanan_id')
            ->leftJoin('m_kelas', 'm_kelas.id', '=', 't_pelayanan.kelas_id')
            ->leftJoin('m_bed', 'm_bed.id', '=', 't_pelayanan.bed_id')
            ->whereIn('t_lab_register.kode_transaksi', $kode_transaksi)
            ->get();

        // dd($data->toSql());

        return $data;
    }
}
