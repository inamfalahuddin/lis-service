<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class BridgingController extends Controller
{
    public function order(Request $request)
    {
        $validated = $request->validate([
            'no_register' => ['required', 'array', 'min:1'],
            'no_register.*' => ['string'],
        ]);

        return response()->json([
            'message' => 'Body valid',
            'data' => $validated
        ]);
    }
}
