<?php

namespace App\Http\Controllers;

use App\Services\HttpClientService;
use Illuminate\Http\Request;

class ResultsController extends MshController
{
    /**
     * Build URI for LIS API
     */
    private function buildResultUri(string $endpoint, array $params = []): string
    {
        $baseUri = sprintf(
            '/bridging/result/%s/%s',
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


    public function info()
    {
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
        $validated = $request->validate([
            'no_lab' => ['required', 'string', 'max:20'],
        ]);

        $uri = $this->buildResultUri('no_lab', [$validated['no_lab']]);

        $httpClient = app(HttpClientService::class);
        $response = $httpClient->sendToLIS($uri, [], 'GET');

        return response()->json($response, $response['status']);
    }

    public function get_by_periode(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
        if (!$this->validatePeriod($validated['start_date'], $validated['end_date'])) {
            return response()->json([
                'message' => 'Periode tidak boleh lebih dari 30 hari',
                'errors' => [
                    'end_date' => ['Maksimal periode adalah 30 hari']
                ]
            ], 422);
        }

        $uri = $this->buildResultUri('bridging/result_allperiode', [
            $validated['start_date'],
            $validated['end_date']
        ]);

        $httpClient = app(HttpClientService::class);
        $response = $httpClient->sendToLIS($uri, [], 'GET');

        return response()->json($response, $response['status']);
    }

    public function get_by_mrn_periode(Request $request)
    {
        $validated = $request->validate([
            'no_rm' => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
        if (!$this->validatePeriod($validated['start_date'], $validated['end_date'])) {
            return response()->json([
                'message' => 'Periode tidak boleh lebih dari 30 hari',
                'errors' => [
                    'end_date' => ['Maksimal periode adalah 30 hari']
                ]
            ], 422);
        }

        $uri = $this->buildResultUri('bridging/result_mikroperiode', [
            $validated['no_rm'],
            $validated['start_date'],
            $validated['end_date']
        ]);

        $httpClient = app(HttpClientService::class);
        $response = $httpClient->sendToLIS($uri, [], 'GET');

        return response()->json($response, $response['status']);
    }
}
