<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\Clock;
use App\Livewire\Attendance\Kiosk;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendancePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['attendance.require_photo' => true]);
    }

    public function testClockInAndOutOnTheClockScreenSaveBothPhotos(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->set('photo', $this->photo())
            ->call('clockIn')
            ->assertHasNoErrors()
            ->assertSet('photo', null);

        $duty = Attendance::sole();
        $this->assertSame(Attendance::STATUS_OPEN, $duty->status);
        $this->assertNotNull($duty->clock_in_photo_path);
        $this->assertStringStartsWith('attendance-photos/', $duty->clock_in_photo_path);
        Storage::disk('local')->assertExists($duty->clock_in_photo_path);

        Livewire::test(Clock::class)
            ->set('photo', $this->photo())
            ->call('clockOut')
            ->assertHasNoErrors();

        $duty->refresh();
        $this->assertSame(Attendance::STATUS_CLOSED, $duty->status);
        $this->assertNotNull($duty->clock_out_photo_path);
        Storage::disk('local')->assertExists($duty->clock_out_photo_path);
    }

    public function testTheCameraStepShowsOnlyWhenAPhotoIsRequired(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        Project::factory()->ongoing()->create();

        $this->get(route('clock'))
            ->assertOk()
            ->assertSeeHtml('data-test="camera-capture"')
            ->assertSeeHtml('data-test="open-camera-button"')
            ->assertSee('Open camera');

        config(['attendance.require_photo' => false]);
        $this->get(route('clock'))->assertOk()->assertDontSeeHtml('data-test="camera-capture"');
    }

    public function testTheCameraIsInAModalThatOnlyTheCloseButtonCanClose(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        Project::factory()->ongoing()->create();

        $html = $this->get(route('clock'))->assertOk()->getContent();

        // The camera sits inside the clock-in modal, not on the page itself.
        $this->assertMatchesRegularExpression('/data-test="clock-photo-modal-clock-in-photo".*data-test="camera-capture"/s', $html);

        // No closing by clicking outside or pressing Esc; only the Close button.
        preg_match('/<ui-modal[^>]*>\s*(?:<[^>]*>\s*)*?<dialog[^>]*data-modal="clock-in-photo"/s', $html, $modal);
        $this->assertNotEmpty($modal, 'The clock-in photo modal should be on the page.');
        $this->assertStringContainsString('disable-click-outside', $modal[0]);
        $this->assertStringContainsString('disable-escape', $modal[0]);
        $this->assertStringContainsString('data-test="close-clock-in-photo"', $html);
    }

    public function testANoteBeforeTheButtonSaysTheCameraIsNext(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        Project::factory()->ongoing()->create();

        $this->get(route('clock'))
            ->assertSeeHtml('data-test="photo-step-note"')
            ->assertSee('Camera required.')
            ->assertSee('After you press Clock In, take a photo of yourself to confirm.');

        app(AttendanceService::class)->clockIn($worker, Project::factory()->ongoing()->create());

        $this->get(route('clock'))->assertSee('After you press Clock Out, take a photo of yourself to confirm.');

        config(['attendance.require_photo' => false]);
        $this->get(route('clock'))->assertDontSeeHtml('data-test="photo-step-note"');
    }

    public function testTheKioskShowsTheCameraNoteBeforeClockIn(): void
    {
        $worker = User::factory()->worker()->create();
        Project::factory()->ongoing()->create();

        $this->kiosk($worker)->assertSee('After you press Clock In, take a photo of yourself to confirm.');
    }

    public function testTheKioskUsesPhotoModalsForClockInAndOut(): void
    {
        $worker = User::factory()->worker()->create();
        Project::factory()->ongoing()->create();

        $this->kiosk($worker)
            ->assertSeeHtml('data-test="clock-photo-modal-kiosk-photo-in"')
            ->assertSeeHtml('data-test="close-kiosk-photo-in"');

        app(AttendanceService::class)->clockIn($worker, Project::factory()->ongoing()->create());

        $this->kiosk($worker)
            ->assertSeeHtml('data-test="clock-photo-modal-kiosk-photo-out"')
            ->assertSeeHtml('data-test="close-kiosk-photo-out"');
    }

    public function testClockInWithoutAPhotoWhileRequiredIsRefused(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->call('clockIn')
            ->assertHasErrors(['photo' => 'required']);

        $this->assertSame(0, Attendance::count());
    }

    public function testClockOutWithoutAPhotoWhileRequiredIsRefused(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        app(AttendanceService::class)->clockIn($worker, Project::factory()->ongoing()->create());

        Livewire::test(Clock::class)
            ->call('clockOut')
            ->assertHasErrors(['photo' => 'required']);

        $this->assertSame(Attendance::STATUS_OPEN, Attendance::sole()->status);
    }

    public function testTheKioskSavesBothPhotos(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->kiosk($worker)
            ->set('projectId', $project->id)
            ->set('photo', $this->photo())
            ->call('clockIn')
            ->assertHasNoErrors()
            ->assertSet('photo', null);

        $this->kiosk($worker)
            ->set('photo', $this->photo())
            ->call('clockOut')
            ->assertHasNoErrors();

        $duty = Attendance::sole();
        $this->assertSame(Attendance::STATUS_CLOSED, $duty->status);
        Storage::disk('local')->assertExists($duty->clock_in_photo_path);
        Storage::disk('local')->assertExists($duty->clock_out_photo_path);
    }

    public function testTheKioskRefusesAClockInWithoutAPhoto(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->kiosk($worker)
            ->set('projectId', $project->id)
            ->call('clockIn')
            ->assertHasErrors(['photo' => 'required']);

        $this->assertSame(0, Attendance::count());
    }

    public function testANonImageUploadIsRejected(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->set('photo', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))
            ->call('clockIn')
            ->assertHasErrors('photo');

        $this->assertSame(0, Attendance::count());
    }

    public function testAnImageOverTwoMegabytesIsRejected(): void
    {
        $this->actingAs(User::factory()->worker()->create());
        $project = Project::factory()->ongoing()->create();

        Livewire::test(Clock::class)
            ->set('projectId', $project->id)
            ->set('photo', UploadedFile::fake()->image('big.jpg')->size(3000))
            ->call('clockIn')
            ->assertHasErrors(['photo' => 'max']);

        $this->assertSame(0, Attendance::count());
    }

    public function testWithoutTheRequirementBothScreensWorkWithoutAPhoto(): void
    {
        config(['attendance.require_photo' => false]);
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->ongoing()->create();

        $this->actingAs($worker);
        Livewire::test(Clock::class)->set('projectId', $project->id)->call('clockIn')->assertHasNoErrors();
        Livewire::test(Clock::class)->call('clockOut')->assertHasNoErrors();
        auth()->logout();

        $this->kiosk($worker)->set('projectId', $project->id)->call('clockIn')->assertHasNoErrors();
        $this->kiosk($worker)->call('clockOut')->assertHasNoErrors();

        $this->assertSame(2, Attendance::where('status', Attendance::STATUS_CLOSED)->count());
        $this->assertSame(0, Attendance::whereNotNull('clock_in_photo_path')->count());
    }

    public function testAPhotoIsNotKeptWhenTheClockInIsRejected(): void
    {
        $worker = User::factory()->worker()->create();
        $this->actingAs($worker);
        $project = Project::factory()->ongoing()->create();
        app(AttendanceService::class)->clockIn($worker, $project);

        $screen = Livewire::test(Clock::class);
        $screen->set('projectId', $project->id)->set('photo', $this->photo())->call('clockIn')->assertHasErrors('clock');

        $this->assertSame([], Storage::disk('local')->allFiles('attendance-photos'));
    }

    public function testTheServiceStillWorksWithoutAPhoto(): void
    {
        $worker = User::factory()->worker()->create();

        $duty = app(AttendanceService::class)->clockIn($worker, Project::factory()->ongoing()->create());

        $this->assertNull($duty->clock_in_photo_path);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function viewers(): array
    {
        return [
            'admin' => ['admin', 200],
            'staff' => ['staff', 200],
            'worker' => ['worker', 403],
        ];
    }

    #[DataProvider('viewers')]
    public function testOnlyAdminAndStaffCanViewAPhoto(string $role, int $status): void
    {
        $duty = $this->dutyWithPhoto();

        $response = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('attendance.photo', [$duty, 'in']));

        $response->assertStatus($status);

        if ($status === 200) {
            $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        }
    }

    public function testGuestsAreSentToLoginForPhotos(): void
    {
        $this->get(route('attendance.photo', [$this->dutyWithPhoto(), 'in']))->assertRedirect(route('login'));
    }

    public function testAMissingPhotoOrAnUnknownTypeIs404(): void
    {
        $admin = User::factory()->admin()->create();
        $duty = $this->dutyWithPhoto();

        $this->actingAs($admin)->get(route('attendance.photo', [$duty, 'out']))->assertNotFound();
        $this->actingAs($admin)->get('/attendance/'.$duty->id.'/photo/side')->assertNotFound();

        Storage::disk('local')->delete($duty->clock_in_photo_path);
        $this->actingAs($admin)->get(route('attendance.photo', [$duty, 'in']))->assertNotFound();
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('me.jpg', 720, 960);
    }

    private function kiosk(User $worker): Testable
    {
        return Livewire::test(Kiosk::class)
            ->set('username', $worker->username)
            ->set('password', 'password')
            ->call('verify');
    }

    private function dutyWithPhoto(): Attendance
    {
        Storage::disk('local')->put('attendance-photos/2026/03/1-in-test.jpg', UploadedFile::fake()->image('x.jpg')->getContent());

        return Attendance::factory()->open()->create(['clock_in_photo_path' => 'attendance-photos/2026/03/1-in-test.jpg']);
    }
}
