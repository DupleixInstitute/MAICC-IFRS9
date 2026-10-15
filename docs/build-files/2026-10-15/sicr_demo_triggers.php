<?php
// Demo SICR triggers on maiic_ifrs9_demo: raised by user 1, approved by user 2 (Demo Approver, not MAIIC).
use App\Services\Eir\QualitativeSicrService;
use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_demo') { echo "wrong db\n"; return; }
$r = new QualitativeSicrService();
$demo = [
    ['104450000098', 'Crop failure, drought or flood in the borrower\'s area', 'Demo trigger, not a MAIIC assessment. Shows how a weather event in the borrower\'s district lifts a current agricultural loan to Stage 2.', 'Demo only: no document. A real trigger names the disaster declaration or the field report, its date and the credit file reference.'],
    ['104430000071', 'Breach of a financial covenant', 'Demo trigger, not a MAIIC assessment. Shows how a covenant breach lifts a current industrial loan to Stage 2.', 'Demo only: no document. A real trigger names the covenant test, the accounts it was measured on and the credit file reference.'],
    ['104460000111', 'Borrower in liquidation, receivership or administration', 'Demo trigger, not a MAIIC assessment. Shows how credit-impaired evidence moves a current loan straight to Stage 3.', 'Demo only: no document. A real trigger names the court order or the appointment of the liquidator, its date and the credit file reference.'],
];
foreach ($demo as [$contract, $item, $reason, $evidence]) {
    if (DB::table('finance_sicr_triggers')->where('account_number', $contract)->where('is_demo', true)->exists()) { echo "exists {$contract}\n"; continue; }
    $itemId = (int) DB::table('finance_sicr_items')->where('name', $item)->value('id');
    $id = $r->propose(['item_id' => $itemId, 'scope' => 'contract', 'contract_id' => $contract, 'reason' => $reason, 'evidence' => $evidence, 'effective_from' => '2026-08-01', 'is_demo' => true], 1);
    $r->approve($id, 2);
    echo "trigger {$id} on {$contract}\n";
}
