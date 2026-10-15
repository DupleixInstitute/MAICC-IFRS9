foreach ([['UAT Maker','uat.maker@maiic.test'],['UAT Checker','uat.checker@maiic.test']] as [$n,$e]) {
  $u = \App\Models\User::firstOrNew(['email' => $e]);
  $u->name = $n; $u->gender = 'male'; $u->password = \Illuminate\Support\Facades\Hash::make('UatPass#2026'); $u->email_verified_at = now(); $u->save();
  $u->syncRoles(['admin']);
  echo $u->id.' '.$e.' '.implode(',', $u->getRoleNames()->all()).' govern='.(int)$u->can('eir.govern').PHP_EOL;
}
echo 'db=' . \DB::connection()->getDatabaseName() . PHP_EOL;
