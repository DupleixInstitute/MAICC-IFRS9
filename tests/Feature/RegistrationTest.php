<?php

namespace Tests\Feature;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_public_registration()
    {
        // accounts are created by an administrator (Users & Roles); /register is not served
        $this->get('/register')->assertNotFound();
        $this->assertFalse(\Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::registration()));
    }

    public function test_new_users_cannot_register_themselves()
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'gender' => 'male',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertGuest();
        $response->assertNotFound();
    }
}
