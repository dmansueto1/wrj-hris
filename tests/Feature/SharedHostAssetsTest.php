<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SharedHostAssetsTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // A running Vite dev server, without touching the real public/hot.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($this->hotFile, 'https://wrj-hris.test:5173');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function testOnTheAppUrlThePageUsesTheViteDevServer(): void
    {
        config(['app.url' => 'https://wrj-hris.test']);

        $this->get('https://wrj-hris.test/login')
            ->assertOk()
            ->assertSee('https://wrj-hris.test:5173/@vite/client', false);
    }

    public function testThroughAnNgrokLinkThePageUsesTheBuiltAssets(): void
    {
        config(['app.url' => 'https://wrj-hris.test']);

        $this->withHeaders(['X-Forwarded-Host' => 'demo.ngrok-free.dev', 'X-Forwarded-Proto' => 'https'])
            ->get('https://wrj-hris.test/login')
            ->assertOk()
            ->assertDontSee(':5173', false)
            ->assertSee('/build/assets/', false);
    }
}
