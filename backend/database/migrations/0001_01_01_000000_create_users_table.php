<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('phone')->nullable();
            $table->text('password');
            $table->text('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_email_lowercase_check CHECK (email = lower(email))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_phone_e164_check CHECK (phone ~ '^\\+[1-9][0-9]{6,14}$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'driver'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_driver_phone_check CHECK (role <> 'driver' OR phone IS NOT NULL)");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
