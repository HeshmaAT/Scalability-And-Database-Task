<?php
namespace App\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
class LinkCountUpdated implements ShouldBroadcastNow{
    public function __construct(public array $payload){}
    public function broadcastOn():array{return [new PrivateChannel('links.'.$this->payload['link_id'])];}
    public function broadcastAs():string{return 'LinkCountUpdated';}
    public function broadcastWith():array{return $this->payload;}
}
