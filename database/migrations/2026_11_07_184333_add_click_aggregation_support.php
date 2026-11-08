<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->unsignedBigInteger('click_count')->default(0);
        });

        Schema::create('processed_click_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->dateTime('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_click_batches');

        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('click_count');
        });
    }
};
