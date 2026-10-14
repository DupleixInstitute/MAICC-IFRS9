<?php
$res = app(\Illuminate\Contracts\Http\Kernel::class)->handle(\Illuminate\Http\Request::create('/login', 'GET'));
echo $res->getStatusCode(), "\n";
if ($res->exception ?? null) { echo get_class($res->exception), ': ', $res->exception->getMessage(), "\n", $res->exception->getFile(), ':', $res->exception->getLine(), "\n"; }
