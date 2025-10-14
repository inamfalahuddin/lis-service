<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ResultsController extends MshController
{
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

        return response()->json([
            'message' => 'Body valid',
            'data' => $validated
        ]);
    }

    public function get_by_periode(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
        $startDate = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']);
        $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']);

        if ($endDate->diffInDays($startDate) > 30) {
            return response()->json([
                'message' => 'Periode tidak boleh lebih dari 30 hari',
                'errors' => [
                    'end_date' => ['Maksimal periode adalah 30 hari']
                ]
            ], 422);
        }

        return response()->json([
            'message' => 'Body valid',
            'data' => $validated
        ]);
    }

    public function get_by_mrn_periode(Request $request)
    {
        $validated = $request->validate([
            'no_rm' => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        // Validasi tambahan: end_date tidak boleh lebih dari 30 hari dari start_date
        $startDate = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['start_date']);
        $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['end_date']);

        if ($endDate->diffInDays($startDate) > 30) {
            return response()->json([
                'message' => 'Periode tidak boleh lebih dari 30 hari',
                'errors' => [
                    'end_date' => ['Maksimal periode adalah 30 hari']
                ]
            ], 422);
        }

        return response()->json([
            'message' => 'Body valid',
            'data' => $validated
        ]);
    }
}
