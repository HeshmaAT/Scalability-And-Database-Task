<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LinkEvent extends Model
{
    protected $table='link_events';public $timestamps=false;
    protected $guarded=[];
    protected $casts=['payload'=>'array','version'=>'integer'];
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Lifecycle events are append-only'));
        static::deleting(fn()=>throw new \LogicException('Lifecycle events are append-only'));
    }
}
