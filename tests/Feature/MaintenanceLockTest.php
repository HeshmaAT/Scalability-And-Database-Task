<?php
namespace Tests\Feature;

use App\Services\ClickMaintenanceService;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MaintenanceLockTest extends TestCase
{
    private function connection(int $wait, int $acquired): Connection
    {
        $connection = Mockery::mock(Connection::class);
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $connection->shouldReceive('selectOne')->once()
            ->with('SELECT GET_LOCK(?, ?) AS acquired', ['analytics:partition-maintenance', $wait], false)
            ->andReturn((object) ['acquired' => $acquired]);
        return $connection;
    }
    private function expectRelease(Connection $connection): void
    {
        $connection->shouldReceive('selectOne')->once()
            ->with('SELECT RELEASE_LOCK(?)', ['analytics:partition-maintenance'], false)
            ->andReturn((object) ['released' => 1]);
    }
    public function test_existing_callers_remain_non_blocking(): void
    {
        $connection = $this->connection(0, 1); $this->expectRelease($connection);
        $called = false;
        $ok = (new ClickMaintenanceService)->run(function () use (&$called) { $called = true; }, true);
        $this->assertTrue($ok); $this->assertTrue($called);
    }
    public function test_busy_lock_never_runs_callback_or_releases_another_owners_lock(): void
    {
        $this->connection(1, 0); $called = false;
        $ok = (new ClickMaintenanceService)->run(function () use (&$called) { $called = true; }, true, 1);
        $this->assertFalse($ok); $this->assertFalse($called);
    }
    public function test_lock_is_released_when_callback_throws(): void
    {
        $connection = $this->connection(1, 1); $this->expectRelease($connection);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fixture failure');
        (new ClickMaintenanceService)->run(function () { throw new \RuntimeException('fixture failure'); }, true, 1);
    }
    public function test_incomplete_archive_blocks_callback_and_releases_lock(): void
    {
        $connection = $this->connection(1, 1); $this->expectRelease($connection);
        $query = Mockery::mock(Builder::class);
        $connection->shouldReceive('table')->once()->with('click_archive_runs')->andReturn($query);
        $query->shouldReceive('useWritePdo')->once()->andReturnSelf();
        $query->shouldReceive('where')->once()->with('status', '!=', 'completed')->andReturnSelf();
        $query->shouldReceive('exists')->once()->andReturnTrue();
        $called = false;
        $ok = (new ClickMaintenanceService)->run(function () use (&$called) { $called = true; }, lockWaitSeconds: 1);
        $this->assertFalse($ok); $this->assertFalse($called);
    }
}
