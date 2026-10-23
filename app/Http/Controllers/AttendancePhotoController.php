<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Support\AttendancePhotos;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a clock in/out photo from the private disk (T-10.2). Route: attendance.photo,
 * protected by auth + permission:attendance.view-all (Admin and Staff).
 */
class AttendancePhotoController extends Controller
{
    public function __invoke(Attendance $attendance, string $type): StreamedResponse
    {
        $path = $type === 'in' ? $attendance->clock_in_photo_path : $attendance->clock_out_photo_path;
        $disk = Storage::disk(AttendancePhotos::DISK);

        abort_if($path === null || $path === '' || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
