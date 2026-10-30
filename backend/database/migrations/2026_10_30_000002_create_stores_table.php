<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('address');
            $table->double('lat');
            $table->double('lng');
            $table->text('location_source');
            $table->text('contact_name')->nullable();
            $table->text('contact_phone')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->text('external_id_1c')->nullable()->unique();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_lat_check CHECK (lat BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE stores ADD CONSTRAINT stores_lng_check CHECK (lng BETWEEN -180 AND 180)');
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_location_source_check CHECK (location_source IN ('geocoder', 'manual'))");
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_contact_phone_e164_check CHECK (contact_phone ~ '^\\+[1-9][0-9]{6,14}$')");
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_comment_not_blank_check CHECK (btrim(comment) <> '')");
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_external_id_1c_not_blank_check CHECK (external_id_1c <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
