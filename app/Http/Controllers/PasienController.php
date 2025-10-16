<?php

namespace App\Http\Controllers;

use App\Enums\StatusControlEnum;
use App\Services\HttpClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PasienController extends MshController
{
    const LOG_CHANNEL   = 'pasien';
    const LOG_PREFIX    = 'PASIEN_CONTROLLER';

    public function pasien(Request $request)
    {
        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Request received', [
            'method' => 'pasien',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'input' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'order_control' => ['required', Rule::in(array_column(StatusControlEnum::cases(), 'name'))],
                'no_rm' => ['required', 'digits_between:4,15'],
            ]);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Validation success', [
                'no_rm' => $validated['no_rm'],
                'order_control' => $validated['order_control']
            ]);

            $raw = $this->get_data_pasien($validated['no_rm']);

            if ($raw->isEmpty()) {
                Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - Patient data not found', [
                    'no_rm' => $validated['no_rm'],
                    'data_count' => $raw->count()
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

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Patient data retrieved successfully', [
                'no_rm' => $validated['no_rm'],
                'data_count' => $raw->count(),
                'patient_name' => $raw->first()->nama
            ]);

            $payload = $this->pasienPayload($raw->toArray(), $validated);

            Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - Payload constructed', [
                'payload_structure' => array_keys($payload),
                'patient_count' => count($raw)
            ]);

            $httpClient = app(HttpClientService::class);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Sending to LIS', [
                'endpoint' => 'bridging/other_pas',
                'no_rm' => $validated['no_rm']
            ]);

            $response = $httpClient->sendToLIS('bridging/other_pas', $payload, 'POST');

            // Return response langsung dari LIS
            if ($response['success']) {
                Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - LIS response success', [
                    'status_code' => $response['status'],
                    'response_type' => gettype($response['data']),
                    'no_rm' => $validated['no_rm']
                ]);

                if (is_array($response['data'])) {
                    return response()->json($response['data'], $response['status']);
                }

                return response($response['body'], $response['status'])
                    ->header('Content-Type', 'application/json');
            }

            // Jika gagal kirim ke LIS
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - LIS request failed', [
                'status_code' => $response['status'],
                'error' => $response['error'] ?? 'Unknown error',
                'response_body' => $response['body'] ?? null,
                'no_rm' => $validated['no_rm']
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
            Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all(),
                'no_rm' => $request->input('no_rm')
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Unexpected error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'no_rm' => $request->input('no_rm')
            ]);

            throw $e;
        }
    }

    private function pasienPayload($data, $payload)
    {
        Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - Building payload', [
            'data_count' => count($data),
            'order_control' => $payload['order_control']
        ]);

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
        Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - Fetching patient data', [
            'no_rm' => $no_rm,
            'connection' => 'mysql2'
        ]);

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

        Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - Patient query executed', [
            'no_rm' => $no_rm,
            'result_count' => $raw->count()
        ]);

        return $raw;
    }
}
