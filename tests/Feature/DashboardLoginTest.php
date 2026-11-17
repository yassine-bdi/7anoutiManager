<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dashboard_auth.username', 'omar.makhadou');
        config()->set('dashboard_auth.password_hash', Hash::make('Omar//2026//7'));
    }

    /**
     * It redirects guests to login page.
     */
    public function test_it_redirects_guest_from_dashboard_to_login(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    /**
     * It allows login with configured credentials.
     */
    public function test_it_logs_in_with_expected_credentials(): void
    {
        $response = $this->post(route('login.attempt'), [
            'username' => 'omar.makhadou',
            'password' => 'Omar//2026//7',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('dashboard_authenticated', true)
            ->assertSessionHas('dashboard_username', 'omar.makhadou');

        $this->get(route('home'))->assertOk();
    }

    /**
     * It rejects invalid credentials.
     */
    public function test_it_rejects_invalid_login_credentials(): void
    {
        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => 'omar.makhadou',
            'password' => 'bad-password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username')
            ->assertSessionMissing('dashboard_authenticated');
    }

    /**
     * It throttles repeated login attempts.
     */
    public function test_it_throttles_excessive_login_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login.attempt'), [
                'username' => 'omar.makhadou',
                'password' => 'bad-password',
            ]);
        }

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => 'omar.makhadou',
            'password' => 'bad-password',
        ]);

        $response->assertStatus(429);
    }
}
