<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use Illuminate\Console\Command;
class InitializeEventStream extends Command
{
    protected $signature = 'events:init';
    protected $description = 'Create the three independent link event consumer groups';
    public function handle(EventStream $stream): int
    {
        $stream->initialize();
        $this->info('Ready: analytics-writer, dashboard, milestones (start at retained history).');
        return self::SUCCESS;
    }
}
