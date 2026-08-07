<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-hosted login CAPTCHA endpoint (Ticket #001, item 1).
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_captcha_endpoint_returns_a_png_for_guests()
    {
        $response = $this->get('/captcha');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        // Never cached — every load must be a fresh challenge.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        // Valid PNG signature.
        $this->assertSame("\x89PNG", substr($response->getContent(), 0, 4));
    }

    public function test_captcha_stores_a_code_in_the_session()
    {
        $response = $this->get('/captcha');

        $code = session('login_captcha');
        $this->assertNotEmpty($code);
        $this->assertSame((int) config('captcha.length'), strlen($code));
        // Only characters from the configured, unambiguous set.
        $this->assertMatchesRegularExpression(
            '/^[' . preg_quote(config('captcha.characters'), '/') . ']+$/',
            $code
        );
        $this->assertNotEmpty(session('login_captcha_time'));
    }

    public function test_each_request_generates_a_fresh_code()
    {
        $this->get('/captcha');
        $first = session('login_captcha');

        $this->get('/captcha');
        $second = session('login_captcha');

        // Overwhelmingly likely to differ (31^5 combinations); equality would
        // indicate the code is not being regenerated.
        $this->assertNotSame($first, $second);
    }
}
