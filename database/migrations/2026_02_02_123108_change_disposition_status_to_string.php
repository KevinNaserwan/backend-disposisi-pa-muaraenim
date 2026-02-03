<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            // Reverting to enum is tricky in some DBs without recreating, 
            // but for now we can leave it as string or try to revert if strictly needed.
            // Leaving as string is safer for down migration to avoid data loss if new statuses were added.
            // $table->enum('status', ['pending', 'read', 'completed'])->default('pending')->change();
        });
    }
};
