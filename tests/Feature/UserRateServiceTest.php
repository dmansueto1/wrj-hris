<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRateHistory;
use App\Services\UserRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UserRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testChangingTheRateUpdatesTheUserAndAddsOneHistoryRow(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 2));

        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['hourly_rate' => '80.00']);
        $historyBefore = $worker->rateHistories()->count();

        app(UserRateService::class)->changeRate($worker, '90.00', $admin);

        $this->assertSame('90.00', $worker->fresh()->hourly_rate);
        $this->assertSame($historyBefore + 1, $worker->rateHistories()->count());

        $latest = $worker->rateHistories()->latest('id')->first();

        $this->assertSame('90.00', $latest->hourly_rate);
        $this->assertSame('2026-03-02', $latest->effective_from->toDateString());
        $this->assertSame($admin->id, $latest->changed_by);
    }

    public function testChangingToTheSameRateAddsNoHistoryRow(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create(['hourly_rate' => '80.00']);
        $historyBefore = UserRateHistory::count();

        app(UserRateService::class)->changeRate($worker, '80', $admin);

        $this->assertSame('80.00', $worker->fresh()->hourly_rate);
        $this->assertSame($historyBefore, UserRateHistory::count());
    }

    public function testCreatingAUserWritesTheFirstHistoryRow(): void
    {
        $worker = User::factory()->create(['hourly_rate' => '75.50']);

        $this->assertCount(1, $worker->rateHistories);
        $this->assertSame('75.50', $worker->rateHistories->first()->hourly_rate);
    }

    public function testAnInvalidRateIsRejected(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(UserRateService::class)->changeRate($worker, '-5', $admin);
    }
}
