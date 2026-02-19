<?php

declare(strict_types=1);

namespace App\Observers;

use App\Mail\Erp\QuotationEvaluationApprovalRequestMail;
use App\Models\EmailTemplate;
use App\Models\QuotationEvaluation;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final readonly class QuotationEvaluationObserver
{
    /**
     * Handle the QuotationEvaluation "creating" event.
     */
    public function creating(QuotationEvaluation $quotationEvaluation): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($quotationEvaluation->creator_id === null) {
                $quotationEvaluation->creator_id = $user->getKey();
            }

            if ($quotationEvaluation->team_id === null) {
                // Try Filament tenant first, then fall back to user's current team
                $tenant = Filament::getTenant();
                if ($tenant !== null) {
                    $quotationEvaluation->team_id = $tenant->getKey();
                } elseif ($user->currentTeam !== null) {
                    $quotationEvaluation->team_id = $user->currentTeam->getKey();
                }
            }
        }

        // Auto-generate QE number if not provided
        /** @var string|null $qeNumber */
        $qeNumber = $quotationEvaluation->qe_number;
        if (($qeNumber === null || $qeNumber === '') && $quotationEvaluation->team_id !== null) {
            $quotationEvaluation->qe_number = QuotationEvaluation::generateQeNumber($quotationEvaluation->team_id);
        }
    }

    /**
     * Handle the QuotationEvaluation "created" event.
     */
    public function created(QuotationEvaluation $quotationEvaluation): void
    {
        $this->sendApprovalRequestEmails($quotationEvaluation);
    }

    /**
     * Send approval request emails to assigned approvers.
     */
    private function sendApprovalRequestEmails(QuotationEvaluation $quotationEvaluation): void
    {
        $team = $quotationEvaluation->team;
        if ($team === null) {
            return;
        }

        // Get assigned approvers
        $approvers = collect();

        if ($quotationEvaluation->dept_head_sales_id !== null) {
            $approver = User::find($quotationEvaluation->dept_head_sales_id);
            if ($approver !== null) {
                $approvers->push($approver);
            }
        }

        if ($quotationEvaluation->deputy_director_id !== null) {
            $approver = User::find($quotationEvaluation->deputy_director_id);
            if ($approver !== null && ! $approvers->contains('id', $approver->id)) {
                $approvers->push($approver);
            }
        }

        if ($quotationEvaluation->approved_by_id !== null) {
            $approver = User::find($quotationEvaluation->approved_by_id);
            if ($approver !== null && ! $approvers->contains('id', $approver->id)) {
                $approvers->push($approver);
            }
        }

        if ($approvers->isEmpty()) {
            Log::warning('No approvers found for quotation evaluation approval request', [
                'quotation_evaluation_id' => $quotationEvaluation->id,
                'team_id' => $team->id,
            ]);
            return;
        }

        // Send email to each approver
        foreach ($approvers as $approver) {
            try {
                $emailService = app(EmailTemplateService::class);
                $settings = $team->getErpSettings();

                $emailService->sendWithTeamSettings(
                    $team,
                    new QuotationEvaluationApprovalRequestMail($quotationEvaluation, $approver),
                    $approver->email,
                    null, // templateConfig - using new template system
                    null, // template_id - will use default if not configured
                    EmailTemplate::TYPE_QUOTATION_EVALUATION
                );
            } catch (\Exception $e) {
                Log::error('Failed to send quotation evaluation approval request email', [
                    'quotation_evaluation_id' => $quotationEvaluation->id,
                    'approver_id' => $approver->id,
                    'approver_email' => $approver->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
