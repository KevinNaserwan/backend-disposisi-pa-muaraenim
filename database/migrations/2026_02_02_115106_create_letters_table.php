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
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('agenda_number')->unique(); // Automatic input but needs to be stored
            $table->string('letter_number');
            $table->string('origin');
            $table->date('letter_date');
            $table->date('received_date')->nullable();
            $table->enum('type', ['important', 'ordinary'])->default('ordinary'); // Penting / Biasa
            $table->enum('classification', ['secret', 'ordinary'])->default('ordinary'); // Rahasia / Biasa
            $table->string('subject'); // Perihal
            $table->string('file_path')->nullable();
            $table->string('status')->default('processing'); // processing, archived
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
