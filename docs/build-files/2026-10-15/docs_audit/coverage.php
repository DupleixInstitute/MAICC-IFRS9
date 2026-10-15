<?php
// Menu routes (and tab params) against the article routes of both manuals.
$app = 'C:/xampp/htdocs/MAICC-IFRS9/';
$menu = (function() use ($app) { return require $app.'config/menu.php'; })();
$items = [];
$walk = function($list, $trail) use (&$walk, &$items) {
  foreach ($list as $i) {
    $t = trim($trail.' > '.$i['name'], ' >');
    if (!empty($i['tabs'])) foreach ($i['tabs'] as $tab) $items[] = [$t.' > '.$tab['name'], $tab['route'], http_build_query($tab['params'] ?? [])];
    elseif (!empty($i['children'])) $walk($i['children'], $t);
    elseif (!empty($i['route'])) $items[] = [$t, $i['route'], ''];
  }
};
$walk($menu['admin'], '');
$map = [];
foreach (['user' => 'help_user_content', 'admin' => 'help_admin_content'] as $m => $d) {
  $files = glob($app.'database/seeders/data/'.$d.'/*.php'); sort($files);
  foreach ($files as $f) foreach ((array) require $f as $ch => $arts) foreach ($arts as $t => $s) {
    foreach ($s['routes'] ?? [] as $r) $map[$r][] = "[$m] $t";
    foreach ($s['images'] ?? [] as $k => $c) $imgs[$k][] = "[$m] $t";
  }
}
$out = [];
foreach ($items as [$label, $route, $q]) {
  $arts = $map[$route] ?? [];
  printf("%s | %s%s | %s\n", $label, $route, $q ? "?$q" : '', $arts ? implode('; ', array_unique($arts)) : 'NO ARTICLE');
}
file_put_contents(__DIR__.'/image_keys_used.json', json_encode(array_keys($imgs ?? [])));
