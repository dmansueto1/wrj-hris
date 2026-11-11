<?php

namespace Tests\Feature\SystemSettings;

use App\Livewire\SystemSettings\Index;
use App\Models\User;
use App\Services\SystemSettingsService;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SystemSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The page shows the real saved values, not the test defaults.
        Settings::clearFake();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin', 200], 'staff' => ['staff', 403], 'worker' => ['worker', 403]];
    }

    #[DataProvider('roles')]
    public function testOnlyAdminCanOpenSystemSettings(string $role, int $status): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('system-settings.index'))
            ->assertStatus($status);
    }

    public function testAdminIsAskedForTheirPasswordFirst(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('system-settings.index'))
            ->assertRedirect(route('password.confirm'));
    }

    public function testAGuestIsSentToLogin(): void
    {
        $this->get(route('system-settings.index'))->assertRedirect(route('login'));
    }

    public function testTheMenuShowsSystemSettingsOnlyToAdmin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('clock'))->assertSee('System Settings');
        $this->actingAs(User::factory()->staff()->create())->get(route('clock'))->assertDontSee('System Settings');
    }

    public function testAFreshDatabaseShowsEveryDefault(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->assertSet('values', [
                'attendance_require_photo' => true,
                'attendance_require_approved_device' => true,
                'attendance_flag_after_hours' => '12',
                'kiosk_verified_seconds' => '120',
                'material_markup_rate' => '20',
                'idle_logout_minutes' => '30',
                'login_max_attempts' => '5',
                'login_lockout_minutes' => '15',
                'device_prune_pending_days' => '30',
            ])
            ->assertSee('System Settings')
            ->assertSee('Attendance')
            ->assertSee('Materials')
            ->assertSee('Security')
            ->assertSee('Devices')
            ->assertSee('Default: 12 hours')
            ->assertSee('Default: 20 %');
    }

    public function testSavingChangesTheSettings(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->set('values.attendance_flag_after_hours', '8')
            ->set('values.material_markup_rate', '25')
            ->set('values.attendance_require_photo', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('values.attendance_flag_after_hours', '8');

        $this->assertSame(8, Settings::int('attendance_flag_after_hours'));
        $this->assertSame('0.2500', Settings::string('material_markup_rate'));
        $this->assertFalse(Settings::bool('attendance_require_photo'));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'zero hours' => ['attendance_flag_after_hours', '0'],
            '25 hours' => ['attendance_flag_after_hours', '25'],
            '29 seconds' => ['kiosk_verified_seconds', '29'],
            '601 seconds' => ['kiosk_verified_seconds', '601'],
            'negative percent' => ['material_markup_rate', '-1'],
            '101 percent' => ['material_markup_rate', '101'],
            'three-decimal percent' => ['material_markup_rate', '12.345'],
            '2 attempts' => ['login_max_attempts', '2'],
            '11 attempts' => ['login_max_attempts', '11'],
            'text' => ['idle_logout_minutes', 'abc'],
            'decimal minutes' => ['idle_logout_minutes', '10.5'],
            'not a yes or no' => ['attendance_require_photo', 'maybe'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testOutOfRangeValuesAreRejectedAndNothingIsSaved(string $key, mixed $value): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->set('values.idle_logout_minutes', '45')
            ->set('values.'.$key, $value)
            ->call('save')
            ->assertHasErrors('values.'.$key);

        $this->assertSame(30, Settings::int('idle_logout_minutes'));
        $this->assertSame(0, Activity::query()->count());
    }

    public function testRangeErrorsUseSimpleWording(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->set('values.attendance_flag_after_hours', '25')
            ->set('values.idle_logout_minutes', 'abc')
            ->set('values.material_markup_rate', '101')
            ->call('save')
            ->assertSee('Enter a number from 1 to 24.')
            ->assertSee('Enter a whole number.')
            ->assertSee('Enter a percent from 0 to 100.');
    }

    public function testAnUnknownKeyIsRejectedByTheService(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            app(SystemSettingsService::class)->update(['attendance_flag_after_hours' => '8', 'app_debug' => '1'], $admin);
            $this->fail('An unknown key was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('values.app_debug', $e->errors());
        }

        $this->assertSame(12, Settings::int('attendance_flag_after_hours'));
    }

    public function testOneSaveWritesOneActivityWithOnlyTheChangedKeys(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('values.attendance_flag_after_hours', '8')
            ->set('values.login_max_attempts', '3')
            ->call('save');

        $activity = Activity::query()->where('log_name', 'settings')->sole();
        $this->assertSame('Changed settings', $activity->description);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertEquals(['attendance_flag_after_hours' => '12', 'login_max_attempts' => '5'], $activity->properties['old']);
        $this->assertEquals(['attendance_flag_after_hours' => '8', 'login_max_attempts' => '3'], $activity->properties['new']);
    }

    public function testASaveWithNoChangeWritesNothing(): void
    {
        $admin = User::factory()->admin()->create();

        app(SystemSettingsService::class)->update(app(SystemSettingsService::class)->values(), $admin);

        $this->assertSame(0, Activity::query()->count());
    }

    public function testRestoreDefaultsPutsEverySettingBack(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        app(SystemSettingsService::class)->update([
            'attendance_require_photo' => false,
            'attendance_flag_after_hours' => '8',
            'material_markup_rate' => '25',
            'login_lockout_minutes' => '10',
        ], $admin);

        Livewire::test(Index::class)
            ->call('restoreDefaults')
            ->assertHasNoErrors()
            ->assertSet('values.attendance_flag_after_hours', '12')
            ->assertSet('values.material_markup_rate', '20');

        $this->assertTrue(Settings::bool('attendance_require_photo'));
        $this->assertSame(12, Settings::int('attendance_flag_after_hours'));
        $this->assertSame('0.2000', Settings::string('material_markup_rate'));
        $this->assertSame(15, Settings::int('login_lockout_minutes'));
    }

    public function testSaveIsDisabledUntilSomethingChangesAndDiscardUndoesIt(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(Index::class)
            ->assertSeeHtml('disabled="disabled" data-test="save-settings"')
            ->assertDontSee('You have unsaved changes');
        $this->assertFalse($component->instance()->hasChanges);

        $component->set('values.kiosk_verified_seconds', '60')
            ->assertDontSeeHtml('disabled="disabled" data-test="save-settings"')
            ->assertSee('You have unsaved changes');
        $this->assertTrue($component->instance()->hasChanges);

        $component->call('discard')->assertSet('values.kiosk_verified_seconds', '120');
        $this->assertFalse($component->instance()->hasChanges);
        $this->assertSame(120, Settings::int('kiosk_verified_seconds'));
    }

    public function testStaffCannotSaveThroughTheComponentOrTheService(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);

        Livewire::test(Index::class)->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(SystemSettingsService::class)->update(['attendance_flag_after_hours' => '8'], $staff);
    }
}
