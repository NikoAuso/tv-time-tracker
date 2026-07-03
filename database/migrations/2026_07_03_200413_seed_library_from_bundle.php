<?php

declare(strict_types=1);

use App\Services\BundleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Al primo avvio on-device carica la libreria dal seed imbarcato. In
     * dev/test è no-op: i dati arrivano da `import:tvtime` o dalle factory.
     */
    public function up(): void
    {
        if (! BundleSeeder::runningOnDevice()) {
            return;
        }

        if (DB::table('users')->exists()) {
            return;
        }

        $seed = database_path('seed.sqlite');

        if (is_file($seed)) {
            BundleSeeder::seedInto($seed);
        }
    }

    public function down(): void
    {
        // ponytail: seed di soli dati, non reversibile granularmente; migrate:fresh droppa le tabelle.
    }
};
