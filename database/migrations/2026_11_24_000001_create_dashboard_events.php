<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
    public function up():void{Schema::create('dashboard_events',function(Blueprint $t){
        $t->id();$t->uuid('event_id')->unique();$t->unsignedBigInteger('link_id');$t->char('source_hash',64);
        $t->unsignedBigInteger('total_clicks');$t->string('occurred_at',40);$t->timestamp('projected_at',6);
        $t->timestamp('broadcasted_at',6)->nullable();$t->index(['link_id','id']);
    });}
    public function down():void{Schema::dropIfExists('dashboard_events');}
};
