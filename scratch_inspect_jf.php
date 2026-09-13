<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PetaJabatan;

$jfs = PetaJabatan::where('jenis_jabatan', 'LIKE', '%FUNGSIONAL%')->take(10)->get();
echo "=== SAMPLE JABATAN FUNGSIONAL IN PETA_JABATAN ===\n";
foreach ($jfs as $jf) {
    $curr = $jf;
    $chain = [];
    while ($curr) {
        $chain[] = "{$curr->nama_jabatan} [{$curr->jenis_jabatan}]";
        $curr = $curr->parent_id ? PetaJabatan::find($curr->parent_id) : null;
    }
    echo "JF: {$jf->nama_jabatan} | Chain: " . implode(" -> ", $chain) . "\n";
}

