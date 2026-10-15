$out = [];
$walk = function ($items, $trail) use (&$walk, &$out) {
  foreach ($items as $it) {
    $t = array_merge($trail, [$it['name']]);
    if (!empty($it['tabs'])) {
      foreach ($it['tabs'] as $tab) {
        $out[] = ['menu' => implode(' > ', $t), 'tab' => $tab['name'], 'route' => $tab['route'], 'url' => route($tab['route'], $tab['params'] ?? [], false), 'perm' => $tab['permissions']];
      }
    } elseif (!empty($it['children'])) {
      $walk($it['children'], $t);
    } elseif (!empty($it['route'])) {
      $out[] = ['menu' => implode(' > ', $t), 'tab' => '', 'route' => $it['route'], 'url' => route($it['route'], [], false), 'perm' => $it['permissions'] ?? '', 'download' => $it['download'] ?? false];
    }
  }
};
$walk(config('menu.admin'), []);
file_put_contents('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/menu_urls.json', json_encode($out, JSON_PRETTY_PRINT));
echo count($out);
