<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('stream_milestone_counters',function(Blueprint $t){$t->unsignedBigInteger('link_id')->primary();$t->unsignedBigInteger('count');$t->unsignedBigInteger('legacy_baseline');});
        Schema::create('link_milestones',function(Blueprint $t){$t->id();$t->unsignedBigInteger('link_id');$t->unsignedBigInteger('milestone');$t->uuid('event_id')->unique();$t->timestamp('reached_at');$t->unique(['link_id','milestone']);});
        Schema::create('event_outbox',function(Blueprint $t){$t->uuid('event_id')->primary();$t->string('event_type',64);$t->string('stream_name',100);$t->longText('envelope');$t->timestamp('created_at');$t->timestamp('published_at')->nullable()->index();});
    }
    public function down(): void {Schema::dropIfExists('event_outbox');Schema::dropIfExists('link_milestones');Schema::dropIfExists('stream_milestone_counters');}
};
