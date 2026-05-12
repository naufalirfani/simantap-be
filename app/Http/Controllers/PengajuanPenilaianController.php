<?php

namespace App\Http\Controllers;

use App\Models\PengajuanPenilaian;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PengajuanPenilaianController extends Controller
{
    /**
     * Display a listing of pengajuan penilaians.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $perPage = min(max((int)$perPage, 1), 100);

            $withPagination = $request->boolean('with_pagination', true);
            $withJoin = $request->boolean('with_join', false);

            // Get the relations to eager load (default: none)
            $relations = ['pegawai', 'subindikator', 'instrumen'];
  
            $query = PengajuanPenilaian::query();
            if (!empty($withJoin)) {
                $query->with($relations);
            }

            // Filter by pegawai_id if provided
            if ($request->filled('pegawai_id')) {
                $query->where('pegawai_id', $request->get('pegawai_id'));
            }

            // Filter by subindikator_id if provided
            if ($request->filled('subindikator_id')) {
                $query->where('subindikator_id', $request->get('subindikator_id'));
            }

            // Filter by instrumen_id if provided
            if ($request->filled('instrumen_id')) {
                $query->where('instrumen_id', $request->get('instrumen_id'));
            }

            // Filter by status if provided
            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
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
                'data' => $this->appendFileUrlsToResult($result),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pengajuan penilaians',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created pengajuan penilaian in storage.
     * Supports both file upload and file_path string.
     * Files stored in: storage/app/private/pengajuan-penilaian/{pegawai_id}/{hash}
     */
    public function store(Request $request)
    {
        try {
            // Handle both file upload and bukti_dukung string input
            if ($request->hasFile('file')) {
                // File upload validation
                $validated = $request->validate([
                    'pegawai_id' => 'required|uuid|exists:pegawai,id',
                    'subindikator_id' => 'required|uuid|exists:subindikators,id',
                    'instrumen_id' => 'required|uuid|exists:instrumens,id',
                    'file' => 'required|file|max:10240', // max 10MB
                    'status' => 'required|string|max:255',
                    'tanggal_sk' => 'sometimes|date',
                    'catatan' => 'sometimes|string|nullable',
                ], [
                    'status.unique_diajukan' => 'Pengajuan sedang diproses untuk penilaian yang sama',
                ]);
                
                // Check if employee already has a "Diajukan" submission for this subindikator
                if ($validated['status'] === 'Diajukan') {
                    $existingDiajukan = PengajuanPenilaian::where('pegawai_id', $validated['pegawai_id'])
                        ->where('subindikator_id', $validated['subindikator_id'])
                        ->where('status', 'Diajukan')
                        ->exists();

                    if ($existingDiajukan) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => [
                                'status' => ['Pengajuan sedang diproses untuk penilaian yang sama'],
                            ],
                        ], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                }

                $file = $request->file('file');
                $pegawaiId = $validated['pegawai_id'];
                $storageDirectory = "pengajuan-penilaian/{$pegawaiId}";
                $storedFileName = $this->buildStoredFileName($file);
                
                // Store file in private storage: storage/app/private/pengajuan-penilaian/{pegawai_id}/
                $filePath = $file->storeAs(
                    $storageDirectory,
                    $storedFileName,
                    'local'
                );

                $pengajuanPenilaian = PengajuanPenilaian::create([
                    'pegawai_id' => $validated['pegawai_id'],
                    'subindikator_id' => $validated['subindikator_id'],
                    'instrumen_id' => $validated['instrumen_id'],
                    'bukti_dukung' => $filePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                    'status' => $validated['status'],
                    'tanggal_sk' => $validated['tanggal_sk'] ?? null,
                    'catatan' => $validated['catatan'] ?? null,
                ]);
            } else {
                // String bukti_dukung input (fallback)
                $validated = $request->validate([
                    'pegawai_id' => 'required|uuid|exists:pegawai,id',
                    'subindikator_id' => 'required|uuid|exists:subindikators,id',
                    'instrumen_id' => 'required|uuid|exists:instrumens,id',
                    'bukti_dukung' => 'required|string|max:255',
                    'status' => 'required|string|max:255',
                    'tanggal_sk' => 'sometimes|date',
                    'catatan' => 'sometimes|string|nullable',
                ]);

                // Check if employee already has a "Diajukan" submission for this subindikator
                if ($validated['status'] === 'Diajukan') {
                    $existingDiajukan = PengajuanPenilaian::where('pegawai_id', $validated['pegawai_id'])
                        ->where('subindikator_id', $validated['subindikator_id'])
                        ->where('status', 'Diajukan')
                        ->exists();

                    if ($existingDiajukan) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => [
                                'status' => ['Pengajuan sedang diproses untuk penilaian yang sama'],
                            ],
                        ], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                }

                $pengajuanPenilaian = PengajuanPenilaian::create($validated);
            }

            return response()->json([
                'success' => true,
                'data' => $this->appendFileUrlsToItem($pengajuanPenilaian),
                'message' => 'Pengajuan penilaian created successfully',
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
                'message' => 'Failed to create pengajuan penilaian',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified pengajuan penilaian.
     */
    public function show(string $id)
    {
        try {
            $pengajuanPenilaian = PengajuanPenilaian::with('pegawai', 'subindikator', 'instrumen')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $pengajuanPenilaian,
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan penilaian not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pengajuan penilaian',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified pengajuan penilaian in storage.
     * Supports file upload replacement.
     */
    public function update(Request $request, string $id)
    {
        try {
            $pengajuanPenilaian = PengajuanPenilaian::findOrFail($id);

            // Validation for update
            $validated = $request->validate([
                'pegawai_id' => 'sometimes|uuid|exists:pegawai,id',
                'subindikator_id' => 'sometimes|uuid|exists:subindikators,id',
                'instrumen_id' => 'sometimes|uuid|exists:instrumens,id',
                'bukti_dukung' => 'sometimes|string|max:255',
                'file' => 'sometimes|file|max:10240', // max 10MB
                'status' => 'sometimes|string|max:255',
                'tanggal_sk' => 'sometimes|date',
                'catatan' => 'sometimes|string|nullable',
            ]);

            // Check if employee already has a "Diajukan" submission for this subindikator
            $pegawaiId = $validated['pegawai_id'] ?? $pengajuanPenilaian->pegawai_id;
            $subindikatorId = $validated['subindikator_id'] ?? $pengajuanPenilaian->subindikator_id;
            $status = $validated['status'] ?? $pengajuanPenilaian->status;

            if ($status === 'Diajukan') {
                $existingDiajukan = PengajuanPenilaian::where('pegawai_id', $pegawaiId)
                    ->where('subindikator_id', $subindikatorId)
                    ->where('status', 'Diajukan')
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existingDiajukan) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => [
                            'status' => ['Pengajuan sedang diproses untuk penilaian yang sama'],
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $pegawaiId = $validated['pegawai_id'] ?? $pengajuanPenilaian->pegawai_id;
                $storageDirectory = "pengajuan-penilaian/{$pegawaiId}";

                // Delete old file if it exists
                if ($pengajuanPenilaian->bukti_dukung) {
                    Storage::disk('local')->delete($pengajuanPenilaian->bukti_dukung);
                }

                $storedFileName = $this->buildStoredFileName($file);

                // Store new file
                $filePath = $file->storeAs(
                    $storageDirectory,
                    $storedFileName,
                    'local'
                );

                $validated['bukti_dukung'] = $filePath;
                $validated['original_filename'] = $file->getClientOriginalName();
                $validated['file_size'] = $file->getSize();
                $validated['file_type'] = $file->getMimeType();
            }

            $pengajuanPenilaian->update($validated);

            return response()->json([
                'success' => true,
                'data' => $this->appendFileUrlsToItem($pengajuanPenilaian),
                'message' => 'Pengajuan penilaian updated successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan penilaian not found',
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
                'message' => 'Failed to update pengajuan penilaian',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Approve the specified pengajuan penilaian.
     */
    public function approve(Request $request, string $id)
    {
        try {
            $pengajuanPenilaian = PengajuanPenilaian::findOrFail($id);

            $validated = $request->validate([
                'catatan_admin' => 'sometimes|string|nullable',
                'tanggal_sk' => 'sometimes|date',
            ]);

            $pengajuanPenilaian->update(array_merge($validated, [
                'status' => 'Diterima',
            ]));

            return response()->json([
                'success' => true,
                'data' => $this->appendFileUrlsToItem($pengajuanPenilaian),
                'message' => 'Pengajuan penilaian approved successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan penilaian not found',
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
                'message' => 'Failed to approve pengajuan penilaian',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reject the specified pengajuan penilaian.
     */
    public function reject(Request $request, string $id)
    {
        try {
            $pengajuanPenilaian = PengajuanPenilaian::findOrFail($id);

            $validated = $request->validate([
                'catatan_admin' => 'required|string',
            ]);

            $pengajuanPenilaian->update([
                'status' => 'Ditolak',
                'catatan_admin' => $validated['catatan_admin'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->appendFileUrlsToItem($pengajuanPenilaian),
                'message' => 'Pengajuan penilaian rejected successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan penilaian not found',
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
                'message' => 'Failed to reject pengajuan penilaian',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified pengajuan penilaian from storage.
     */
    public function destroy(string $id)
    {
        try {
            $pengajuanPenilaian = PengajuanPenilaian::findOrFail($id);
            
            // Delete the file if it exists in storage
            if ($pengajuanPenilaian->bukti_dukung) {
                Storage::disk('local')->delete($pengajuanPenilaian->bukti_dukung);
            }
            
            $pengajuanPenilaian->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan penilaian deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan penilaian not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete pengajuan penilaian',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download the file attachment
     */
    public function download(string $id)
    {
        return $this->respondWithFile($id, false);
    }

    /**
     * Preview the file attachment inline for PDF files.
     */
    public function preview(string $id)
    {
        return $this->respondWithFile($id, true);
    }

    /**
     * Attach download/preview URLs to a single pengajuan penilaian item.
     */
    private function appendFileUrlsToItem(PengajuanPenilaian $pengajuanPenilaian): PengajuanPenilaian
    {
        $pengajuanPenilaian->setAttribute('download_url', url("api/pengajuan-penilaians/{$pengajuanPenilaian->id}/download"));
        $pengajuanPenilaian->setAttribute('preview_url', url("api/pengajuan-penilaians/{$pengajuanPenilaian->id}/preview"));

        return $pengajuanPenilaian;
    }

    /**
     * Attach download/preview URLs to paginated or plain collection results.
     */
    private function appendFileUrlsToResult(mixed $result): mixed
    {
        if (is_object($result) && method_exists($result, 'getCollection') && method_exists($result, 'setCollection')) {
            $result->setCollection($result->getCollection()->map(function (PengajuanPenilaian $item) {
                return $this->appendFileUrlsToItem($item);
            }));

            return $result;
        }

        if (is_iterable($result)) {
            return collect($result)->map(function (PengajuanPenilaian $item) {
                return $this->appendFileUrlsToItem($item);
            });
        }

        return $result;
    }

    /**
     * Return the file as download or inline response.
     */
    private function respondWithFile(string $id, bool $inline)
    {
        try {
            $pengajuanPenilaian = PengajuanPenilaian::findOrFail($id);

            if (!$pengajuanPenilaian->bukti_dukung) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!Storage::disk('local')->exists($pengajuanPenilaian->bukti_dukung)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage',
                ], Response::HTTP_NOT_FOUND);
            }

            $mimeType = File::mimeType(Storage::disk('local')->path($pengajuanPenilaian->bukti_dukung)) ?: 'application/octet-stream';

            if ($inline) {
                if ($mimeType !== 'application/pdf') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Preview only available for PDF files',
                    ], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
                }

                return response()->file(
                    Storage::disk('local')->path($pengajuanPenilaian->bukti_dukung),
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . ($pengajuanPenilaian->original_filename ?? 'attachment.pdf') . '"',
                    ]
                );
            }

            return response()->download(
                Storage::disk('local')->path($pengajuanPenilaian->bukti_dukung),
                $pengajuanPenilaian->original_filename ?? 'attachment',
                ['Content-Type' => $mimeType]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan penilaian not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Build a safe, original-like filename.
     * If the same filename is uploaded in the same directory, it will overwrite.
     */
    private function buildStoredFileName(UploadedFile $file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBaseName = Str::of($originalName)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9\-_]+/', '_')
            ->trim('_')
            ->value();

        if ($safeBaseName === '') {
            $safeBaseName = 'file';
        }

        $extension = $file->getClientOriginalExtension();
        $extensionPart = $extension !== '' ? ".{$extension}" : '';

        return "{$safeBaseName}{$extensionPart}";
    }
}
