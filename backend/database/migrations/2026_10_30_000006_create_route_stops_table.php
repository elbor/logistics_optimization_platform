<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Зупинка = магазин; зміна порядку чи перебудова — видалити точки і вставити заново
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sequence');
            $table->integer('leg_distance_m');
            $table->integer('leg_s');
            $table->integer('arrival_offset_s');
            $table->text('status')->default('pending');
            // Час закриття зупинки (completed або skipped) на телефоні і на сервері
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('completed_received_at')->nullable();
            $table->timestampsTz();

            $table->unique(['route_id', 'store_id']);
            $table->unique(['route_id', 'sequence']);
            $table->index('store_id');
        });

        $checks = [
            'sequence' => 'sequence >= 1',
            'leg_distance_m' => 'leg_distance_m >= 0',
            'leg_s' => 'leg_s >= 0',
            'arrival_offset_s' => 'arrival_offset_s >= 0',
            'status' => "status IN ('pending', 'completed', 'skipped')",
            'closed' => "(status <> 'pending') = (completed_at IS NOT NULL) AND (status <> 'pending') = (completed_received_at IS NOT NULL)",
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE route_stops ADD CONSTRAINT route_stops_{$name}_check CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
