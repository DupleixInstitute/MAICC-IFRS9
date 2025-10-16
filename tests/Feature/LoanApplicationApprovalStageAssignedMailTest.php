<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Stage;
use App\Models\LoanApplication;
use App\Models\LoanApplicationLinkedApprovalStage;
use App\Notifications\LoanApplicationApprovalStageAssigned;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoanApplicationApprovalStageAssignedMailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_sends_a_loan_application_approval_stage_assigned_email()
    {
        // Create the necessary models
        $user = User::factory()->create(['email' => 'approver@example.com']);
        $role = Role::factory()->create(['name' => 'current_role', 'group_email' => 'role_group@example.com']);
        $loanApplication = LoanApplication::factory()->create();
        $stage = Stage::factory()->create();
        $linkedStage = LoanApplicationLinkedApprovalStage::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'approver_id' => $user->id,
            'stage_id' => $stage->id,
            'approver' => (object)['group_email' => 'approver_group@example.com', 'current_role' => 'current_role'],
        ]);

        // Fake the notification and mail
        Notification::fake();
        Mail::fake();

        // Trigger the notification
        $user->notify(new LoanApplicationApprovalStageAssigned($linkedStage));

        // Assert the notification was sent
        Notification::assertSentTo(
            [$user],
            LoanApplicationApprovalStageAssigned::class,
            function ($notification, $channels) use ($linkedStage) {
                return in_array('mail', $channels);
            }
        );

        // Assert the email was sent
        Mail::assertSent(function ($mail) use ($user, $linkedStage) {
            return $mail->hasTo($user->email)
                && $mail->hasCc('approver_group@example.com')
                && $mail->hasCc('role_group@example.com')
                && $mail->subject === 'Loan Application Approval Assigned'
                && strpos($mail->render(), 'You have been assigned a loan application to approve.') !== false
                && strpos($mail->render(), 'Stage: ' . $linkedStage->stage->name) !== false
                && strpos($mail->render(), route('loan_applications.show', $linkedStage->loan_application_id)) !== false;
        });
    }
}
