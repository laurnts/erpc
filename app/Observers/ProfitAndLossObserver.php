<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PNLStatus;
use App\Mail\Erp\ProfitAndLossApprovalRequestMail;
use App\Models\EmailTemplate;
use App\Models\ProfitAndLoss;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;

final readonly class ProfitAndLossObserver
{
    /**
     * Handle the ProfitAndLoss "creating" event.
     */
    public function creating(ProfitAndLoss $profitAndLoss): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($profitAndLoss->creator_id === null) {
                $profitAndLoss->creator_id = $user->getKey();
            }

            if ($profitAndLoss->team_id === null) {
                // Try Filament tenant first, then fall back to user's current team
                $tenant = Filament::getTenant();
                if ($tenant !== null) {
                    $profitAndLoss->team_id = $tenant->getKey();
                } elseif ($user->currentTeam !== null) {
                    $profitAndLoss->team_id = $user->currentTeam->getKey();
                }
            }
        }

        // Auto-generate PNL number if not provided
        /** @var string|null $pnlNumber */
        $pnlNumber = $profitAndLoss->pnl_number;
        if (($pnlNumber === null || $pnlNumber === '') && $profitAndLoss->team_id !== null) {
            $profitAndLoss->pnl_number = ProfitAndLoss::generatePnlNumber($profitAndLoss->team_id);
        }

        // Set initial status to NEED_APPROVAL if not set
        if ($profitAndLoss->status === null) {
            $profitAndLoss->status = PNLStatus::NEED_APPROVAL;
        }
    }

    /**
     * Handle the ProfitAndLoss "updating" event.
     */
    public function updating(ProfitAndLoss $profitAndLoss): void
    {
        // Freeze the financial figures the first time the PNL becomes approved,
        // so the approved value never changes even if the quote is later revised.
        if ($profitAndLoss->isDirty('status')
            && $profitAndLoss->status === PNLStatus::APPROVED
            && $profitAndLoss->financial_snapshot === null) {
            $profitAndLoss->captureFinancialSnapshot();
        }
    }

    /**
     * Handle the ProfitAndLoss "created" event.
     */
    public function created(ProfitAndLoss $profitAndLoss): void
    {
        $this->sendApprovalRequestEmails($profitAndLoss);
    }

    /**
     * Send approval request emails to assigned approvers.
     */
    private function sendApprovalRequestEmails(ProfitAndLoss $profitAndLoss): void
    {
        $team = $profitAndLoss->team;
        if ($team === null) {
            return;
        }

        // Get assigned approvers
        $approvers = collect();

        if ($profitAndLoss->dept_head_sales_id !== null) {
            $approver = User::find($profitAndLoss->dept_head_sales_id);
            if ($approver !== null) {
                $approvers->push($approver);
            }
        }

        if ($profitAndLoss->deputy_director_id !== null) {
            $approver = User::find($profitAndLoss->deputy_director_id);
            if ($approver !== null && ! $approvers->contains('id', $approver->id)) {
                $approvers->push($approver);
            }
        }

        if ($profitAndLoss->approved_by_id !== null) {
            $approver = User::find($profitAndLoss->approved_by_id);
            if ($approver !== null && ! $approvers->contains('id', $approver->id)) {
                $approvers->push($approver);
            }
        }

        if ($approvers->isEmpty()) {
            Log::warning('No approvers found for profit and loss approval request', [
                'profit_and_loss_id' => $profitAndLoss->id,
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
                    new ProfitAndLossApprovalRequestMail($profitAndLoss, $approver),
                    $approver->email,
                    null, // templateConfig - using new template system
                    null, // template_id - will use default if not configured
                    EmailTemplate::TYPE_PROFIT_AND_LOSS
                );
            } catch (\Exception $e) {
                Log::error('Failed to send profit and loss approval request email', [
                    'profit_and_loss_id' => $profitAndLoss->id,
                    'approver_id' => $approver->id,
                    'approver_email' => $approver->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
