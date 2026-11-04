<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\ShiftSchedule;
use App\Models\User;
use App\Models\UserShift;
use App\Services\UserShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function testNewUsersAreFlexibleWithNoSchedule(): void
    {
        $worker = User::factory()->worker()->create();

        $this->assertSame(User::SHIFT_FLEXIBLE, $worker->fresh()->shift_type);
        $this->assertSame(0, UserShift::query()->count());
        $this->assertNull(app(UserShiftService::class)->windowsFor($worker, Carbon::parse('2026-10-19')));

        Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->assertSet('type', User::SHIFT_FLEXIBLE)
            ->assertSee('Hours count exactly as clocked in and out.')
            ->assertDontSeeHtml('data-test="rest-day-1"');
    }

    public function testSwitchingToTwoShiftsFillsTheDefaultWeekWithSundayAsRestDay(): void
    {
        $worker = User::factory()->worker()->create();

        $component = Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->set('type', User::SHIFT_TWO)
            ->assertSee('Morning In')
            ->assertSee('Afternoon Out')
            ->assertSeeHtml('data-test="rest-day-7"')
            ->assertSee('Working days: Mon–Sat · Rest day: Sun');

        foreach (range(1, 6) as $weekday) {
            $component->assertSet("days.{$weekday}.is_rest_day", false)
                ->assertSet("days.{$weekday}.first_in", '08:00')
                ->assertSet("days.{$weekday}.first_out", '12:00')
                ->assertSet("days.{$weekday}.second_in", '13:00')
                ->assertSet("days.{$weekday}.second_out", '17:00');
        }
        $component->assertSet('days.7.is_rest_day', true);
    }

    public function testSavingTwoShiftsWritesSevenRowsAndASecondSaveUpdatesThem(): void
    {
        $worker = User::factory()->worker()->create();

        $component = Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->set('type', User::SHIFT_TWO)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(User::SHIFT_TWO, $worker->fresh()->shift_type);
        $this->assertSame(7, $worker->shifts()->count());
        $monday = $worker->shifts()->where('weekday', 1)->sole();
        $this->assertSame(['08:00', '12:00', '13:00', '17:00'], [$monday->first_in, $monday->first_out, $monday->second_in, $monday->second_out]);
        $sunday = $worker->shifts()->where('weekday', 7)->sole();
        $this->assertTrue($sunday->is_rest_day);
        $this->assertNull($sunday->first_in);

        $component->set('days.1.first_in', '07:30')->call('save')->assertHasNoErrors();

        $this->assertSame(7, $worker->shifts()->count());
        $this->assertSame('07:30', $worker->shifts()->where('weekday', 1)->sole()->first_in);

        // The saved schedule shows again after a reload.
        Livewire::test(ShiftSchedule::class, ['user' => $worker->fresh()])
            ->assertSet('type', User::SHIFT_TWO)
            ->assertSet('days.1.first_in', '07:30')
            ->assertSet('days.7.is_rest_day', true);
    }

    public function testRestDaysSaveWithoutTimesAndUntickingKeepsTheTypedTimes(): void
    {
        $worker = User::factory()->worker()->create();

        $component = Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->set('type', User::SHIFT_TWO)
            // Saturday still has its times when ticked; they are not validated and are not kept.
            ->set('days.6.is_rest_day', true)
            ->set('days.6.second_out', '')
            ->assertSee('Working days: Mon–Fri · Rest days: Sat, Sun')
            ->call('save')
            ->assertHasNoErrors();

        foreach ([6, 7] as $weekday) {
            $day = $worker->shifts()->where('weekday', $weekday)->sole();
            $this->assertTrue($day->is_rest_day);
            $this->assertSame([null, null, null, null], [$day->first_in, $day->first_out, $day->second_in, $day->second_out]);
        }

        // Unticking brings the time inputs back with what was typed.
        Livewire::test(ShiftSchedule::class, ['user' => $worker->fresh()])
            ->set('days.5.is_rest_day', true)
            ->assertSet('days.5.first_in', '08:00')
            ->set('days.5.is_rest_day', false)
            ->assertSet('days.5.first_in', '08:00')
            ->assertSeeHtml('wire:model="days.5.first_in"');
    }

    public function testCopyMondayFillsTheOtherWorkingDaysAndLeavesRestDaysAlone(): void
    {
        $worker = User::factory()->worker()->create();

        $component = Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->set('type', User::SHIFT_TWO)
            ->set('days.1.first_in', '07:00')
            ->set('days.1.first_out', '11:00')
            ->set('days.1.second_in', '12:00')
            ->set('days.1.second_out', '16:00')
            ->call('copyMonday');

        foreach (range(2, 6) as $weekday) {
            $component->assertSet("days.{$weekday}.first_in", '07:00')
                ->assertSet("days.{$weekday}.second_out", '16:00');
        }
        $component->assertSet('days.7.is_rest_day', true)->assertSet('days.7.first_in', '');
    }

    public function testSwitchingFromTwoToOneClearsTheAfternoonAndFlexibleDeletesTheRows(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();

        Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->assertSet('type', User::SHIFT_TWO)
            ->set('type', User::SHIFT_ONE)
            ->assertSee('In')
            ->assertDontSee('Afternoon In')
            ->set('days.1.first_out', '17:00')
            ->call('save')
            ->assertHasNoErrors();

        $monday = $worker->shifts()->where('weekday', 1)->sole();
        $this->assertSame(['08:00', '17:00', null, null], [$monday->first_in, $monday->first_out, $monday->second_in, $monday->second_out]);
        $this->assertSame(User::SHIFT_ONE, $worker->fresh()->shift_type);

        Livewire::test(ShiftSchedule::class, ['user' => $worker->fresh()])
            ->set('type', User::SHIFT_FLEXIBLE)
            ->assertDontSeeHtml('data-test="rest-day-1"')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(User::SHIFT_FLEXIBLE, $worker->fresh()->shift_type);
        $this->assertSame(0, $worker->shifts()->count());
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidSchedules(): array
    {
        return [
            'morning out before morning in' => [User::SHIFT_TWO, ['days.1.first_in' => '12:00', 'days.1.first_out' => '08:00'], 'days.1.first_out'],
            'morning out after afternoon in' => [User::SHIFT_TWO, ['days.1.first_out' => '13:30', 'days.1.second_in' => '13:00'], 'days.1.second_in'],
            'empty afternoon out on a working day' => [User::SHIFT_TWO, ['days.2.second_out' => ''], 'days.2.second_out'],
            'every day a rest day' => [User::SHIFT_ONE, [
                'days.1.is_rest_day' => true, 'days.2.is_rest_day' => true, 'days.3.is_rest_day' => true,
                'days.4.is_rest_day' => true, 'days.5.is_rest_day' => true, 'days.6.is_rest_day' => true,
                'days.7.is_rest_day' => true,
            ], 'days'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('invalidSchedules')]
    public function testInvalidSchedulesShowAnErrorAndSaveNothing(string $type, array $changes, string $errorKey): void
    {
        $worker = User::factory()->worker()->create();

        $component = Livewire::test(ShiftSchedule::class, ['user' => $worker])->set('type', $type);
        foreach ($changes as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('save')->assertHasErrors($errorKey);

        $this->assertSame(User::SHIFT_FLEXIBLE, $worker->fresh()->shift_type);
        $this->assertSame(0, $worker->shifts()->count());
    }

    public function testOneShiftNeedsDifferentInAndOutTimes(): void
    {
        $worker = User::factory()->worker()->create();

        Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->set('type', User::SHIFT_ONE)
            ->set('days.1.first_out', '08:00')
            ->call('save')
            ->assertHasErrors('days.1.first_out');
    }

    public function testSavingIsWrittenToTheActivityLog(): void
    {
        $worker = User::factory()->worker()->create();

        Livewire::test(ShiftSchedule::class, ['user' => $worker])
            ->set('type', User::SHIFT_TWO)
            ->call('save');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'shifts',
            'description' => 'Changed shift',
            'subject_type' => User::class,
            'subject_id' => $worker->id,
            'causer_id' => $this->admin->id,
        ]);
        $activity = \DB::table('activity_log')->where('log_name', 'shifts')->sole();
        $properties = json_decode($activity->properties, true);
        $this->assertSame(User::SHIFT_FLEXIBLE, $properties['old']['type']);
        $this->assertSame(User::SHIFT_TWO, $properties['new']['type']);
        $this->assertSame('08:00', $properties['new']['days'][1]['first_in']);
    }

    public function testTheFactoryShiftStatesMatchTheTicket(): void
    {
        $worker = User::factory()->worker()->twoShifts()->create();
        $windows = app(UserShiftService::class)->windowsFor($worker, Carbon::parse('2026-10-19'));

        $this->assertSame('2026-10-19 13:00', $windows[1][0]->format('Y-m-d H:i'));
        $this->assertSame([], app(UserShiftService::class)->windowsFor($worker, Carbon::parse('2026-10-25')));

        $nightWorker = User::factory()->worker()->oneShift('22:00', '06:00')->create();
        $night = app(UserShiftService::class)->windowsFor($nightWorker, Carbon::parse('2026-10-19'));
        $this->assertSame('2026-10-20 06:00', $night[0][1]->format('Y-m-d H:i'));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin', 200], 'staff' => ['staff', 403], 'worker' => ['worker', 403]];
    }

    #[DataProvider('roles')]
    public function testOnlyAdminSeesTheShiftCard(string $role, int $status): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs(User::factory()->{$role}()->create());

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('users.edit', $worker))
            ->assertStatus($status);

        if ($status === 200) {
            $response->assertSeeHtml('data-test="shift-card"')->assertSee('Shift type');
        }
    }

    public function testStaffCannotUseTheShiftComponent(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(ShiftSchedule::class, ['user' => $worker])->assertForbidden();
    }
}
