<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clock in/out photos (T-10.2). Stored on the private `local` disk (storage/app/private) and only
 * served through the permission-checked attendance.photo route. Never create temporaryUrl() links.
 */
class AttendancePhotos
{
    public const DISK = 'local';

    /**
     * Whether a photo is required to clock in or out (System Settings, T-14.1).
     */
    public static function required(): bool
    {
        return Settings::bool('attendance_require_photo');
    }

    /**
     * Validation rules for the uploaded photo: a JPEG or PNG of at most 2 MB.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return [self::required() ? 'required' : 'nullable', 'image', 'mimes:jpeg,png', 'max:2048'];
    }

    /**
     * Save the photo as attendance-photos/{Y}/{m}/{user_id}-{in|out}-{uuid}.{jpg|png} and return the path.
     */
    public static function store(UploadedFile $photo, User $user, string $type): string
    {
        $extension = $photo->extension() === 'png' ? 'png' : 'jpg';
        $name = $user->id.'-'.$type.'-'.Str::uuid().'.'.$extension;

        return (string) $photo->storeAs('attendance-photos/'.now()->format('Y/m'), $name, self::DISK);
    }

    /**
     * Remove a stored photo, e.g. when the clock in/out it belonged to was rejected.
     */
    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
