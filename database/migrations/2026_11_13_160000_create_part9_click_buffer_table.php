<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('part9_click_buffer', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->nullable()->index();
            $table->text('payload');
        });
    }
    public function down(): void { Schema::dropIfExists('part9_click_buffer'); }
};
