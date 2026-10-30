<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Рівно один рядок; відсутність рядка означає «не налаштовано»
        Schema::create('settings', function (Blueprint $table) {
            $table->smallInteger('id')->default(1)->primary();
            $table->text('warehouse_address');
            $table->double('warehouse_lat');
            $table->double('warehouse_lng');
            $table->text('warehouse_location_source');
            $table->decimal('fuel_price', 8, 2);
            $table->smallInteger('default_stop_minutes')->default(10);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_single_row_check CHECK (id = 1)');
        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_warehouse_lat_check CHECK (warehouse_lat BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_warehouse_lng_check CHECK (warehouse_lng BETWEEN -180 AND 180)');
        DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_warehouse_location_source_check CHECK (warehouse_location_source IN ('geocoder', 'manual'))");
        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_fuel_price_check CHECK (fuel_price > 0)');
        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_default_stop_minutes_check CHECK (default_stop_minutes BETWEEN 0 AND 240)');
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
