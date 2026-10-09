<?php
$ids = ['Ebenezer'=>'104430000084','GOJET'=>'104450000098','JAT'=>'104430000087','JVD'=>'104460000120','Lake Malawi'=>'106050000009','MP SACCO'=>'106050000006','Microloan'=>'104460000116','Milele'=>'106050000007','Mphunzitsi'=>'106050000014','Promenade'=>'106050000004','Mchinji'=>'104460000070','VNC'=>'104430000054'];
$cols = Schema::getColumnListing('contract_eir');
$want = array_values(array_filter($cols, fn($c)=>preg_match('/^(contract_id|customer|approved|sanction|drawn|contractual_rate|rate|tenor|origination|moratorium|repay|instal|frequency|schedule_moratorium|scheme)/i',$c)));
foreach ($ids as $name=>$id) {
    $r = DB::table('contract_eir')->where('contract_id',$id)->first($want);
    echo "== $name ($id): ", $r ? json_encode(array_filter((array)$r, fn($v)=>$v!==null && $v!=='')) : 'NOT IN MASTER', "\n";
    $f = DB::table('contract_fees')->where('contract_id',$id)->get(['fee_type','amount']);
    if ($f->count()) echo "   fees: ", $f->map(fn($x)=>"{$x->fee_type} {$x->amount}")->implode('; '), "\n";
}
