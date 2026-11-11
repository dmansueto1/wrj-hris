<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Index;
use App\Livewire\Attendance\Mine;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class FlaggedRemarksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 2026-10-21; the flagged attendance is from Monday 2026-10-19.
        $this->travelTo('2026-10-21 15:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->worker = User::factory()->worker()->create();
    }

    public function testAFlaggedAttendanceNotYetCorrectedShowsFlaggedInTheRemarks(): void
    {
        Attendance::factory()->for($this->worker)->onDate('2026-10-19')->flagged()->create();

        $this->daily()
            ->assertSeeHtml('data-test="remark-flagged"')
            ->assertDontSeeHtml('data-test="remark-was-flagged"');
    }

    public function testACorrectedFlaggedAttendanceShowsThatItWasFlagged(): void
    {
        $flagged = Attendance::factory()->for($this->worker)->onDate('2026-10-19')->flagged()->create();

        $corrected = $this->corrections()->setClockOut($flagged, Carbon::parse('2026-10-19 11:30'), 'Forgot to clock out.', $this->admin);

        $this->assertTrue($corrected->was_flagged);
        $this->assertSame(Attendance::STATUS_ADJUSTED, $corrected->status);

        $this->daily()
            ->assertSeeHtml('data-test="remark-was-flagged"')
            ->assertSee('Flagged, corrected')
            ->assertDontSeeHtml('data-test="remark-flagged"');
    }

    public function testACorrectionOfACorrectionStillShowsThatItWasFlagged(): void
    {
        $flagged = Attendance::factory()->for($this->worker)->onDate('2026-10-19')->flagged()->create();
        $first = $this->corrections()->setClockOut($flagged, Carbon::parse('2026-10-19 11:30'), 'Forgot to clock out.', $this->admin);

        $second = $this->corrections()->changeProject($first, Project::factory()->ongoing()->create(), 'Wrong project picked.', $this->admin);

        $this->assertTrue($second->was_flagged);
        $this->daily()->assertSeeHtml('data-test="remark-was-flagged"');
    }

    public function testACorrectedAttendanceThatWasNeverFlaggedHasNoFlagRemark(): void
    {
        $closed = Attendance::factory()->for($this->worker)->onDate('2026-10-19')->create();

        $corrected = $this->corrections()->changeTimes($closed, Carbon::parse('2026-10-19 07:00'), Carbon::parse('2026-10-19 11:00'), 'Left early that day.', $this->admin);

        $this->assertFalse($corrected->was_flagged);
        $this->daily()
            ->assertDontSeeHtml('data-test="remark-flagged"')
            ->assertDontSeeHtml('data-test="remark-was-flagged"');
    }

    public function testMyAttendanceAndTheByEmployeeTabShowTheSameRemarks(): void
    {
        $flagged = Attendance::factory()->for($this->worker)->onDate('2026-10-19')->flagged()->create();
        Attendance::factory()->for($this->worker)->onDate('2026-10-20')->flagged()->create();
        $this->corrections()->setClockOut($flagged, Carbon::parse('2026-10-19 11:30'), 'Forgot to clock out.', $this->admin);

        Livewire::actingAs($this->admin)->test(Index::class)
            ->set('tab', 'employee')
            ->set('employee', (string) $this->worker->id)
            ->assertSeeHtml('data-test="remark-flagged"')
            ->assertSeeHtml('data-test="remark-was-flagged"');

        Livewire::actingAs($this->worker)->test(Mine::class)
            ->assertSeeHtml('data-test="remark-flagged"')
            ->assertSeeHtml('data-test="remark-was-flagged"')
            ->assertDontSeeText('session');
    }

    private function daily(): Testable
    {
        return Livewire::actingAs($this->admin)->test(Index::class)->set('date', '2026-10-19');
    }

    private function corrections(): AttendanceCorrectionService
    {
        return app(AttendanceCorrectionService::class);
    }
}
