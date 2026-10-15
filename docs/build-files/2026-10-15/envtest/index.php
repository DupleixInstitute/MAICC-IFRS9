<?php
$bat = __DIR__ . '\t.bat';
file_put_contents($bat, "@echo off\r\necho %DB_DATABASE% > \"" . __DIR__ . "\out.txt\"\r\n");
$h = @popen('start /B "" "' . $bat . '"', 'r'); if ($h) pclose($h);
echo 'launched ' . getenv('DB_DATABASE');
