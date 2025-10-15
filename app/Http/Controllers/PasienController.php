<?php

namespace App\Http\Controllers;

use App\Enums\OrderControlEnum;
use App\Enums\StatusControlEnum;
use App\Models\MPasien;
use App\Models\TLabRegister;
use App\Services\HttpClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PasienController extends MshController
{
    public function pasien(Request $request)
    {
        $validated = $request->validate([
            'order_control' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
            'no_rm' => ['required', 'digits_between:4,15'],
        ]);

        $raw = $this->get_data_pasien($validated['no_rm']);

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

        $payload = $this->pasienPayload($raw->toArray(), $validated);

        // return response()->json($payload);

        $httpClient = app(HttpClientService::class);
        $response = $httpClient->sendToLIS('bridging/other_pas', $payload, 'POST');

        // Return response langsung dari LIS
        if ($response['success']) {
            if (is_array($response['data'])) {
                return response()->json($response['data'], $response['status']);
            }
            return response($response['body'], $response['status'])
                ->header('Content-Type', 'application/json');
        }

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

    private function pasienPayload($data, $payload)
    {
        $build = function ($item) use ($payload) {
            $birthDate = null;
            if (!empty($item->tanggal_lahir)) {
                $birthDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item->tanggal_lahir)->format('d.m.Y');
            }

            return [
                "pasien" => [
                    "msh" => $this->getMshData(),
                    "pid" => [
                        "pmrn"      => (string) $item->no_rm,
                        "pname"     => (string) $item->nama,
                        "sex"       => (string) $item->jenis_kelamin,
                        "birth_dt"  => $birthDate,
                        "address"   => (string) $item->alamat,
                        "no_tlp"    => ($item->no_telepon_1 == '' ? '000000000' : (string) $item->no_telepon_1),
                        "no_hp"     => ($item->no_telepon_2 == '' ? '000000000' : (string) $item->no_telepon_2),
                        "email"     => (string) ($item->email ?? 'none@mail.com'),
                        "nik"       => (string) $item->no_identitas
                    ],
                    "obr" => [
                        "order_control" => (string) StatusControlEnum::fromName($payload['order_control'])->value,
                        "user_id"       => (string) ($item->created_by ?? '000'),
                        "reserve1"      => "",
                        "reserve2"      => "",
                        "reserve3"      => "",
                        "reserve4"      => "",
                    ]
                ]
            ];
        };

        if (is_iterable($data) && count($data) > 1) {
            return array_map($build, $data);
        }

        $item = is_iterable($data) ? $data[0] : $data;
        return $build($item);
    }

    private function get_data_pasien($no_rm)
    {
        $raw = DB::connection('mysql2')->table('m_pasien')->select(
            'no_rm',
            'nama',
            DB::raw("
                CASE 
                    WHEN m_pasien.jenis_kelamin = 1 THEN 'L'
                    WHEN m_pasien.jenis_kelamin = 2 THEN 'P'
                    ELSE '-' 
                END as jenis_kelamin
            "),
            'tanggal_lahir',
            'alamat',
            'no_telepon_1',
            'no_telepon_2',
            'no_identitas',
            'created_by'
        )
            ->where('no_rm', $no_rm)
            ->get();

        return $raw;
    }
}
