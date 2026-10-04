<?php

it('serves the installable SPA shell for direct lesson and home screen entry', function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32))]);
    $this->withoutVite();

    foreach (['/lessons', '/lessons/123'] as $path) {
        $this->get($path)
            ->assertOk()
            ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
            ->assertSee('rel="apple-touch-icon" href="/icons/apple-touch-icon.png"', false)
            ->assertSee('name="apple-mobile-web-app-capable" content="yes"', false)
            ->assertSee('width=device-width, initial-scale=1', false);
    }
});
