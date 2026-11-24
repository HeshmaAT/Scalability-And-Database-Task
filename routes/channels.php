<?php
use Illuminate\Support\Facades\Broadcast;
use App\Models\Link;
use App\Models\User;
Broadcast::channel('links.{linkId}',function(User $user,string $linkId){
    return Link::query()->useWritePdo()->where('user_id',$user->id)->whereKey($linkId)->exists();
});
