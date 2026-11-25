<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasColumn('links','lifecycle_version'))Schema::table('links',function(Blueprint $t){$t->unsignedBigInteger('lifecycle_version')->default(0);});
        if(!Schema::hasTable('link_events'))Schema::create('link_events',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('link_id');$t->unsignedBigInteger('version');$t->uuid('event_id')->unique();
            $t->string('type',64);$t->longText('payload');$t->dateTime('occurred_at',6);
            $t->unique(['link_id','version']);
        });
        if(!Schema::hasIndex('event_outbox','event_outbox_relay_scan'))Schema::table('event_outbox',fn(Blueprint $t)=>$t->index(['stream_name','published_at','created_at','event_id'],'event_outbox_relay_scan'));
    }
    public function down(): void {Schema::table('event_outbox',fn(Blueprint $t)=>$t->dropIndex('event_outbox_relay_scan'));Schema::dropIfExists('link_events');Schema::table('links',fn(Blueprint $t)=>$t->dropColumn('lifecycle_version'));}
};
