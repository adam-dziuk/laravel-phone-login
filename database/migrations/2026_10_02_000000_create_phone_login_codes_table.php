<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_login_codes', function (Blueprint $table) {
            $table->id();
            $table->char('phone_hash', 64);
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['phone_hash', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_login_codes');
    }
};
