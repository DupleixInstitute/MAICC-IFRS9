<?php
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_oct') { echo "WRONG DB\n"; return; }
foreach ([['oct.maker@maiic.local', 'Oct Maker', 'OctMaker#2026check'], ['oct.checker@maiic.local', 'Oct Checker', 'OctChecker#2026check']] as [$e, $n, $p]) {
    $u = App\Models\User::where('email', $e)->first() ?? new App\Models\User();
    $u->forceFill(['name' => $n, 'email' => $e, 'password' => Hash::make($p), 'email_verified_at' => now()]);
    $u->save();
    $u->syncRoles(['admin']);
    echo $u->id . ' ' . $u->email . ' ' . ($u->can('eir.govern') ? 'govern' : 'no-govern') . "\n";
}
