<?php
// Render every parameter-free GET page as user 1 against the configured database; report non-2xx/3xx.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::find(1);
$ok = 0; $bad = [];
foreach (app('router')->getRoutes() as $r) {
    if (! in_array('GET', $r->methods()) || str_contains($r->uri(), '{')) continue;
    $u = $r->uri();
    if (preg_match('#^(migrate$|cache$|_|api/|sanctum|telescope|horizon|livewire|logout|docs|_debugbar|.*export|.*download|.*pdf|.*xlsx|.*csv|.*template|.*sample)#i', $u)) continue;
    auth()->setUser($user);
    $req = Illuminate\Http\Request::create('/' . ltrim($u, '/'), 'GET');
    $req->setUserResolver(fn () => $user);
    try { $res = $kernel->handle($req); $s = $res->getStatusCode(); } catch (Throwable $e) { $s = 'EXC ' . substr($e->getMessage(), 0, 120); }
    if (is_int($s) && $s < 400) $ok++; else $bad[] = "$s  /$u";
    app('auth')->forgetGuards();
}
echo "ok $ok, bad " . count($bad) . "\n" . implode("\n", array_slice($bad, 0, 60)) . "\n";
