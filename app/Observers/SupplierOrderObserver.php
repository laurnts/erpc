<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Enums\CentralPurchasingRole;
use App\Enums\OrderStatus;
use App\Mail\Erp\SupplierOrderApprovalRequestMail;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use App\Services\TeamMemberService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final readonly class SupplierOrderObserver
{
    /**
     * Handle the SupplierOrder "creating" event.
     */
    public function creating(SupplierOrder $supplierOrder): void
    {
        // Only set creator_id and team_id from auth if not already provided
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($supplierOrder->creator_id === null) {
                $supplierOrder->creator_id = $user->getKey();
            }

            if ($supplierOrder->team_id === null && $user->currentTeam !== null) {
                $supplierOrder->team_id = $user->currentTeam->getKey();
            }
        }

        // Get team settings
        $team = $supplierOrder->team ?? ($supplierOrder->team_id !== null ? Team::find($supplierOrder->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;

        // Auto-generate PO number if not provided
        /** @var string|null $poNumber */
        $poNumber = $supplierOrder->po_number;
        if ($poNumber === null || $poNumber === '') {
            $supplierOrder->po_number = $this->generatePoNumber($supplierOrder, $settings);
        }

        // Set default payment terms if not provided
        if ($supplierOrder->payment_terms_days === null) {
            $supplierOrder->payment_terms_days = $settings->default_payment_terms_days;
        }
    }

    /**
     * Generate a unique PO number (PO-YYYY-NNNN-A/B/C format).
     *
     * The suffix (A, B, C) is added when there are multiple orders for the same request.
     */
    private function generatePoNumber(SupplierOrder $supplierOrder, TeamErpSettings $settings): string
    {
        $prefix = $settings->supplier_order_number_prefix;
        $year = date('Y');

        // Check if this request already has orders
        $existingOrdersForRequest = SupplierOrder::query()
            ->withTrashed()
            ->where('team_id', $supplierOrder->team_id)
            ->where('request_id', $supplierOrder->request_id)
            ->orderBy('created_at')
            ->get();

        $existingOrdersCount = $existingOrdersForRequest->count();

        // If there are existing orders for this request, use the same base number with suffix
        if ($existingOrdersCount > 0) {
            $firstOrder = $existingOrdersForRequest->first();
            $firstPoNumber = (string) $firstOrder->po_number;

            // Extract the base number from the first order's PO number
            $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)(?:-[A-Z])?$/';
            if (preg_match($regex, $firstPoNumber, $matches)) {
                $baseNumber = (int) $matches[1];
                $basePoNumber = sprintf('%s-%s-%04d', $prefix, $year, $baseNumber);
                $suffix = chr(65 + $existingOrdersCount); // A=65 for second order, B=66 for third, etc.

                return $basePoNumber.'-'.$suffix;
            }
        }

        // Get the highest sequence number for this team and year (for new base number)
        $pattern = $prefix.'-'.$year.'-%';

        $allOrdersForTeam = SupplierOrder::query()
            ->withTrashed()
            ->where('team_id', $supplierOrder->team_id)
            ->where('po_number', 'like', $pattern)
            ->get();

        $nextNumber = 1;
        $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)(?:-[A-Z])?$/';

        foreach ($allOrdersForTeam as $order) {
            if (preg_match($regex, (string) $order->po_number, $matches)) {
                $orderNumber = (int) $matches[1];
                if ($orderNumber >= $nextNumber) {
                    $nextNumber = $orderNumber + 1;
                }
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }

    /**
     * Handle the SupplierOrder "updated" event.
     */
    public function updated(SupplierOrder $supplierOrder): void
    {
        // Send approval request emails when status changes to CONFIRMED
        if ($supplierOrder->wasChanged('status') && $supplierOrder->status === OrderStatus::CONFIRMED) {
            $this->sendApprovalRequestEmails($supplierOrder);
        }
    }

    /**
     * Send approval request emails to eligible approvers.
     */
    private function sendApprovalRequestEmails(SupplierOrder $supplierOrder): void
    {
        $team = $supplierOrder->team;
        if ($team === null) {
            return;
        }

        // Get all eligible approvers (Dept Head of Sales, Deputy Director, Director)
        $approvalRoles = [
            CentralPurchasingRole::DEPT_HEAD_SALES,
            CentralPurchasingRole::DEPUTY_DIRECTOR,
            CentralPurchasingRole::DIRECTOR,
        ];

        $approvers = collect();
        foreach ($approvalRoles as $role) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, $role);
            $approvers = $approvers->merge($members);
        }

        // Remove duplicates (in case user has multiple roles)
        $approvers = $approvers->unique('id');

        if ($approvers->isEmpty()) {
            Log::warning('No approvers found for supplier order approval request', [
                'supplier_order_id' => $supplierOrder->id,
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
                    new SupplierOrderApprovalRequestMail($supplierOrder, $approver),
                    $approver->email,
                    $settings->email_template_supplier_order ?? null,
                    null, // template_id - will use default if not configured
                    \App\Models\EmailTemplate::TYPE_SUPPLIER_ORDER
                );
            } catch (\Exception $e) {
                Log::error('Failed to send supplier order approval request email', [
                    'supplier_order_id' => $supplierOrder->id,
                    'approver_id' => $approver->id,
                    'approver_email' => $approver->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
