<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{
        Schema::create('link_webhooks',function(Blueprint $t){$t->unsignedBigInteger('link_id')->primary();$t->text('url');$t->text('secret');$t->boolean('enabled')->default(true);$t->timestamps();});
        Schema::create('webhook_deliveries',function(Blueprint $t){$t->uuid('event_id')->primary();$t->unsignedBigInteger('link_id')->index();$t->text('url');$t->text('secret');$t->longText('body');$t->string('status',16)->default('pending');$t->unsignedInteger('attempts')->default(0);$t->timestamp('available_at')->index();$t->timestamp('locked_until')->nullable();$t->uuid('claim_token')->nullable();$t->unsignedSmallInteger('last_status')->nullable();$t->string('last_error',100)->nullable();$t->timestamps();});
    }
    public function down():void{Schema::dropIfExists('webhook_deliveries');Schema::dropIfExists('link_webhooks');}
};
