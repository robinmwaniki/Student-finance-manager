<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProgressiveWebAppTest extends TestCase
{
    public function test_offline_shell_assets_are_available_and_financial_pages_are_not_precached(): void
    {
        $manifest = file_get_contents(public_path('manifest.webmanifest'));
        $serviceWorker = file_get_contents(public_path('sw.js'));
        $offlinePage = file_get_contents(public_path('offline.html'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertIsString($manifest);
        $this->assertIsString($serviceWorker);
        $this->assertIsString($offlinePage);
        $this->assertIsString($layout);

        $decodedManifest = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('standalone', $decodedManifest['display']);
        $this->assertSame('/icons/icon.svg', $decodedManifest['icons'][0]['src']);

        $this->assertStringContainsString("cache.add(OFFLINE_URL)", $serviceWorker);
        $this->assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringNotContainsString("cache.addAll", $serviceWorker);
        $this->assertStringNotContainsString("'/dashboard'", $serviceWorker);
        $this->assertStringNotContainsString("'/income'", $serviceWorker);
        $this->assertStringContainsString("navigator.serviceWorker.register", $layout);
        $this->assertStringContainsString("You're offline", $offlinePage);
    }
}
