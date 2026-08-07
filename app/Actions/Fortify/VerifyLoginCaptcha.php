<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\ValidationException;

/**
 * First step of the Fortify login pipeline: verify the self-hosted CAPTCHA.
 *
 * This is a dedicated pipeline stage (not part of authenticateUsing) on
 * purpose: with two-factor authentication enabled, Fortify invokes the
 * credential callback twice per login attempt (RedirectIfTwoFactorAuthenticatable
 * and AttemptToAuthenticate). A pipeline stage runs exactly once, so the
 * single-use session code is consumed exactly once.
 */
class VerifyLoginCaptcha
{
    public function handle($request, $next)
    {
        if (! config('captcha.enabled', true)) {
            return $next($request);
        }

        $key = config('captcha.session_key', 'login_captcha');
        $expected = (string) $request->session()->pull($key, '');
        $issuedAt = (int) $request->session()->pull($key . '_time', 0);
        $ttl = (int) config('captcha.ttl', 300);
        $given = strtoupper(trim((string) $request->input('captcha', '')));

        $expired = $issuedAt > 0 && (now()->timestamp - $issuedAt) > $ttl;

        if ($expected === '' || $expired || $given === '' || ! hash_equals(strtoupper($expected), $given)) {
            throw ValidationException::withMessages([
                'captcha' => __('The security code is incorrect or has expired. Please enter the new code shown.'),
            ]);
        }

        return $next($request);
    }
}
