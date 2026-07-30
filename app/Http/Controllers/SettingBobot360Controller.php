<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingBobot360Controller extends Controller
{
    private $filePath = 'settings/bobot_360.json';

    private $categories = [
        'jpt_madya',
        'jpt_pratama',
        'administrator',
        'pengawas',
        'pelaksana',
        'jf',
        'kepala_kantor'
    ];

    /**
     * Get the current 360 weight settings.
     */
    public function index()
    {
        try {
            if (Storage::disk('local')->exists($this->filePath)) {
                $content = Storage::disk('local')->get($this->filePath);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    return response()->json($data, 200);
                }
            }

            // Default fallback settings matching the user's reference table
            $defaultSettings = [
                'jpt_madya' => [
                    'atasan_langsung' => 40,
                    'penerima_manfaat' => 0,
                    'rekan_kerja' => 15,
                    'bawahan' => 40,
                    'penilaian_diri' => 5,
                ],
                'jpt_pratama' => [
                    'atasan_langsung' => 40,
                    'penerima_manfaat' => 0,
                    'rekan_kerja' => 30,
                    'bawahan' => 25,
                    'penilaian_diri' => 5,
                ],
                'administrator' => [
                    'atasan_langsung' => 40,
                    'penerima_manfaat' => 0,
                    'rekan_kerja' => 30,
                    'bawahan' => 25,
                    'penilaian_diri' => 5,
                ],
                'pengawas' => [
                    'atasan_langsung' => 40,
                    'penerima_manfaat' => 0,
                    'rekan_kerja' => 30,
                    'bawahan' => 25,
                    'penilaian_diri' => 5,
                ],
                'pelaksana' => [
                    'atasan_langsung' => 50,
                    'penerima_manfaat' => 0,
                    'rekan_kerja' => 45,
                    'bawahan' => 0,
                    'penilaian_diri' => 5,
                ],
                'jf' => [
                    'atasan_langsung' => 30,
                    'penerima_manfaat' => 30,
                    'rekan_kerja' => 35,
                    'bawahan' => 0,
                    'penilaian_diri' => 5,
                ],
                'kepala_kantor' => [
                    'atasan_langsung' => 50,
                    'penerima_manfaat' => 0,
                    'rekan_kerja' => 0,
                    'bawahan' => 45,
                    'penilaian_diri' => 5,
                ],
            ];

            return response()->json($defaultSettings, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil setting bobot 360.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store/update the 360 weight settings.
     */
    public function store(Request $request)
    {
        try {
            $rules = [];
            foreach ($this->categories as $category) {
                $rules["{$category}"] = 'required|array';
                $rules["{$category}.atasan_langsung"] = 'required|numeric|min:0|max:100';
                $rules["{$category}.penerima_manfaat"] = 'required|numeric|min:0|max:100';
                $rules["{$category}.rekan_kerja"] = 'required|numeric|min:0|max:100';
                $rules["{$category}.bawahan"] = 'required|numeric|min:0|max:100';
                $rules["{$category}.penilaian_diri"] = 'required|numeric|min:0|max:100';
            }

            $data = $request->validate($rules);

            // Verify total sum is exactly 100 for each category
            foreach ($this->categories as $category) {
                $total = $data[$category]['atasan_langsung']
                       + $data[$category]['penerima_manfaat']
                       + $data[$category]['rekan_kerja']
                       + $data[$category]['bawahan']
                       + $data[$category]['penilaian_diri'];

                if (round($total, 2) != 100.00) {
                    return response()->json([
                        'message' => "Total bobot untuk " . str_replace('_', ' ', strtoupper($category)) . " harus bernilai 100. Total saat ini: {$total}"
                    ], 422);
                }
            }

            // Save to static JSON file
            Storage::disk('local')->put($this->filePath, json_encode($data, JSON_PRETTY_PRINT));

            return response()->json([
                'message' => 'Setting bobot 360 berhasil disimpan.',
                'data' => $data
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan setting bobot 360.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
