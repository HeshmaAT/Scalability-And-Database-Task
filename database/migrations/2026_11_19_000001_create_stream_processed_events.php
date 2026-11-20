<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('stream_processed_events', function (Blueprint $table) {
            $table->string('consumer_group', 32);
            $table->uuid('event_id');
            $table->uuid('claim_token')->index();
            $table->string('consumer_name', 80);
            $table->unsignedBigInteger('link_id')->index();
            $table->char('payload_hash', 64);
            $table->timestamp('processed_at');
            $table->primary(['consumer_group','event_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('stream_processed_events'); }
};
