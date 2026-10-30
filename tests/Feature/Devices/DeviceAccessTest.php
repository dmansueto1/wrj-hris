<?php

namespace Tests\Feature\Devices;

use App\Livewire\Attendance\Clock;
use App\Livewire\Attendance\Kiosk;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Project;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\TestCase;

class DeviceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.require_approved_device' => true]);
    }

    public function testANewBrowserSeesTheApprovalScreenOnTheKioskAndNothingIsSaved(): void
    {
        $this->get(route('kiosk'))
            ->assertOk()
            ->assertSee('This device needs approval')
            ->assertSeeHtml('data-test="request-access-button"')
            ->assertDontSeeHtml('data-test="kiosk-continue-button"')
            ->assertDontSee("Today's attendance");

        $this->assertDatabaseCount('devices', 0);
    }

    public function testRequestAccessFromTheKioskSavesOnePendingDevice(): void
    {
        $component = Livewire::test(Kiosk::class)->call('requestAccess');

        $device = Device::query()->sole();
        $this->assertSame(Device::STATUS_PENDING, $device->status);
        $this->assertSame('kiosk', $device->requested_from);
        $this->assertNull($device->requested_by);

        $component->assertSee('Request sent')
            ->assertSeeHtml('data-test="device-code"')
            ->assertSee($device->code)
            ->assertSeeHtml('data-test="check-again-button"');

        $token = Cookie::queued(DeviceService::COOKIE)?->getValue();
        $this->assertIsString($token);
        $this->assertSame(hash('sha256', $token), $device->token_hash);

        // The same browser asking again gets the same request, not a second device.
        Cookie::unqueue(DeviceService::COOKIE);
        Livewire::withCookie(DeviceService::COOKIE, $token)
            ->test(Kiosk::class)
            ->assertSee('Request sent')
            ->assertSee($device->code)
            ->call('requestAccess');

        $this->assertDatabaseCount('devices', 1);
    }

    public function testRequestAccessFromTheClockPageRecordsWhoAsked(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->assertSee('This device needs approval')
            ->assertDontSeeHtml('data-test="clock-in-button"')
            ->assertDontSee('Today')
            ->call('requestAccess')
            ->assertSee('Request sent');

        $device = Device::query()->sole();
        $this->assertSame('clock', $device->requested_from);
        $this->assertSame($worker->id, $device->requested_by);
    }

    public function testTheSixthRequestFromOneNetworkWithinAnHourIsRefused(): void
    {
        foreach (range(1, 5) as $i) {
            Livewire::test(Kiosk::class)->call('requestAccess');
            Cookie::unqueue(DeviceService::COOKIE); // the next one is another browser
        }

        Livewire::test(Kiosk::class)
            ->call('requestAccess')
            ->assertHasErrors('device')
            ->assertSee('Too many requests from this network. Try again later.');

        $this->assertDatabaseCount('devices', 5);
    }

    public function testAnApprovedDeviceUsesTheKioskAndClockAndRecordsTheLastUser(): void
    {
        Device::factory()->approved()->withToken('approved-token')->create();
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        Livewire::withCookie(DeviceService::COOKIE, 'approved-token')
            ->test(Kiosk::class)
            ->assertSeeHtml('data-test="kiosk-continue-button"')
            ->assertDontSee('This device needs approval');

        $this->actingAs($worker);

        Livewire::withCookie(DeviceService::COOKIE, 'approved-token')
            ->test(Clock::class)
            ->assertSeeHtml('data-test="clock-in-button"')
            ->set('projectId', (string) $project->id)
            ->call('clockIn')
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame($worker->id, Device::query()->sole()->last_user_id);
    }

    public function testAPendingDeviceCannotSignInOrClockIn(): void
    {
        Device::factory()->pending()->withToken('pending-token')->create();
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        Livewire::withCookie(DeviceService::COOKIE, 'pending-token')
            ->test(Kiosk::class)
            ->set('username', $worker->username)
            ->set('password', 'password')
            ->call('verify')
            ->assertForbidden();

        $this->actingAs($worker);

        Livewire::withCookie(DeviceService::COOKIE, 'pending-token')
            ->test(Clock::class)
            ->set('projectId', (string) $project->id)
            ->call('clockIn')
            ->assertHasErrors(['clock' => 'This device is not approved for clock in/out.']);

        $this->assertSame(0, Attendance::query()->count());
    }

    public function testABlockedDeviceIsToldSoAndAnArchivedOneCanAskAgain(): void
    {
        $blocked = Device::factory()->blocked()->withToken('blocked-token')->create();

        Livewire::withCookie(DeviceService::COOKIE, 'blocked-token')
            ->test(Kiosk::class)
            ->assertSee('This device is blocked')
            ->assertSee($blocked->code)
            ->assertDontSeeHtml('data-test="request-access-button"');

        Device::factory()->approved()->withToken('archived-token')->create()->delete();

        Livewire::withCookie(DeviceService::COOKIE, 'archived-token')
            ->test(Kiosk::class)
            ->assertSee('This device needs approval')
            ->assertSeeHtml('data-test="request-access-button"');
    }

    public function testWithTheSettingOffBothPagesWorkAsBefore(): void
    {
        config(['attendance.require_approved_device' => false]);

        $this->get(route('kiosk'))
            ->assertSeeHtml('data-test="kiosk-continue-button"')
            ->assertDontSee('This device needs approval');

        $this->actingAs(User::factory()->worker()->create());
        Project::factory()->ongoing()->create();

        $this->get(route('clock'))
            ->assertSeeHtml('data-test="clock-in-button"')
            ->assertDontSee('This device needs approval');
    }

    public function testABlockedOrApprovedDeviceCannotRequestAgain(): void
    {
        $blocked = Device::factory()->blocked()->withToken('blocked-token')->create();
        $approved = Device::factory()->approved()->withToken('approved-token')->create();

        Livewire::withCookie(DeviceService::COOKIE, 'blocked-token')->test(Kiosk::class)->call('requestAccess');
        Livewire::withCookie(DeviceService::COOKIE, 'approved-token')->test(Kiosk::class)->call('requestAccess');

        $this->assertDatabaseCount('devices', 2);
        $this->assertSame(Device::STATUS_BLOCKED, $blocked->fresh()->status);
        $this->assertSame(Device::STATUS_APPROVED, $approved->fresh()->status);
        // The browsers keep their own tokens: no new token was handed out.
        $this->assertContains(Cookie::queued(DeviceService::COOKIE)?->getValue(), ['blocked-token', 'approved-token']);
    }

    public function testASpoofedForwardedForIsNotTrustedOutsideLocal(): void
    {
        // The request limit and devices.ip_address use the client IP. Outside local (here: testing),
        // an X-Forwarded-For sent straight to the app must not change it.
        $device = Device::factory()->approved()->withToken('approved-token')->create([
            'ip_address' => '203.0.113.10',
            'last_seen_at' => now()->subMinutes(10),
        ]);

        $this->withCookie(DeviceService::COOKIE, 'approved-token')
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->get(route('kiosk'))
            ->assertOk();

        $this->assertSame('127.0.0.1', $device->fresh()->ip_address);
    }

    public function testAPendingDeviceCannotClockOutOrUseTheKioskClockActions(): void
    {
        Device::factory()->pending()->withToken('pending-token')->create();
        $worker = User::factory()->worker()->create();
        $open = Attendance::factory()->for($worker)->open()->create();

        Livewire::withCookie(DeviceService::COOKIE, 'pending-token')->test(Kiosk::class)->call('clockIn')->assertForbidden();
        Livewire::withCookie(DeviceService::COOKIE, 'pending-token')->test(Kiosk::class)->call('clockOut')->assertForbidden();

        $this->actingAs($worker);
        Livewire::withCookie(DeviceService::COOKIE, 'pending-token')
            ->test(Clock::class)
            ->call('clockOut')
            ->assertHasErrors(['clock' => 'This device is not approved for clock in/out.']);

        $this->assertNull($open->fresh()->clock_out_at);
    }
}
