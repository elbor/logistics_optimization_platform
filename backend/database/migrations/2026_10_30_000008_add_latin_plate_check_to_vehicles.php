<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Кириличні двійники латиниці зберігаються латиницею, щоб один номер не існував у двох написаннях
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_plate_latin_check CHECK (plate !~ '[АВЕІКМНОРСТХ]')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_plate_latin_check');
    }
};
