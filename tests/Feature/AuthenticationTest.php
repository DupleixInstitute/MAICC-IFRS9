<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Login requires the self-hosted CAPTCHA (Ticket #001, item 1). Helper to
     * put a known challenge code into the session, as GET /captcha would.
     */
    private function withCaptchaInSession(string $code = 'AB2C3')
    {
        return $this->withSession([
            'login_captcha' => $code,
            'login_captcha_time' => now()->timestamp,
        ]);
    }

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->withCaptchaInSession('AB2C3')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha' => 'ab2c3', // case-insensitive
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->withCaptchaInSession('AB2C3')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'captcha' => 'AB2C3',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_wrong_captcha()
    {
        $user = User::factory()->create();

        $response = $this->withCaptchaInSession('AB2C3')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha' => 'WRONG',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha');
    }

    public function test_users_can_not_authenticate_with_expired_captcha()
    {
        $user = User::factory()->create();

        $response = $this->withSession([
            'login_captcha' => 'AB2C3',
            'login_captcha_time' => now()->subSeconds(config('captcha.ttl') + 60)->timestamp,
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha' => 'AB2C3',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha');
    }

    public function test_captcha_is_single_use()
    {
        $user = User::factory()->create();

        // First attempt consumes the code (wrong password on purpose).
        $this->withCaptchaInSession('AB2C3')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'captcha' => 'AB2C3',
        ]);
        $this->assertGuest();

        // Replaying the same code without a fresh challenge must fail.
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha' => 'AB2C3',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha');
    }

    public function test_login_without_captcha_fails_when_enabled()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha');
    }

    public function test_captcha_can_be_disabled_via_config()
    {
        config(['captcha.enabled' => false]);

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
