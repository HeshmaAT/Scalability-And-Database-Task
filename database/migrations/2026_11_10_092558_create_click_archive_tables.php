<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_archive_runs', function (Blueprint $table) {
            $table->id();

            $table->string('partition_name', 32)->unique();
            $table->dateTime('upper_bound');

            $table->string('status', 20)->default('preparing');
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
        });

        Schema::create('click_archive_counts', function (Blueprint $table) {
            $table->foreignId('archive_run_id')
                ->constrained('click_archive_runs')
                ->restrictOnDelete();

            $table->foreignId('link_id')
                ->constrained('links')
                ->restrictOnDelete();

            $table->unsignedBigInteger('clicks');

            $table->primary(['archive_run_id', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_archive_counts');
        Schema::dropIfExists('click_archive_runs');
    }
};
