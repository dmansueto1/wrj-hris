<?php

namespace Tests\Feature\Devices;

use App\Livewire\Devices\Index;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DevicesPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, int}>
     */
    public static function roles(): array
    {
        return ['admin' => ['admin', 200], 'staff' => ['staff', 403], 'worker' => ['worker', 403]];
    }

    #[DataProvider('roles')]
    public function testOnlyAdminCanOpenDevices(string $role, int $status): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('devices.index'))
            ->assertStatus($status);
    }

    public function testAGuestIsSentToTheLoginPage(): void
    {
        $this->get(route('devices.index'))->assertRedirect(route('login'));
    }

    public function testStaffCannotUseTheComponent(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        Livewire::test(Index::class)->assertForbidden();
    }

    public function testTheListShowsPendingRequestsFirstWithWhereTheyCameFrom(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $worker = User::factory()->worker()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Device::factory()->approved()->create(['name' => 'Site A tablet', 'last_seen_at' => now()]);
        $pending = Device::factory()->pending()->create([
            'requested_from' => 'clock',
            'requested_by' => $worker->id,
            'browser' => 'Chrome',
            'platform' => 'Android',
            'last_seen_at' => now()->subDay(),
        ]);

        Livewire::test(Index::class)
            ->assertSeeInOrder([$pending->code, 'Site A tablet'])
            ->assertSee('Clock In/Out')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Chrome on Android')
            ->assertSee('Pending')
            ->assertSee('Approved');
    }

    public function testTheTypeColumnShowsPhoneTabletOrComputer(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $phone = Device::factory()->create(['user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1']);
        $laptop = Device::factory()->create(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36']);
        $tablet = Device::factory()->create(); // the factory's Android tablet

        $html = Livewire::test(Index::class)->assertSee('Type')->html();

        foreach ([[$phone, 'Phone'], [$laptop, 'Computer'], [$tablet, 'Tablet']] as [$device, $label]) {
            $this->assertMatchesRegularExpression(
                '/data-test="device-type-'.$device->id.'"[\s\S]*?'.$label.'/',
                $html,
            );
        }
    }

    public function testApprovingADeviceNamesItAndIsLogged(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $device = Device::factory()->pending()->create();

        Livewire::test(Index::class)
            ->call('openApprove', $device->id)
            ->set('deviceName', '')
            ->call('saveName')
            ->assertHasErrors('deviceName')
            ->set('deviceName', 'Site A tablet')
            ->call('saveName')
            ->assertHasNoErrors();

        $device->refresh();
        $this->assertSame(Device::STATUS_APPROVED, $device->status);
        $this->assertSame('Site A tablet', $device->name);
        $this->assertSame($admin->id, $device->approved_by);
        $this->assertNotNull($device->approved_at);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'devices',
            'description' => 'Approved device',
            'subject_id' => $device->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function testRenameBlockAndArchiveChangeTheDeviceAndAreLogged(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $device = Device::factory()->approved()->create(['name' => 'Old name']);

        Livewire::test(Index::class)
            ->call('openRename', $device->id)
            ->set('deviceName', 'Site B tablet')
            ->call('saveName')
            ->call('block', $device->id);

        $device->refresh();
        $this->assertSame('Site B tablet', $device->name);
        $this->assertSame(Device::STATUS_BLOCKED, $device->status);

        Livewire::test(Index::class)->call('archive', $device->id);
        $this->assertSoftDeleted($device);

        foreach (['Renamed device', 'Blocked device', 'Archived device'] as $description) {
            $this->assertDatabaseHas('activity_log', [
                'log_name' => 'devices',
                'description' => $description,
                'subject_id' => $device->id,
                'causer_id' => $admin->id,
            ]);
        }
    }

    public function testTheAdminsOwnBrowserIsMarkedThisDevice(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Device::factory()->approved()->withToken('admin-token')->create(['name' => 'Office PC']);

        Livewire::withCookie(DeviceService::COOKIE, 'admin-token')
            ->test(Index::class)
            ->assertSee('This device');
    }

    public function testPruningArchivesOnlyOldPendingRequests(): void
    {
        $old = Device::factory()->pending()->create(['last_seen_at' => now()->subDays(31)]);
        $recent = Device::factory()->pending()->create(['last_seen_at' => now()->subDays(29)]);
        $approved = Device::factory()->approved()->create(['last_seen_at' => now()->subDays(90)]);

        $this->artisan('devices:prune-pending')->assertSuccessful();

        $this->assertSoftDeleted($old);
        $this->assertNotSoftDeleted($recent);
        $this->assertNotSoftDeleted($approved);
    }
}
