<?php
$app='C:/xampp/htdocs/MAICC-IFRS9/';
$menu=(function() use($app){return require $app.'config/menu.php';})();
$items=[];
$walk=function($l,$t) use(&$walk,&$items){foreach($l as $i){$n=trim($t.' > '.$i['name'],' >');
 if(!empty($i['tabs']))foreach($i['tabs'] as $tab)$items[]=[$n.' > '.$tab['name'],$tab['route'],$tab['params']['tab']??''];
 elseif(!empty($i['children']))$walk($i['children'],$n); elseif(!empty($i['route']))$items[]=[$n,$i['route'],''];}};
$walk($menu['admin'],'');
$map=[];
foreach(['user'=>'help_user_content','admin'=>'help_admin_content'] as $m=>$d){$fs=glob($app."database/seeders/data/$d/*.php");sort($fs);
 foreach($fs as $f)foreach((array)require $f as $ch=>$arts)foreach($arts as $t=>$s)foreach($s['routes']??[] as $r)$map[$r][]=[$m,$t,array_keys($s['images']??[])];}
$tabkey=['loads'=>'eir-feed-loads','builds'=>'eir-feed-builds','periods'=>'eir-feed-periods','queries'=>'eir-feed-queries','blocks'=>'eir-takeon-blocks','population'=>'eir-takeon-population','contracts'=>'eir-data','cashflows'=>'eir-data-cashflows','schedules'=>'eir-schedule-review','blockers'=>'eir-coverage','portfolios'=>'eir-coverage-portfolios','facilities'=>'eir-coverage-facilities','dashboard'=>'macro-dashboard','variables'=>'macro-variables','entry'=>'macro-entry','scenarios'=>'macro-scenarios','import'=>'macro-import'];
foreach($items as [$label,$route,$tab]){$a=$map[$route]??[];
 $arts=implode('; ',array_unique(array_map(fn($x)=>($x[0]==='admin'?'[Admin] ':'').$x[1],$a)));
 $img=$tab&&isset($tabkey[$tab])?$tabkey[$tab]:($a[0][2][0]??'-');
 echo "| $label | $arts | yes | $img.jpg |\n";}
