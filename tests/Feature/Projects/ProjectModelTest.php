<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectModelTest extends TestCase
{
    use RefreshDatabase;

    public function testTheOngoingScopeReturnsOnlyOngoingProjects(): void
    {
        $ongoing = Project::factory()->ongoing()->create();
        Project::factory()->planning()->create();
        Project::factory()->onHold()->create();
        Project::factory()->completed()->create();

        $this->assertSame([$ongoing->id], Project::ongoing()->pluck('id')->all());
    }

    public function testTheOngoingScopeExcludesArchivedProjects(): void
    {
        Project::factory()->ongoing()->create()->delete();

        $this->assertSame(0, Project::ongoing()->count());
    }

    public function testANewProjectStartsAsPlanning(): void
    {
        $this->assertSame(Project::STATUS_PLANNING, (new Project)->status);

        $id = Project::query()->insertGetId([
            'code' => 'RAW-01',
            'name' => 'Inserted without a status',
            'location' => 'Taguig',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(Project::STATUS_PLANNING, Project::findOrFail($id)->status);
    }

    public function testMoneyAndDatesAreCast(): void
    {
        $project = Project::factory()->create([
            'budget' => '1500000.5',
            'start_date' => '2026-01-05',
            'latitude' => '14.5995124',
            'longitude' => '120.9842195',
        ])->fresh();

        $this->assertSame('1500000.50', $project->budget);
        $this->assertSame('2026-01-05', $project->start_date->toDateString());
        $this->assertSame('14.5995124', $project->latitude);
        $this->assertSame('120.9842195', $project->longitude);
    }

    public function testTheCodeIsUniqueInTheDatabase(): void
    {
        Project::factory()->create(['code' => 'ABC-01']);

        $this->expectException(UniqueConstraintViolationException::class);

        Project::factory()->create(['code' => 'ABC-01']);
    }

    public function testTheStatusLabelAndBadgeColor(): void
    {
        $this->assertSame(['Planning', 'zinc'], [Project::factory()->planning()->make()->statusLabel(), Project::factory()->planning()->make()->statusColor()]);
        $this->assertSame(['Ongoing', 'green'], [Project::factory()->ongoing()->make()->statusLabel(), Project::factory()->ongoing()->make()->statusColor()]);
        $this->assertSame(['On hold', 'amber'], [Project::factory()->onHold()->make()->statusLabel(), Project::factory()->onHold()->make()->statusColor()]);
        $this->assertSame(['Completed', 'blue'], [Project::factory()->completed()->make()->statusLabel(), Project::factory()->completed()->make()->statusColor()]);
    }

    public function testScheduleProgressIsTheShareOfThePlannedDaysThatHavePassed(): void
    {
        $project = Project::factory()->make(['start_date' => '2026-01-01', 'end_date' => '2026-01-11']);

        $this->assertSame(0, $project->scheduleProgress(Carbon::parse('2025-12-25')));
        $this->assertSame(0, $project->scheduleProgress(Carbon::parse('2026-01-01')));
        $this->assertSame(30, $project->scheduleProgress(Carbon::parse('2026-01-04 15:00')));
        $this->assertSame(100, $project->scheduleProgress(Carbon::parse('2026-01-11')));
        $this->assertSame(100, $project->scheduleProgress(Carbon::parse('2026-03-01')));
    }

    public function testScheduleProgressIsNullWithoutBothDates(): void
    {
        $this->assertNull(Project::factory()->make(['start_date' => null])->scheduleProgress());
        $this->assertNull(Project::factory()->make(['end_date' => null])->scheduleProgress());
    }
}
