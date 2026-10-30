<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_stop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->text('status')->default('pending');
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->unique(['route_stop_id', 'order_id']);
            $table->index('order_id');
        });

        DB::statement("ALTER TABLE route_stop_orders ADD CONSTRAINT route_stop_orders_status_check CHECK (status IN ('pending', 'delivered', 'not_delivered'))");
        DB::statement("ALTER TABLE route_stop_orders ADD CONSTRAINT route_stop_orders_comment_check CHECK (comment IS NULL OR (status = 'not_delivered' AND btrim(comment) <> ''))");

        // Замовлення — щонайбільше в одному маршруті; недоставлене звільняється для нового
        DB::statement("CREATE UNIQUE INDEX route_stop_orders_order_active_unique ON route_stop_orders (order_id) WHERE status <> 'not_delivered'");
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stop_orders');
    }
};
