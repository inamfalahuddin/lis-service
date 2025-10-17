<?php

namespace App\Http\Controllers;

use App\Services\HttpClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResultsController extends MshController
{
    const LOG_CHANNEL   = 'result';
    const LOG_PREFIX    = 'RESULT_CONTROLLER';

    /**
     * Build URI for LIS API
     */
    private function buildResultUri(string $endpoint, array $params = []): string
    {
        $baseUri = sprintf(
            '/%s/%s',
            env('LIS_USER_ID'),
            env('LIS_SECRET_KEY')
        );

        $paramString = implode('/', $params);

        return $endpoint . $baseUri . ($paramString ? '/' . $paramString : '');
    }

    /**
     * Validate date period (max 30 days)
     */
    private function validatePeriod(string $startDate, string $endDate): bool
    {
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate);
        $end = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate);

        return $end->diffInDays($start) <= 30;
    }

    /**
     * Format date from Y-m-d to Ymd for LIS API
     */
    private function formatDateForUri(string $date): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('Ymd');
    }

    /**
     * Build date range string for LIS API (YYYYMMDD-YYYYMMDD)
     */
    private function buildDateRange(string $startDate, string $endDate): string
    {
        $start = $this->formatDateForUri($startDate);
        $end = $this->formatDateForUri($endDate);

        return $start . '-' . $end;
    }

    public function info()
    {
        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Info endpoint accessed');

        return response()->json([
            'message' => 'Available SIMRS to LIS requests',
            'routes' => [
                [
                    'description' => 'Request by No Order Lab',
                    'method' => 'GET',
                    'endpoint' => '/result/no_order',
                    'params' => [
                        'no_lab' => 'string (required)'
                    ]
                ],
                [
                    'description' => 'Request by Periode',
                    'method' => 'GET',
                    'endpoint' => '/result/periode',
                    'params' => [
                        'start_date' => 'Y-m-d (required)',
                        'end_date' => 'Y-m-d (required)'
                    ]
                ],
                [
                    'description' => 'Request by MRN and Periode',
                    'method' => 'GET',
                    'endpoint' => '/result/mrn_periode',
                    'params' => [
                        'no_rm' => 'string (required)',
                        'start_date' => 'Y-m-d (required)',
                        'end_date' => 'Y-m-d (required)'
                    ]
                ],
            ]
        ]);
    }

    public function get_by_no_lab(Request $request)
    {
        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Get by no_lab request received', [
            'method' => 'get_by_no_lab',
            'ip' => $request->ip(),
            'input' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'no_lab' => ['required', 'string', 'max:20'],
            ]);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - No lab validation success', [
                'no_lab' => $validated['no_lab']
            ]);

            $uri = $this->buildResultUri('bridging/result', [$validated['no_lab']]);

            Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - URI constructed', [
                'uri' => $uri,
                'no_lab' => $validated['no_lab']
            ]);

            // ONLY TESTING PURPOSE
            // return response()->json(file_get_contents(storage_path('test/response/result_by_no_lab.json')));

            $httpClient = app(HttpClientService::class);
            $response = $httpClient->sendToLIS($uri, [], 'GET');

            if ($response['success']) {
                Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - LIS response success for no_lab', [
                    'no_lab' => $validated['no_lab'],
                    'status_code' => $response['status'],
                    'response_type' => gettype($response['data'])
                ]);

                // Return response asli dari LIS tanpa wrapper
                if (is_array($response['data'])) {
                    return response()->json($response['data'], $response['status']);
                }

                // Jika response berupa string/JSON string, kembalikan langsung
                return response($response['body'], $response['status'])
                    ->header('Content-Type', 'application/json');
            } else {
                Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - LIS request failed for no_lab', [
                    'no_lab' => $validated['no_lab'],
                    'status_code' => $response['status'],
                    'error' => $response['error'] ?? 'Unknown error'
                ]);

                // Return error response dalam format yang sama dengan LIS
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - No lab validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Unexpected error in get_by_no_lab', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'no_lab' => $request->input('no_lab')
            ]);
            throw $e;
        }
    }

    public function get_by_periode(Request $request)
    {
        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Get by periode request received', [
            'method' => 'get_by_periode',
            'ip' => $request->ip(),
            'input' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Periode validation success', [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date']
            ]);

            // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
            if (!$this->validatePeriod($validated['start_date'], $validated['end_date'])) {
                Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - Periode validation failed (exceeds 30 days)', [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'days_diff' => \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date'])
                        ->diffInDays(\Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']))
                ]);

                return response()->json([
                    'message' => 'Periode tidak boleh lebih dari 30 hari',
                    'errors' => [
                        'end_date' => ['Maksimal periode adalah 30 hari']
                    ]
                ], 422);
            }

            $dateRange = $this->buildDateRange($validated['start_date'], $validated['end_date']);
            $uri = $this->buildResultUri('bridging/result_allperiode', [
                $dateRange
            ]);

            Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - URI constructed for periode', [
                'uri' => $uri,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date']
            ]);

            $httpClient = app(HttpClientService::class);
            $response = $httpClient->sendToLIS($uri, [], 'GET');

            if ($response['success']) {
                Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - LIS response success for periode', [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status_code' => $response['status'],
                    'data_count' => is_array($response['data']) ? count($response['data']) : 'N/A'
                ]);

                // Return response asli dari LIS tanpa wrapper
                if (is_array($response['data'])) {
                    return response()->json($response['data'], $response['status']);
                }

                // Clean response body dari HTML errors sebelum dikembalikan
                $cleanedBody = $this->cleanResponseBody($response['body']);

                // Jika setelah cleaning body kosong, kembalikan error response
                if (empty(trim($cleanedBody))) {
                    Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - LIS response body empty after cleaning', [
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date']
                    ]);

                    return response()->json([
                        'response' => [
                            'code' => '500',
                            'message' => 'Response dari LIS tidak valid',
                            'product' => 'SOFTMEDIX LIS',
                            'version' => 'ws.003',
                            'id' => ''
                        ]
                    ], 500);
                }

                return response($cleanedBody, $response['status'])
                    ->header('Content-Type', 'application/json');
            } else {
                Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - LIS request failed for periode', [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status_code' => $response['status'],
                    'error' => $response['error'] ?? 'Unknown error'
                ]);

                // Return error response dalam format yang sama dengan LIS
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - Periode validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Unexpected error in get_by_periode', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date')
            ]);
            throw $e;
        }
    }

    public function get_by_mrn_periode(Request $request)
    {
        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Get by MRN periode request received', [
            'method' => 'get_by_mrn_periode',
            'ip' => $request->ip(),
            'input' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'no_rm' => ['required', 'string', 'max:20'],
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - MRN periode validation success', [
                'no_rm' => $validated['no_rm'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date']
            ]);

            // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
            if (!$this->validatePeriod($validated['start_date'], $validated['end_date'])) {
                Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - MRN periode validation failed (exceeds 30 days)', [
                    'no_rm' => $validated['no_rm'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'days_diff' => \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date'])
                        ->diffInDays(\Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']))
                ]);

                return response()->json([
                    'message' => 'Periode tidak boleh lebih dari 30 hari',
                    'errors' => [
                        'end_date' => ['Maksimal periode adalah 30 hari']
                    ]
                ], 422);
            }

            $dateRange = $this->buildDateRange($validated['start_date'], $validated['end_date']);
            $uri = $this->buildResultUri('bridging/result_mrnperiode', [
                $validated['no_rm'],
                $dateRange
            ]);

            Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - URI constructed for MRN periode', [
                'uri' => $uri,
                'no_rm' => $validated['no_rm'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date']
            ]);

            $httpClient = app(HttpClientService::class);
            $response = $httpClient->sendToLIS($uri, [], 'GET');

            if ($response['success']) {
                Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - LIS response success for MRN periode', [
                    'no_rm' => $validated['no_rm'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status_code' => $response['status'],
                    'data_count' => is_array($response['data']) ? count($response['data']) : 'N/A'
                ]);

                // Return response asli dari LIS tanpa wrapper
                if (is_array($response['data'])) {
                    return response()->json($response['data'], $response['status']);
                }

                // Clean response body dari HTML errors sebelum dikembalikan
                $cleanedBody = $this->cleanResponseBody($response['body']);

                if (empty(trim($cleanedBody))) {
                    Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - LIS response body empty after cleaning', [
                        'no_rm' => $validated['no_rm'],
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date']
                    ]);

                    return response()->json([
                        'response' => [
                            'code' => '500',
                            'message' => 'Response dari LIS tidak valid',
                            'product' => 'SOFTMEDIX LIS',
                            'version' => 'ws.003',
                            'id' => ''
                        ]
                    ], 500);
                }

                return response($cleanedBody, $response['status'])
                    ->header('Content-Type', 'application/json');
            } else {
                Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - LIS request failed for MRN periode', [
                    'no_rm' => $validated['no_rm'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status_code' => $response['status'],
                    'error' => $response['error'] ?? 'Unknown error'
                ]);

                // Return error response dalam format yang sama dengan LIS
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - MRN periode validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Unexpected error in get_by_mrn_periode', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'no_rm' => $request->input('no_rm'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date')
            ]);
            throw $e;
        }
    }

    public function get_by_mrn_all_periode(Request $request)
    {
        Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - Get by MRN all periode request received', [
            'method' => 'get_by_mrn_all_periode',
            'ip' => $request->ip(),
            'input' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'no_rm' => ['required', 'string', 'max:20'],
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - MRN all periode validation success', [
                'no_rm' => $validated['no_rm'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date']
            ]);

            // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
            if (!$this->validatePeriod($validated['start_date'], $validated['end_date'])) {
                Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - MRN all periode validation failed (exceeds 30 days)', [
                    'no_rm' => $validated['no_rm'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'days_diff' => \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date'])
                        ->diffInDays(\Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']))
                ]);

                return response()->json([
                    'message' => 'Periode tidak boleh lebih dari 30 hari',
                    'errors' => [
                        'end_date' => ['Maksimal periode adalah 30 hari']
                    ]
                ], 422);
            }

            $dateRange = $this->buildDateRange($validated['start_date'], $validated['end_date']);
            $uri = $this->buildResultUri('wslis/bridging/result_allperiode', [
                $dateRange
            ]);

            Log::channel(self::LOG_CHANNEL)->debug(self::LOG_PREFIX . ' - URI constructed for MRN all periode', [
                'uri' => $uri,
                'no_rm' => $validated['no_rm'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date']
            ]);

            $httpClient = app(HttpClientService::class);
            $response = $httpClient->sendToLIS($uri, [], 'GET');

            if ($response['success']) {
                Log::channel(self::LOG_CHANNEL)->info(self::LOG_PREFIX . ' - LIS response success for MRN all periode', [
                    'no_rm' => $validated['no_rm'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status_code' => $response['status'],
                    'data_count' => is_array($response['data']) ? count($response['data']) : 'N/A'
                ]);
            } else {
                Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - LIS request failed for MRN all periode', [
                    'no_rm' => $validated['no_rm'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'status_code' => $response['status'],
                    'error' => $response['error'] ?? 'Unknown error'
                ]);
            }

            return response()->json($response, $response['status']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel(self::LOG_CHANNEL)->warning(self::LOG_PREFIX . ' - MRN all periode validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::channel(self::LOG_CHANNEL)->error(self::LOG_PREFIX . ' - Unexpected error in get_by_mrn_all_periode', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'no_rm' => $request->input('no_rm'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date')
            ]);
            throw $e;
        }
    }

    /**
     * Clean response body from HTML errors and other noise
     */
    private function cleanResponseBody($body)
    {
        if (is_string($body)) {
            $cleaned = preg_replace('/<div style="border:1px solid #990000;.*?<\/div>/s', '', $body);
            $cleaned = preg_replace('/<[^>]*>/', '', $cleaned);
            $cleaned = trim($cleaned);

            return $cleaned ?: $body;
        }

        return $body;
    }
}
