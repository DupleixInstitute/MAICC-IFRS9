<?php

namespace App\Console\Commands;

use App\Models\LoanApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-script';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is fixing the script, modified depending on the changes made in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
    //    $applications = LoanApplication::get();
    //    foreach ($applications as $application) {
    //     //    dd($application);
    //        //check related approval stages for that loan application
    //        $linkedStages = $application->linkedStages;
    //        //check the stage with is_current_stage = 1
    //           $currentStage = $linkedStages->where('completed', 1)->first();
    //             if($currentStage){
    //                 if($application->current_loan_application_approval_stage_id != $currentStage->id){
    //                     Log::info('Loan application with id '.$application->id.' has a mismatch in the current stage');
    //                     //update it
    //                     $application->update([
    //                         'current_loan_application_approval_stage_id' => $currentStage->id
    //                     ]);
    //                     //remove is current from the stage tha has it currently
    //                     $currentStage->update([
    //                         'is_current' => 1
    //                     ]);

    //                     //other stages should be updated to is_current = 0
    //                     $otherStages = $linkedStages->where('id', '!=', $currentStage->id);
    //                     foreach ($otherStages as $otherStage) {
    //                         $otherStage->update([
    //                             'is_current' => 0
    //                         ]);
    //                     }

    //                  }

    //             }
    //         //check the loan current stage column in the loan application table



    //       //check the current stage

    //    }


    //    ///FIX BRANCH

    //         //FIX BRANCH ISSUES
    //         $loansWithoutBranch = LoanApplication::whereNull('branch_id')->get();
    //         // dd($loansWithoutBranch);
    //         foreach ($loansWithoutBranch as $loan) {
    //             Log::info('Loan application with id '.$loan->id.' has no branch');
    //             $branchId = $loan->client->branch_id;
    //             Log::info($branchId);
    //             if($branchId){
    //                 $loan->update([
    //                     'branch_id' => $branchId
    //                 ]);
    //             }
    //             // Log::info('Loan application with id '.$loan->id.' has been assigned branch id ');
    //         }



    //fixing 365
    $applications = LoanApplication::whereHas('linkedStages', function($query){
        $query->where('status', 'approved');
    })->get();
    // dd($applications);
    foreach($applications as $application)
    {
        //get linked stages
        $linkedStages = $application->linkedStages->where('status', 'approved');
        // dd($linkedStages);
        $lastStage = $linkedStages->last();
        // dd($lastStage);
        if($application->current_loan_application_approval_stage_id != $lastStage->id){
            Log::info('Loan application with id '.$application->id.' has a mismatch in the current stage');
            //update it
            $application->update([
                'current_loan_application_approval_stage_id' => $lastStage->id
            ]);
            //remove is current from the stage tha has it currently
            $lastStage->update([
                'is_current' => 1
            ]);

            //other stages should be updated to is_current = 0
            $otherStages = $linkedStages->where('id', '!=', $lastStage->id);
            foreach ($otherStages as $otherStage) {
                $otherStage->update([
                    'is_current' => 0
                ]);
            }

        }


    }


    }
}
