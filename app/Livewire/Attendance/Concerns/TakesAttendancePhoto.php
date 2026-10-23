<?php

namespace App\Livewire\Attendance\Concerns;

use App\Exceptions\Attendance\AttendanceException;
use App\Models\Attendance;
use App\Models\User;
use App\Support\AttendancePhotos;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Photo step for the clock screens (T-10.2). The camera component uploads the snapshot into $photo;
 * the clock action validates it, stores it privately and hands the path to AttendanceService.
 */
trait TakesAttendancePhoto
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    /**
     * @return array{photo: list<string>}
     */
    protected function photoRules(): array
    {
        return ['photo' => AttendancePhotos::rules()];
    }

    /**
     * @return array<string, string>
     */
    protected function photoMessages(): array
    {
        return [
            'photo.required' => __('Take a photo of yourself first.'),
            'photo.image' => __('The photo must be a picture from the camera.'),
            'photo.mimes' => __('The photo must be a JPEG or PNG picture.'),
            'photo.max' => __('The photo is too large. Please take it again.'),
        ];
    }

    /**
     * Store the photo (if any), run the clock action with its path and return the saved duty.
     * The photo is removed again when the action is rejected, so no orphan photos are kept.
     *
     * @param  callable(?string): Attendance  $action
     */
    protected function withStoredPhoto(User $user, string $type, callable $action): Attendance
    {
        $path = $this->photo instanceof TemporaryUploadedFile
            ? AttendancePhotos::store($this->photo, $user, $type)
            : null;

        try {
            return $action($path);
        } catch (AttendanceException $e) {
            AttendancePhotos::delete($path);

            throw $e;
        }
    }
}
