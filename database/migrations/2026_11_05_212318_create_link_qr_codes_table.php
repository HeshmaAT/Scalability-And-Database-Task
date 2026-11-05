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
        Schema::create('link_qr_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('link_id')->primary();
            $table->foreign('link_id')->references('id')->on('links')->restrictOnDelete();
            $table->enum('status', ['pending', 'ready'])->default('pending');
            $table->string('object_key')->nullable();
            $table->dateTime('generated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_qr_codes');
    }
};
