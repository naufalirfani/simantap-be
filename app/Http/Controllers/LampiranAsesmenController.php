<?php

namespace App\Http\Controllers;

use App\Models\LampiranAsesmen;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class LampiranAsesmenController extends Controller
{
    /**
     * Display a listing of lampiran asesmens.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $perPage = min(max((int)$perPage, 1), 100);

            $withPagination = $request->boolean('with_pagination', true);

            $query = LampiranAsesmen::query()
                ->with('pegawai');

            // Filter by pegawai_id if provided
            if ($request->filled('pegawai_id')) {
                $query->where('pegawai_id', $request->get('pegawai_id'));
            }

            // Search by nama_asesmen if provided
            if ($request->filled('q')) {
                $q = $request->get('q');
                $query->where('nama_asesmen', 'ilike', "%{$q}%");
            }

            // Order by created_at descending
            $query->orderBy('created_at', 'desc');

            if ($withPagination) {
                $result = $query->paginate($perPage);
            } else {
                $result = $query->get();
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lampiran asesmens',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created lampiran asesmen in storage.
     * Supports both file upload and file_path string.
     * Files stored in: storage/app/private/lampiran-asesmen/{nama_asesmen_snake_case}/{hash}
     */
    public function store(Request $request)
    {
        try {
            // Handle both file upload and file_path string input
            if ($request->hasFile('file')) {
                // File upload validation
                $validated = $request->validate([
                    'pegawai_id' => 'required|uuid|exists:pegawai,id',
                    'nama_asesmen' => 'required|string|max:255',
                    'file' => 'required|file|max:10240', // max 10MB
                ]);

                $file = $request->file('file');
                $pegawaiId = $validated['pegawai_id'];
                $namaAsesmenSnake = Str::snake($validated['nama_asesmen']);
                
                // Store file in private storage: storage/app/private/lampiran-asesmen/{nama_asesmen_snake}/
                $filePath = $file->storeAs(
                    "lampiran-asesmen/{$namaAsesmenSnake}",
                    $file->hashName(),
                    'local'
                );

                $lampiranAsesmen = LampiranAsesmen::create([
                    'pegawai_id' => $pegawaiId,
                    'nama_asesmen' => $validated['nama_asesmen'],
                    'file_path' => $filePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                ]);
            } else {
                // String file_path input (fallback)
                $validated = $request->validate([
                    'pegawai_id' => 'required|uuid|exists:pegawai,id',
                    'nama_asesmen' => 'required|string|max:255',
                    'file_path' => 'required|string|max:255',
                ]);

                $lampiranAsesmen = LampiranAsesmen::create($validated);
            }

            return response()->json([
                'success' => true,
                'data' => $lampiranAsesmen,
                'message' => 'Lampiran asesmen created successfully',
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lampiran asesmen',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified lampiran asesmen.
     */
    public function show(string $id)
    {
        try {
            $lampiranAsesmen = LampiranAsesmen::with('pegawai')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $lampiranAsesmen,
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran asesmen not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lampiran asesmen',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified lampiran asesmen in storage.
     * Supports file upload replacement.
     */
    public function update(Request $request, string $id)
    {
        try {
            $lampiranAsesmen = LampiranAsesmen::findOrFail($id);

            // Validation for update
            $validated = $request->validate([
                'pegawai_id' => 'sometimes|uuid|exists:pegawai,id',
                'nama_asesmen' => 'sometimes|string|max:255',
                'file_path' => 'sometimes|string|max:255',
                'file' => 'sometimes|file|max:10240', // max 10MB
            ]);

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $pegawaiId = $validated['pegawai_id'] ?? $lampiranAsesmen->pegawai_id;
                $namaAsesmen = $validated['nama_asesmen'] ?? $lampiranAsesmen->nama_asesmen;
                $namaAsesmenSnake = Str::snake($namaAsesmen);

                // Delete old file if it exists
                if ($lampiranAsesmen->file_path) {
                    Storage::disk('local')->delete($lampiranAsesmen->file_path);
                }

                // Store new file
                $filePath = $file->storeAs(
                    "lampiran-asesmen/{$namaAsesmenSnake}",
                    $file->hashName(),
                    'local'
                );

                $validated['file_path'] = $filePath;
                $validated['original_filename'] = $file->getClientOriginalName();
                $validated['file_size'] = $file->getSize();
                $validated['file_type'] = $file->getMimeType();
            }

            $lampiranAsesmen->update($validated);

            return response()->json([
                'success' => true,
                'data' => $lampiranAsesmen,
                'message' => 'Lampiran asesmen updated successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran asesmen not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lampiran asesmen',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update lampiran asesmen by pegawai_id and nama_asesmen (upsert).
     * Supports file upload.
     */
    public function updateByPegawaiAndNama(Request $request)
    {
        try {
            $validated = $request->validate([
                'pegawai_id' => 'required|uuid|exists:pegawai,id',
                'nama_asesmen' => 'required|string|max:255',
                'file' => 'sometimes|file|max:10240', // max 10MB
                'file_path' => 'sometimes|string|max:255',
            ]);

            $pegawaiId = $validated['pegawai_id'];
            $namaAsesmen = $validated['nama_asesmen'];

            // Find or create lampiran asesmen
            $lampiranAsesmen = LampiranAsesmen::where('pegawai_id', $pegawaiId)
                ->where('nama_asesmen', $namaAsesmen)
                ->first();

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $namaAsesmenSnake = Str::snake($namaAsesmen);

                // Delete old file if exists
                if ($lampiranAsesmen && $lampiranAsesmen->file_path) {
                    Storage::disk('local')->delete($lampiranAsesmen->file_path);
                }

                // Store new file
                $filePath = $file->storeAs(
                    "lampiran-asesmen/{$namaAsesmenSnake}",
                    $file->hashName(),
                    'local'
                );

                $dataToSave = [
                    'pegawai_id' => $pegawaiId,
                    'nama_asesmen' => $namaAsesmen,
                    'file_path' => $filePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                ];
            } else {
                $dataToSave = [
                    'pegawai_id' => $pegawaiId,
                    'nama_asesmen' => $namaAsesmen,
                ];

                if (isset($validated['file_path'])) {
                    $dataToSave['file_path'] = $validated['file_path'];
                }
            }

            if ($lampiranAsesmen) {
                $lampiranAsesmen->update($dataToSave);
                $message = 'Lampiran asesmen updated successfully';
                $statusCode = Response::HTTP_OK;
            } else {
                $lampiranAsesmen = LampiranAsesmen::create($dataToSave);
                $message = 'Lampiran asesmen created successfully';
                $statusCode = Response::HTTP_CREATED;
            }

            return response()->json([
                'success' => true,
                'data' => $lampiranAsesmen,
                'message' => $message,
            ], $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lampiran asesmen',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified lampiran asesmen from storage.
     */
    public function destroy(string $id)
    {
        try {
            $lampiranAsesmen = LampiranAsesmen::findOrFail($id);
            
            // Delete the file if it exists in storage
            if ($lampiranAsesmen->file_path) {
                Storage::disk('local')->delete($lampiranAsesmen->file_path);
            }
            
            $lampiranAsesmen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lampiran asesmen deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran asesmen not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lampiran asesmen',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download the file attachment
     */
    public function download(string $id)
    {
        try {
            $lampiranAsesmen = LampiranAsesmen::findOrFail($id);

            if (!$lampiranAsesmen->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!Storage::disk('local')->exists($lampiranAsesmen->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage',
                ], Response::HTTP_NOT_FOUND);
            }

            return Storage::disk('local')->download(
                $lampiranAsesmen->file_path,
                $lampiranAsesmen->original_filename ?? 'attachment'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran asesmen not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
