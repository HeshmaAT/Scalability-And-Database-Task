<?php
namespace App\Services\Events;
use RuntimeException;

// Refuse partial history; an already-processed UUID must retain its exact envelope.
class ClickReplayCoverage
{
    public function count(array $events, array $ledger, int $linkId): int
    {
        $seen=[];
        foreach ($events as $event) {
            if (($event['event_type']??null)!=='LinkClicked' || ($event['payload']['link_id']??null)!==$linkId) {
                throw new RuntimeException('Unexpected event in isolated replay source');
            }
            $id=$event['event_id']??'';
            $hash=hash('sha256',json_encode($event,JSON_THROW_ON_ERROR));
            if (!isset($ledger[$id]) || !hash_equals($ledger[$id],$hash)) {
                throw new RuntimeException('Unprocessed or conflicting event; refuse counter replacement');
            }
            $seen[$id]=true;
        }
        if (count($seen)!==count($ledger)) {
            throw new RuntimeException('Incomplete retained history; use persisted raw clicks plus archived count');
        }
        return count($seen);
    }
}
