<?php

namespace Tests\Unit;

use App\Support\AttendancePhotos;
use Tests\TestCase;

class AttendancePhotosTest extends TestCase
{
    public function testThePhotoIsRequiredWhenTheSettingIsOn(): void
    {
        config(['attendance.require_photo' => true]);

        $this->assertTrue(AttendancePhotos::required());
        $this->assertSame('required', AttendancePhotos::rules()[0]);
    }

    public function testThePhotoIsOptionalWhenTheSettingIsOff(): void
    {
        config(['attendance.require_photo' => false]);

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
