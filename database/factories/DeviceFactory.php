<?php

namespace Database\Factories;

use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * A pending request from a Chrome tablet at the kiosk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', Str::random(40)),
            'code' => DeviceService::newCode(),
            'name' => null,
            'status' => Device::STATUS_PENDING,
            'requested_from' => 'kiosk',
            'requested_by' => null,
            'requested_at' => now(),
            'last_seen_at' => now(),
            'browser' => 'Chrome',
            'platform' => 'Android',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'ip_address' => '203.0.113.10',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Device::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Device::STATUS_APPROVED,
            'name' => 'Site tablet',
            'approved_at' => now(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Device::STATUS_BLOCKED]);
    }

    /**
     * The device a browser holding this cookie token belongs to.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => ['token_hash' => hash('sha256', $token)]);
    }
}
