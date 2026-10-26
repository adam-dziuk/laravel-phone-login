# Upgrade Guide

## Upgrading To 0.2 From 0.1

### Encrypted Phone Numbers

Phone numbers are now stored encrypted. The login finds them by `phone_hash`,
an HMAC of the number keyed with `APP_KEY`, and the `phone_login_codes` table
keeps only that hash.

A phone user model of your own needs the `EncryptsPhone` trait; the package's
`PhoneUser` already has it:

```php
use AdamDziuk\PhoneLogin\Concerns\EncryptsPhone;

class Customer extends Authenticatable
{
    use EncryptsPhone;
}
```

Then create a migration that converts both tables. Replace `customers` with
your phone users' table, and add `nullable()` to `phone` if it was nullable:

```php
<?php

use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Codes expire within minutes, so they are dropped, not converted.
        DB::table('phone_login_codes')->delete();

        Schema::table('phone_login_codes', function (Blueprint $table) {
            $table->dropIndex(['phone', 'expires_at']);
            $table->dropColumn('phone');
        });

        Schema::table('phone_login_codes', function (Blueprint $table) {
            $table->char('phone_hash', 64)->after('id');
            $table->index(['phone_hash', 'expires_at']);
        });

        // The unique index goes first: MySQL cannot index a text column.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->char('phone_hash', 64)->nullable()->unique()->after('phone');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->text('phone')->change();
        });

        DB::table('customers')->whereNotNull('phone')->lazyById()->each(function (object $row) {
            DB::table('customers')->where('id', $row->id)->update([
                'phone' => Crypt::encryptString($row->phone),
                'phone_hash' => PhoneLogin::phoneHash($row->phone),
            ]);
        });
    }
};
```

Queries of your own by number, such as `where('phone', $phone)`, no longer
match. Use `where('phone_hash', PhoneLogin::phoneHash($phone))` instead.
