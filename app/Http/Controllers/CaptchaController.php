<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Self-hosted, offline login CAPTCHA.
 *
 * Renders a distorted PNG challenge with PHP GD and stashes the answer in the
 * session. No third-party service is contacted and the answer is never sent to
 * the browser as text (it only exists inside the rendered image), so it cannot
 * be read out of the DOM. Verification happens in FortifyServiceProvider.
 */
class CaptchaController extends Controller
{
    public function image(Request $request)
    {
        $length = (int) config('captcha.length', 5);
        $chars = (string) config('captcha.characters', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789');
        $max = strlen($chars) - 1;

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        $key = config('captcha.session_key', 'login_captcha');
        $request->session()->put($key, $code);
        $request->session()->put($key . '_time', now()->timestamp);

        // If GD is somehow unavailable, fall back to a tiny transparent PNG so
        // the page still renders (verification will then simply fail closed).
        if (! function_exists('imagecreatetruecolor')) {
            return $this->blankPng();
        }

        $width = 160;
        $height = 52;
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, true);

        // Light background.
        $bg = imagecolorallocate($img, 243, 244, 246);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        // Speckle noise.
        for ($i = 0; $i < 500; $i++) {
            $c = imagecolorallocate($img, random_int(175, 220), random_int(200, 232), random_int(175, 220));
            imagesetpixel($img, random_int(0, $width), random_int(0, $height), $c);
        }

        // A few wandering lines.
        for ($i = 0; $i < 5; $i++) {
            $lc = imagecolorallocate($img, random_int(120, 175), random_int(160, 200), random_int(120, 175));
            imageline(
                $img,
                random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height),
                $lc
            );
        }

        // Brand-toned glyph colours: greens, slate, dark gold.
        $palette = [[21, 128, 61], [20, 83, 45], [30, 41, 59], [161, 114, 12]];
        $font = 5;
        $fw = imagefontwidth($font);
        $fh = imagefontheight($font);
        $step = (int) (($width - 24) / $length);

        for ($i = 0; $i < $length; $i++) {
            $rgb = $palette[array_rand($palette)];

            // Draw each glyph on a transparent temp canvas, then scale it up so
            // characters are large and slightly irregular.
            $tmp = imagecreatetruecolor($fw, $fh);
            imagesavealpha($tmp, true);
            $trans = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefill($tmp, 0, 0, $trans);
            $glyph = imagecolorallocate($tmp, $rgb[0], $rgb[1], $rgb[2]);
            imagestring($tmp, $font, 0, 0, $code[$i], $glyph);

            $destW = random_int(20, 26);
            $destH = random_int(30, 40);
            $x = 12 + $i * $step + random_int(-2, 3);
            $y = random_int(4, max(5, $height - $destH - 4));

            imagecopyresampled($img, $tmp, $x, $y, 0, 0, $destW, $destH, $fw, $fh);
            imagedestroy($tmp);
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $this->pngResponse($png);
    }

    private function blankPng()
    {
        // 1x1 transparent PNG.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        return $this->pngResponse($png);
    }

    private function pngResponse(string $png)
    {
        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
