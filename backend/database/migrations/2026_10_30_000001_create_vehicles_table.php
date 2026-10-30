<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('plate')->unique();
            $table->decimal('fuel_consumption_l_per_100km', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // Номер зберігається нормалізованим: верхній регістр, без пробілів
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_plate_normalized_check CHECK (plate = upper(regexp_replace(plate, '\\s', '', 'g')) AND plate <> '')");
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_fuel_consumption_check CHECK (fuel_consumption_l_per_100km > 0 AND fuel_consumption_l_per_100km < 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
