<?php

namespace App\Http\Controllers;

use App\Enums\StatusControlEnum;
use App\Models\TLabRegister;
use App\Services\HttpClientService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class OrderController extends MshController
{
    public function order(Request $request)
    {
        $validated = $request->validate([
            'order_control' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
            'status_pasien' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
            'kode_transaksi' => ['required', 'array', 'min:1'],
            'kode_transaksi.*' => ['string'],
        ]);

        $transactionCodes = $validated['kode_transaksi'];
        $raw = $this->get_data_register($transactionCodes);

        // Cek jika data tidak ditemukan
        if ($raw->isEmpty()) {
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

        $payload = $this->orderPayload($raw->toArray(), $validated);

        // return response()->json($payload);

        $httpClient = app(HttpClientService::class);
        $response = $httpClient->sendToLIS('bridging/order', $payload, 'POST');

        // Return response langsung dari LIS
        if ($response['success']) {
            // Jika LIS mengembalikan response JSON, return langsung
            if (is_array($response['data'])) {
                return response()->json($response['data'], $response['status']);
            }

            // Jika LIS mengembalikan string/plain text
            return response($response['body'], $response['status'])
                ->header('Content-Type', 'application/json');
        }

        // Jika gagal kirim ke LIS
        return response()->json([
            'response' => [
                'code' => (string) $response['status'],
                'message' => $response['error'] ?? 'Gagal terhubung ke server LIS',
                'product' => 'SOFTMEDIX LIS',
                'version' => 'ws.003',
                'id' => ''
            ]
        ], $response['status']);
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
        // Helper untuk membentuk satu payload dari 1 item data
        $build = function ($item) use ($payload) {
            $birthDate = null;
            if (!empty($item->tanggal_lahir)) {
                $birthDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item->tanggal_lahir)->format('d.m.Y');
            }

            $orderDate = null;
            if (!empty($item->created_at)) {
                $orderDate = \Carbon\Carbon::parse($item->created_at)
                    ->format('d.m.Y H:i:s');
            }

            return [
                "order" => [
                    "msh" => $this->getMshData(),
                    "pid" => [
                        "pmrn"      => (string) $item->no_rm,
                        "pname"     => (string) $item->pasien_nama,
                        "sex"       => (string) $item->jenis_kelamin,
                        "birth_dt"  => $birthDate,
                        "address"   => (string) $item->alamat,
                        "no_tlp"    => ($item->no_telepon_1 == '' ? '000000000' : (string) $item->no_telepon_1),
                        "no_hp"     => ($item->no_telepon_2 == '' ? '000000000' : (string) $item->no_telepon_2),
                        "email"     => (string) ($item->email ?? 'none@mail.com'), // kalau tidak ada field email, kasih null
                        "nik"       => (string) $item->no_identitas
                    ],
                    "obr" => [
                        "order_control"     => (string) StatusControlEnum::fromName($payload['order_control'])->value,
                        "ptype"             => (string) StatusControlEnum::fromName($payload['status_pasien'])->value,
                        // "reg_no"            => (string) $item->no_register,
                        "reg_no"            => (string) $item->pelayanan_id,
                        "order_lab"         => (string) $item->kode_transaksi,
                        "provider_id"       => (string) ($item->cara_bayar_id ?? '000'),
                        "provider_name"     => (string) $item->cara_bayar_nama,
                        "order_date"        => (string) $orderDate,
                        "clinician_id"      => (string) ($item->dokter_id == '0' ? '000' : (string) $item->dokter_id),
                        "clinician_name"    => (string) ($item->dokter_nama ?? '000'),
                        "bangsal_id"        => (string) ($item->layanan_id ?? '000'),
                        "bangsal_name"      => (string) $item->layanan_nama,
                        "bed_id"            => (string) ($item->bed_id ?? '000'),
                        "bed_name"          => (string) ($item->bed_nama ?? '000'),
                        "class_id"          => (string) ($item->kelas_id ?? '000'),
                        "class_name"        => (string) $item->kelas_nama,
                        "cito"              => (string) ($item->cito ? 'Y' : 'N'),
                        "med_legal"         => 'N',
                        "user_id"           => (string) ($item->created_by ?? '000'),
                        "reserve1"          => "",
                        "reserve2"          => "",
                        "reserve3"          => "",
                        "reserve4"          => "",
                        "order_test"        => ["idtest1", "idtest2", "idtest3", "idtest4"]
                    ]
                ]
            ];
        };

        // Jika lebih dari 1 data
        if (is_iterable($data) && count($data) > 1) {
            return array_map($build, $data);
        }

        // Jika hanya satu data
        $item = is_iterable($data) ? $data[0] : $data;
        return $build($item);
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
                'm_pasien.alamat',
                'm_pasien.no_telepon_1',
                'm_pasien.no_telepon_2',
                'm_pasien.no_identitas',
                'm_cara_bayar.nama as cara_bayar_nama',
                'hrd_karyawan.nama as dokter_nama',
                'm_layanan.nama as layanan_nama',
                'm_kelas.nama as kelas_nama',
                'm_bed.nama as bed_nama',
            ])
            ->join('t_pelayanan', 't_pelayanan.id', '=', 't_lab_register.pelayanan_id')
            ->join('m_pasien', 'm_pasien.id', '=', 't_pelayanan.pasien_id')
            ->join('m_cara_bayar', 'm_cara_bayar.id', '=', 't_lab_register.cara_bayar_id')
            ->leftJoin('hrd_karyawan', 'hrd_karyawan.id', '=', 't_lab_register.dokter_id')
            ->join('m_layanan', 'm_layanan.id', '=', 't_pelayanan.layanan_id')
            ->join('m_kelas', 'm_kelas.id', '=', 't_pelayanan.kelas_id')
            ->leftJoin('m_bed', 'm_bed.id', '=', 't_pelayanan.bed_id')
            ->whereIn('t_lab_register.kode_transaksi', $kode_transaksi)
            ->get();

        return $data;
    }
}
