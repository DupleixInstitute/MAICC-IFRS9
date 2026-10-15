<?php
$base = 'C:/xampp/htdocs/MAICC-IFRS9/database/seeders/data/';
foreach (['user' => 'help_user_content', 'admin' => 'help_admin_content'] as $m => $d) {
  $files = glob($base.$d.'/*.php'); sort($files);
  foreach ($files as $f) {
    foreach ((array) require $f as $ch => $arts) {
      echo "== [$m] ".basename($f)." :: $ch\n";
      foreach ($arts as $t => $s) {
        printf("   - %s | len=%d steps=%d | routes=%s | imgs=%s\n", $t, strlen($s['body'] ?? ''), count($s['steps'] ?? []), implode(',', $s['routes'] ?? []), implode(',', array_keys($s['images'] ?? [])));
      }
    }
  }
}
