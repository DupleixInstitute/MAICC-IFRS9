Auth::loginUsingId(App\Models\User::value('id'));
$req = Illuminate\Http\Request::create('/dashboard', 'GET');
$req->headers->set('X-Inertia', 'true');
app()->instance('request', $req);
$resp = app(App\Http\Controllers\DashboardController::class)->index($req)->toResponse($req);
$p = json_decode($resp->getContent(), true)['props'];
echo json_encode(['period'=>$p['selectedPeriod'],'compare'=>$p['comparePeriod'],'error'=>$p['error']??null,
 'loans'=>$p['summary']['loans_by_stage']??null,'lgd'=>$p['summary']['lgd_percentages']??null,'ead'=>$p['summary']['total_eads'],
 'trend'=>$p['eclTrends'],'monthEnd'=>$p['monthEnd'],'snap'=>$p['loanBookSnapshot'],'imports'=>count($p['recentImports'])], JSON_PRETTY_PRINT);
