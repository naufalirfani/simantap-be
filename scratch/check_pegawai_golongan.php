<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$golongans = App\Models\Pegawai::whereNotNull('golongan')->distinct()->pluck('golongan')->all();
echo "Distinct golongans in DB: " . json_encode($golongans, JSON_PRETTY_PRINT) . PHP_EOL;

$samplePegawai = App\Models\Pegawai::select('id', 'name', 'nip', 'golongan')->take(5)->get();
echo "Sample pegawai: " . json_encode($samplePegawai, JSON_PRETTY_PRINT) . PHP_EOL;

