<?php

namespace App\Http\Controllers;

use App\Enums\OrderControlEnum;
use App\Enums\StatusControlEnum;
use App\Models\TLabRegister;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class OrderController extends MshController
{
    public function order(Request $request)
    {
        $validated = $request->validate([
            'order_control' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
            'kode_transaksi' => ['required', 'array', 'min:1'],
            'kode_transaksi.*' => ['string'],
        ]);

        $transactionCodes = $validated['kode_transaksi'];
        $registration = TLabRegister::whereIn('kode_transaksi', $transactionCodes)->get();

        return response()->json([
            'message' => 'Body valid',
            'data' => $validated
        ]);
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

    private function orderPayload($data)
    {
        $payload = [
            "order" => [
                "msh" => $this->getMshData(),
                "pid" => [
                    "pmrn" => "m_pasien.no_rm",
                    "pname" => "m_pasien.nama",
                    "sex" => "m_pasien.jenis_kelamin",
                    "birth_dt" => "m_pasien.tanggal_lahir", // 24.04.1992
                    "address" => "m_pasien.alamat",
                    "no_tlp" => "m_pasien.no_telepon_1",
                    "no_hp" => "m_pasien.no_telepon_2",
                    "email" => "m_pasien.email",
                    "nik" => "m_pasien.no_identitas"
                ],
                "obr" => [
                    "order_control" => "ORDER_CONTROL",
                    "ptype" => "STATUS_PASIEN_RAWAT_INAP",
                    "reg_no" => "t_pelayanan.no_reg",
                    "order_lab" => "t_lab_register.kode_transaksi",
                    "provider_id" => "t_lab_register.cara_bayar_id",
                    "provider_name" => "m_cara_bayar.nama",
                    "order_date" => "t_lab_register.created_at|30.07.2025 15:24:11",
                    "clinician_id" => "t_lab_register.dokter_id",
                    "clinician_name" => "hrd_karyawan.nama",
                    "bangsal_id" => "t_pelayanan.layanan_id",
                    "bangsal_name" => "m_layanan.nama",
                    "bed_id" => "t_pelayanan.bed_id",
                    "bed_name" => "m_bed.nama",
                    "class_id" => "t_pelayanan.kelas_id",
                    "class_name" => "m_kelas.nama",
                    "cito" => "t_lab_register.cito|N:Y",
                    "med_legal" => "N",
                    "user_id" => "t_lab_register.created_by",
                    "reserve1" => "",
                    "order_test" => ["idtest1", "idtest2", "idtest3", "idtest4"]
                ]
            ]
        ];

        return $payload;
    }
}
