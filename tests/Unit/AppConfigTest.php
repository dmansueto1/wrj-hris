<?php

use Tests\TestCase;

uses(TestCase::class);

test('app timezone is Asia/Manila', function () {
    expect(config('app.timezone'))->toBe('Asia/Manila');
});
