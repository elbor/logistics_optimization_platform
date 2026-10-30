<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->date('delivery_date');
            $table->text('comment')->nullable();
            $table->text('status')->default('new');
            $table->text('external_id_1c')->nullable()->unique();
            $table->timestampsTz();

            $table->index('store_id');
            $table->index(['delivery_date', 'status']);
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('new', 'planned', 'delivered', 'cancelled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_comment_not_blank_check CHECK (btrim(comment) <> '')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_external_id_1c_not_blank_check CHECK (external_id_1c <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
