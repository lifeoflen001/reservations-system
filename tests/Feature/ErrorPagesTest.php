<?php

namespace Tests\Feature;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_not_found_page_uses_the_standalone_lodgix_error_design(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found.')
            ->assertSee('Lodgix')
            ->assertSee('Return home')
            ->assertSee('Status 404')
            ->assertDontSee('data-sidebar', false)
            ->assertDontSee('data-topbar', false);
    }

    public function test_common_error_views_render_without_the_authenticated_app_shell(): void
    {
        foreach ([401, 403, 419, 429, 500, 503] as $status) {
            $response = $this->view('errors.'.$status, ['exception' => new HttpException($status)]);

            $response->assertSee('Lodgix')
                ->assertSee('Status '.$status)
                ->assertSee('Return home')
                ->assertDontSee('data-sidebar', false)
                ->assertDontSee('data-topbar', false);
        }
    }

    public function test_error_page_supports_a_safe_fallback_when_the_wordmark_is_unavailable(): void
    {
        $response = $this->view('errors.error', ['status' => 500]);

        $response->assertSee('assets/images/landing/lodgix-wordmark.webp')
            ->assertSee('font-family: Inter', false)
            ->assertSee('#e67e2f', false)
            ->assertSee('prefers-reduced-motion', false);
    }
}
