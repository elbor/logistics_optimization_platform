<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // Роль driver перевіряють політика і форма Filament
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->boolean('return_to_warehouse');
            $table->text('status');
            $table->integer('distance_m');
            $table->integer('drive_s');
            $table->integer('total_s');
            $table->integer('return_leg_distance_m')->nullable();
            $table->integer('return_leg_s')->nullable();
            $table->decimal('fuel_l', 7, 2);
            $table->decimal('fuel_cost', 10, 2);
            $table->decimal('fuel_price_snapshot', 8, 2);
            $table->decimal('fuel_consumption_snapshot', 5, 2);
            $table->smallInteger('stop_minutes_snapshot');
            $table->jsonb('geometry');
            // Час натискання на телефоні; *_received_at — коли отримав сервер
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('started_received_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['driver_id', 'date']);
            $table->index('date');
            $table->index('vehicle_id');
        });

        $checks = [
            'status' => "status IN ('draft', 'assigned', 'in_progress', 'completed')",
            'distance_m' => 'distance_m >= 0',
            'drive_s' => 'drive_s >= 0',
            'total_s' => 'total_s >= drive_s',
            'return_leg_distance_m' => 'return_leg_distance_m >= 0 AND return_to_warehouse = (return_leg_distance_m IS NOT NULL)',
            'return_leg_s' => 'return_leg_s >= 0 AND return_to_warehouse = (return_leg_s IS NOT NULL)',
            'fuel_l' => 'fuel_l >= 0',
            'fuel_cost' => 'fuel_cost >= 0',
            'fuel_price_snapshot' => 'fuel_price_snapshot > 0',
            'fuel_consumption_snapshot' => 'fuel_consumption_snapshot > 0',
            'stop_minutes_snapshot' => 'stop_minutes_snapshot >= 0',
            'geometry' => "geometry->>'type' = 'LineString'",
            'started' => "(status IN ('in_progress', 'completed')) = (started_at IS NOT NULL) AND (status IN ('in_progress', 'completed')) = (started_received_at IS NOT NULL)",
            'completed' => "(status = 'completed') = (completed_at IS NOT NULL) AND completed_at >= started_at",
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE routes ADD CONSTRAINT routes_{$name}_check CHECK ({$expression})");
        }

        DB::statement("CREATE UNIQUE INDEX routes_one_in_progress_per_driver ON routes (driver_id) WHERE status = 'in_progress'");
        DB::statement("CREATE UNIQUE INDEX routes_one_in_progress_per_vehicle ON routes (vehicle_id) WHERE status = 'in_progress'");

        // Видалення = скасування; розпочаті й завершені маршрути — історія, яку каскад не має стерти.
        // OR REPLACE: migrate:fresh видаляє таблиці, але не функції.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION routes_prevent_delete_started() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status NOT IN ('draft', 'assigned') THEN
                    RAISE EXCEPTION 'Route % has status % and cannot be deleted', OLD.id, OLD.status
                        USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN OLD;
            END;
            $$;

            CREATE TRIGGER routes_prevent_delete_started
                BEFORE DELETE ON routes
                FOR EACH ROW EXECUTE FUNCTION routes_prevent_delete_started();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
        DB::statement('DROP FUNCTION IF EXISTS routes_prevent_delete_started()');
    }
};
