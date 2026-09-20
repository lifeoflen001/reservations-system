<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hotel.setup.enabled' => false]);
    }

    public function test_login_is_the_entry_point_while_setup_is_disabled(): void
    {
        $this->get(route('login'))
            ->assertOk();

        $this->get(route('setup.index'))
            ->assertRedirect(route('login'));

        $this->post(route('setup.operating-mode'), ['operating_mode' => 'desktop'])
            ->assertRedirect(route('login'));

        $this->post(route('setup.complete'))
            ->assertRedirect(route('login'));
    }
}
