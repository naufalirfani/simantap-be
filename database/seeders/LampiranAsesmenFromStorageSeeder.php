<?php

namespace Database\Seeders;

use App\Models\LampiranAsesmen;
use App\Models\Pegawai;
use App\Models\RiwayatAsesmen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LampiranAsesmenFromStorageSeeder extends Seeder
{
    private const BASE_DIR = 'lampiran-asesmen';

    public function run(): void
    {
        $disk = Storage::disk('local');

        if (!$disk->exists(self::BASE_DIR)) {
            $this->command->warn("LampiranAsesmenFromStorageSeeder: directory '" . self::BASE_DIR . "' not found.");
            return;
        }

        $folderPaths = $disk->directories(self::BASE_DIR);
        if (empty($folderPaths)) {
            $this->command->info('LampiranAsesmenFromStorageSeeder: no asesmen folders found.');
            return;
        }

        $riwayatNames = RiwayatAsesmen::query()
            ->select('nama_asesmen')
            ->distinct()
            ->pluck('nama_asesmen');

        $folderToNamaAsesmen = [];
        foreach ($riwayatNames as $namaAsesmen) {
            $snake = Str::snake($namaAsesmen);

            if (isset($folderToNamaAsesmen[$snake]) && $folderToNamaAsesmen[$snake] !== $namaAsesmen) {
                $this->command->warn(
                    "LampiranAsesmenFromStorageSeeder: duplicate snake_case '{$snake}' from nama_asesmen '{$namaAsesmen}', keeping '{$folderToNamaAsesmen[$snake]}'."
                );
                continue;
            }

            $folderToNamaAsesmen[$snake] = $namaAsesmen;
        }

        $created = 0;
        $updated = 0;
        $skippedNoRiwayat = 0;
        $skippedNoPegawai = 0;
        $skippedInvalidNip = 0;

        foreach ($folderPaths as $folderPath) {
            $folderName = basename($folderPath);

            if (!isset($folderToNamaAsesmen[$folderName])) {
                $skippedNoRiwayat++;
                $this->command->warn(
                    "LampiranAsesmenFromStorageSeeder: folder '{$folderName}' not found in riwayat_asesmen (snake_case nama_asesmen)."
                );
                continue;
            }

            $namaAsesmen = $folderToNamaAsesmen[$folderName];
            $filePaths = $disk->allFiles($folderPath);

            foreach ($filePaths as $filePath) {
                $filenameOnly = pathinfo($filePath, PATHINFO_FILENAME);
                $nip = trim($filenameOnly);

                if ($nip === '') {
                    $skippedInvalidNip++;
                    $this->command->warn(
                        "LampiranAsesmenFromStorageSeeder: invalid filename for NIP in '{$filePath}'."
                    );
                    continue;
                }

                $pegawai = Pegawai::query()
                    ->select(['id', 'nip'])
                    ->where('nip', $nip)
                    ->first();

                if (!$pegawai) {
                    $skippedNoPegawai++;
                    $this->command->warn(
                        "LampiranAsesmenFromStorageSeeder: pegawai not found for NIP '{$nip}' (file '{$filePath}')."
                    );
                    continue;
                }

                $attributes = [
                    'pegawai_id' => $pegawai->id,
                    'nama_asesmen' => $namaAsesmen,
                ];

                $values = [
                    'file_path' => $filePath,
                    'original_filename' => basename($filePath),
                    'file_size' => $disk->size($filePath),
                    'file_type' => $this->detectMimeType($disk->path($filePath)),
                ];

                $lampiran = LampiranAsesmen::updateOrCreate($attributes, $values);

                if ($lampiran->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        }

        $this->command->info(
            "LampiranAsesmenFromStorageSeeder: created={$created}, updated={$updated}, skipped_no_riwayat={$skippedNoRiwayat}, skipped_no_pegawai={$skippedNoPegawai}, skipped_invalid_nip={$skippedInvalidNip}."
        );
    }

    private function detectMimeType(string $absolutePath): ?string
    {
        if (!File::exists($absolutePath)) {
            return null;
        }

        try {
            return File::mimeType($absolutePath) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
