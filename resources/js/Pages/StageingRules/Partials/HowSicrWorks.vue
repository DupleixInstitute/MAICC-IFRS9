<template>
    <!-- The one explanation of qualitative SICR, shared by the three SICR screens. Closed by default. -->
    <details class="text-xs text-gray-600">
        <summary class="cursor-pointer select-none font-semibold text-maiic-700">How qualitative SICR works</summary>
        <div class="mt-2 grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-3">
            <div>
                <div class="font-semibold text-gray-800">1. Stage by days past due</div>
                <p class="mt-1">Each month the staging run first sets the stage from days past due under the staging thresholds (Stage 2 from 31 days; Stage 3 from 91 or 181 days depending on the facility), and from missed instalments. This is the <strong>pre-qualitative stage</strong>.</p>
            </div>
            <div>
                <div class="font-semibold text-gray-800">2. Add the judgement</div>
                <p class="mt-1">A credit officer raises a trigger on a loan, or on every loan of a customer, from the items on this list, with the reason, the evidence and the dates it applies. A second person approves it. Each item says the stage it points to: Stage 2 for a significant increase in credit risk, Stage 3 for evidence the loan is credit-impaired.</p>
            </div>
            <div>
                <div class="font-semibold text-gray-800">3. The stage the ECL uses</div>
                <p class="mt-1">At each month-end the run takes the higher of the two: an approved trigger in force can move a loan up, never down. That is the <strong>post-qualitative stage</strong>, the one the PD, LGD, ECL and every report use. When a trigger is closed, the loan stays at its stage for the governed cure period before it moves down. A new or closed trigger takes effect when the month is staged again and the ECL re-run.</p>
            </div>
        </div>
    </details>
</template>
