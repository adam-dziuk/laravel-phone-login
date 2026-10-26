<?php

use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only for apps that let the package keep phone users in a table of its own.
 * The name comes from phone-login.users.table — set it before migrating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(PhoneLogin::usersTable(), function (Blueprint $table) {
            $table->id();
            // Encrypted, too long for a short string.
            $table->text('phone');
            $table->char('phone_hash', 64)->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(PhoneLogin::usersTable());
    }
};
