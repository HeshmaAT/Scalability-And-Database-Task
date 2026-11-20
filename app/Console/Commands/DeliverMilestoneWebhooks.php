<?php
namespace App\Console\Commands;
use App\Services\Events\WebhookDeliveryWorker;
use Illuminate\Console\Command;
class DeliverMilestoneWebhooks extends Command{
    protected $signature='webhooks:deliver {--once}';
    protected $description='Deliver signed webhooks with leased durable retries and terminal dead-letter state';
    public function handle(WebhookDeliveryWorker $worker):int{
        do{try{$row=$worker->tick();if($row)$this->line(json_encode($row));elseif(!$this->option('once'))usleep(200000);}
        catch(\Throwable $e){$this->error(get_class($e).': '.$e->getMessage());if($this->option('once'))return 1;sleep(1);}}while(!$this->option('once'));return 0;
    }
}
