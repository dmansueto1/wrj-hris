<?php

namespace Tests\Feature;

use App\Livewire\Users\Edit;
use App\Livewire\Users\Index;
use App\Models\User;
use App\Models\UserRateHistory;
use App\Support\Pagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TablePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function testTheDefaultIsTenRowsPerPage(): void
    {
        $this->assertSame(10, Pagination::DEFAULT_PER_PAGE);
        $this->assertSame([10, 25, 50, 100], Pagination::PER_PAGE_OPTIONS);
    }

    public function testTheUsersListShowsTenRowsByDefault(): void
    {
        $this->actingAs(User::factory()->admin()->create(['employee_no' => 'ADM-0001']));
        $this->createWorkers(30);

        // Sorted by employee no.: ADM-0001, then EMP-0001 … EMP-0009 fill page 1.
        Livewire::test(Index::class)
            ->assertSet('perPage', 10)
            ->assertSee('EMP-0009')
            ->assertDontSee('EMP-0010')
            ->call('gotoPage', 2)
            ->assertSee('EMP-0010')
            ->assertSee('EMP-0019')
            ->assertDontSee('EMP-0020');
    }

    public function testTheUserCanChooseTheNumberOfRowsPerPage(): void
    {
        $this->actingAs(User::factory()->admin()->create(['employee_no' => 'ADM-0001']));
        $this->createWorkers(30);

        Livewire::test(Index::class)
            ->set('perPage', 25)
            ->assertSee('EMP-0024')
            ->assertDontSee('EMP-0025')
            ->set('perPage', 50)
            ->assertSee('EMP-0030');
    }

    public function testChangingRowsPerPageGoesBackToTheFirstPage(): void
    {
        $this->actingAs(User::factory()->admin()->create(['employee_no' => 'ADM-0001']));
        $this->createWorkers(30);

        Livewire::test(Index::class)
            ->call('gotoPage', 3)
            ->set('perPage', 25)
            ->assertSee('ADM-0001')
            ->assertSee('EMP-0001');
    }

    public function testAnUnsupportedRowsPerPageFallsBackToTheDefault(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->set('perPage', 5000)
            ->assertSet('perPage', 10);
    }

    public function testTheRowsPerPageChoiceIsShownUnderTheTable(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->assertSee('Rows per page')
            ->assertSeeHtml('data-test="per-page-select"');
    }

    public function testTheRateHistoryTableIsPaginatedTooWithTenRowsByDefault(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->worker()->create(['hourly_rate' => '80.00']);
        $this->actingAs($admin);

        // 1 row from creating the worker + 11 more = 12 rows, newest first.
        foreach (range(1, 11) as $i) {
            UserRateHistory::create([
                'user_id' => $worker->id,
                'hourly_rate' => (string) (100 + $i).'.00',
                'effective_from' => now()->addDays($i)->toDateString(),
                'changed_by' => $admin->id,
            ]);
        }

        Livewire::test(Edit::class, ['user' => $worker])
            ->assertSee('₱ 111.00')
            ->assertSee('₱ 102.00')
            ->assertDontSee('₱ 101.00')
            ->assertSee('Rows per page')
            ->call('gotoPage', 2)
            ->assertSee('₱ 101.00')
            ->assertSee('₱ 80.00');
    }

    private function createWorkers(int $count): void
    {
        foreach (range(1, $count) as $i) {
            User::factory()->worker()->create(['employee_no' => sprintf('EMP-%04d', $i)]);
        }
    }
}
