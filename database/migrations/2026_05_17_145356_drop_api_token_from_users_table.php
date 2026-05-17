<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The api_token column / custom AuthByToken middleware was dead code:
     * authentication is handled entirely by Laravel Sanctum
     * (personal_access_tokens). Drop the unused column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'api_token')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop the unique index first; SQLite cannot drop a column
                // that still backs an index.
                $table->dropUnique('users_api_token_unique');
                $table->dropColumn('api_token');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'api_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('api_token', 80)->unique()->nullable()->default(null);
            });
        }
    }
};
