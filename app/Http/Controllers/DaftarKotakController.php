<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DaftarKotak;

class DaftarKotakController extends Controller
{
    /**
     * Return the latest daftar_kotak record.
     */
    public function index()
    {
        $record = DaftarKotak::latest()->first();

        if (! $record) {
            return response()->json([
                'intervals' => [
                    'potensial' => [],
                    'kinerja' => [],
                ],
                'kotak' => [],
            ], 200);
        }

        return response()->json([
            'intervals' => $record->intervals,
            'kotak' => $record->kotak,
        ], 200);
    }

    /**
     * Store a new daftar_kotak payload.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'intervals' => 'required|array',
            'kotak' => 'required|array',
        ]);

        $record = DaftarKotak::create([
            'intervals' => $data['intervals'],
            'kotak' => $data['kotak'],
        ]);

        return response()->json([
            'intervals' => $record->intervals,
            'kotak' => $record->kotak,
        ], 201);
    }
}
