<?php

namespace Tests\Unit;

use App\Support\AttendancePhotos;
use App\Support\Settings;
use Tests\TestCase;

class AttendancePhotosTest extends TestCase
{
    public function testThePhotoIsRequiredWhenTheSettingIsOn(): void
    {
        Settings::fake(['attendance_require_photo' => '1']);

        $this->assertTrue(AttendancePhotos::required());
        $this->assertSame('required', AttendancePhotos::rules()[0]);
    }

    public function testThePhotoIsOptionalWhenTheSettingIsOff(): void
    {
        Settings::fake(['attendance_require_photo' => '0']);

        $this->assertFalse(AttendancePhotos::required());
        $this->assertSame('nullable', AttendancePhotos::rules()[0]);
    }

    public function testThePhotoMustBeAJpegOrPngOfAtMostTwoMegabytes(): void
    {
        $rules = AttendancePhotos::rules();

        $this->assertContains('image', $rules);
        $this->assertContains('mimes:jpeg,png', $rules);
        $this->assertContains('max:2048', $rules);
    }
}
