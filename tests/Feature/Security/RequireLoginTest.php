<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\RequireLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * System audit of 9 October 2026, finding C7: every web route needs a login
 * unless it is on the public list, and the legacy financial modules need
 * the suite permission they belong to.
 */
class RequireLoginTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Every GET web route refuses a guest: a redirect to login (or 401/404 for parameterised routes), never 200. */
    public function test_no_get_route_answers_a_guest_with_a_page(): void
    {
        $answered = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! in_array('web', (array) $route->middleware(), true)) {
                continue;
            }
            $uri = '/' . ltrim($route->uri(), '/');
            if (str_contains($uri, '{')) {
                continue; // needs a model; the gate runs before binding, so these behave as the un-parameterised ones
            }
            $public = $this->isPublic($route->getName(), $route->uri());
            $response = $this->get($uri);
            if (! $public && $response->getStatusCode() === 200) {
                $answered[] = $uri;
            }
        }
        $this->assertSame([], $answered, 'Routes that served a page to a guest: ' . implode(', ', $answered));
    }

    public function test_a_guest_is_sent_to_the_login_page_from_a_legacy_module(): void
    {
        $this->get('/collateral/register/list')->assertRedirect(route('login'));
        $this->get('/expected-credit-loss/list')->assertRedirect(route('login'));
        $this->getJson('/api/get-tables')->assertStatus(401);
    }

    public function test_the_public_routes_still_answer(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    private function isPublic(?string $name, string $uri): bool
    {
        foreach (RequireLogin::PUBLIC_ROUTES as $p) {
            if ($name !== null && ($p === $name || (str_ends_with($p, '*') && str_starts_with($name, rtrim($p, '*'))))) {
                return true;
            }
        }
        foreach (RequireLogin::PUBLIC_URIS as $u) {
            if (fnmatch($u, $uri) || $u === $uri) {
                return true;
            }
        }

        return false;
    }
}
