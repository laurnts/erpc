<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers\Concerns;

use App\Enums\BuyerQuoteStatus;
use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource;
use App\Models\Request;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for relation managers that are associated with a request stage.
 * Provides tab customization with stage completion indicators.
 */
trait HasRequestStageTab
{
    /**
     * Get the request stage associated with this relation manager.
     */
    abstract protected static function getAssociatedStage(): RequestStage;

    /**
     * Get the tab title without the step indicator.
     */
    abstract protected static function getBaseTabTitle(): string;

    /**
     * Mount the relation manager and check QE and PNL approval.
     * Prevents access if QE or PNL approval is required but not approved.
     */
    public function mount(): void
    {
        parent::mount();
        
        /** @var Request $request */
        $request = $this->getOwnerRecord();
        $stage = static::getAssociatedStage();

        // Check if QE is approved for tabs after Supplier Quotes (or has obtained+selected quote)
        // Supplier Quotes is AWAITING_SUPPLIER_RESPONSE, so require approval for tabs from PREPARING_BUYER_QUOTE onwards
        $requiresQEApproval = $stage->getOrder() > RequestStage::AWAITING_SUPPLIER_RESPONSE->getOrder();
        
        if ($requiresQEApproval) {
            $latestQE = $request->quotationEvaluations()->latest()->first();
            $isQEApproved = $latestQE !== null && $latestQE->status === QEStatus::APPROVED;
            $hasObtainedSelected = $request->hasObtainedSelectedSupplierQuote();
            
            if (! $isQEApproved && ! $hasObtainedSelected) {
                Notification::make()
                    ->title('Access Restricted')
                    ->body('Quotation Evaluation must be approved before accessing this section.')
                    ->warning()
                    ->send();
                
                // Redirect back to Supplier Quotes tab
                $this->redirect(RequestResource::getUrl('view', ['record' => $request->id, 'activeRelationManager' => 'supplierQuotes']));
                return;
            }
        }

        // Check if PNL is approved for tabs from Buyer Orders to Completion Report
        // Buyer Orders is AWAITING_BUYER_CONFIRMATION (order 4), Completion Report is DELIVERED (order 8)
        $requiresPNLApproval = $stage->getOrder() >= RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder() 
            && $stage->getOrder() <= RequestStage::DELIVERED->getOrder();
        
        if ($requiresPNLApproval) {
            $latestPNL = $request->profitAndLosses()->latest()->first();
            $isPNLApproved = $latestPNL !== null && $latestPNL->status === PNLStatus::APPROVED;
            
            if (! $isPNLApproved) {
                Notification::make()
                    ->title('Access Restricted')
                    ->body('Profit & Loss must be approved before accessing this section.')
                    ->warning()
                    ->send();
                
                // Redirect back to Buyer Quotes tab
                $this->redirect(RequestResource::getUrl('view', ['record' => $request->id, 'activeRelationManager' => 'buyerQuotes']));
                return;
            }
        }

        // From Buyer Orders onwards: require at least one Accepted quote and no quotes left in Sent (all Sent must have PO uploaded → Accepted)
        $requiresAcceptedQuote = $stage->getOrder() >= RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder()
            && $stage->getOrder() <= RequestStage::DELIVERED->getOrder();
        if ($requiresAcceptedQuote) {
            $hasAcceptedQuote = $request->buyerQuotes()->where('status', BuyerQuoteStatus::ACCEPTED)->exists();
            $hasSentQuote = $request->buyerQuotes()->where('status', BuyerQuoteStatus::SENT)->exists();
            if (! $hasAcceptedQuote) {
                Notification::make()
                    ->title('Access Restricted')
                    ->body('Send the buyer quote, then upload the buyer\'s PO so the quote status is Accepted. You can then continue to Purchase and later stages.')
                    ->warning()
                    ->send();
                $this->redirect(RequestResource::getUrl('view', ['record' => $request->id, 'activeRelationManager' => 'buyerQuotes']));
                return;
            }
            if ($hasSentQuote) {
                Notification::make()
                    ->title('Access Restricted')
                    ->body('Upload PO for all buyer quotes that are in Sent status (their status will change to Accepted). Once every sent quote has a PO uploaded, you can continue to the next stage.')
                    ->warning()
                    ->send();
                $this->redirect(RequestResource::getUrl('view', ['record' => $request->id, 'activeRelationManager' => 'buyerQuotes']));
                return;
            }
        }

        // Check if all supplier orders are approved for Goods Receive tab
        if ($stage === RequestStage::GOODS_RECEIVE && static::hasUnapprovedSupplierOrders($request)) {
            Notification::make()
                ->title('Access Restricted')
                ->body('All Supplier Orders must be approved before accessing Goods Receive.')
                ->warning()
                ->send();

            $this->redirect(RequestResource::getUrl('view', ['record' => $request->id, 'activeRelationManager' => 'supplierOrders']));
            return;
        }
    }

    /**
     * Check if the request has any supplier orders that are not yet approved (status not APPROVED or SENT).
     */
    private static function hasUnapprovedSupplierOrders(Request $request): bool
    {
        return $request->supplierOrders()
            ->whereNotIn('status', [OrderStatus::APPROVED, OrderStatus::SENT, OrderStatus::CANCELLED])
            ->exists();
    }

    /**
     * Customize the tab component with stage completion indicators.
     */
    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var Request $ownerRecord */
        $stage = static::getAssociatedStage();
        $currentStage = $ownerRecord->stage;

        // Determine tab state
        $isCurrentStage = $currentStage === $stage;
        // Invoices (AWAITING_BUYER_CONFIRMATION) appears after Purchases/Goods Receive in the tab bar,
        // so only show check when we have actually passed Invoices (e.g. on Shipments or later)
        $isCompleted = $stage === RequestStage::AWAITING_BUYER_CONFIRMATION
            ? $currentStage->getOrder() >= RequestStage::AWAITING_SHIPMENT->getOrder()
            : $currentStage->getOrder() > $stage->getOrder();

        // Check if QE is approved for tabs after Supplier Quotes (or has obtained+selected quote)
        // Supplier Quotes is AWAITING_SUPPLIER_RESPONSE, so disable tabs from PREPARING_BUYER_QUOTE onwards
        $requiresQEApproval = $stage->getOrder() > RequestStage::AWAITING_SUPPLIER_RESPONSE->getOrder();
        $isQEApproved = false;
        $hasObtainedSelected = false;
        
        if ($requiresQEApproval) {
            $latestQE = $ownerRecord->quotationEvaluations()->latest()->first();
            $isQEApproved = $latestQE !== null && $latestQE->status === QEStatus::APPROVED;
            $hasObtainedSelected = $ownerRecord->hasObtainedSelectedSupplierQuote();
        }

        // Check if PNL is approved for tabs from Buyer Orders to Completion Report
        // Buyer Orders is AWAITING_BUYER_CONFIRMATION (order 4), Completion Report is DELIVERED (order 8)
        $requiresPNLApproval = $stage->getOrder() >= RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder() 
            && $stage->getOrder() <= RequestStage::DELIVERED->getOrder();
        $isPNLApproved = false;
        
        if ($requiresPNLApproval) {
            $latestPNL = $ownerRecord->profitAndLosses()->latest()->first();
            $isPNLApproved = $latestPNL !== null && $latestPNL->status === PNLStatus::APPROVED;
        }

        $requiresAcceptedQuote = $stage->getOrder() >= RequestStage::AWAITING_BUYER_CONFIRMATION->getOrder()
            && $stage->getOrder() <= RequestStage::DELIVERED->getOrder();
        $hasAcceptedQuote = false;
        $hasSentQuote = false;
        if ($requiresAcceptedQuote) {
            $hasAcceptedQuote = $ownerRecord->buyerQuotes()->where('status', BuyerQuoteStatus::ACCEPTED)->exists();
            $hasSentQuote = $ownerRecord->buyerQuotes()->where('status', BuyerQuoteStatus::SENT)->exists();
        }

        // Build tab
        $tab = Tab::make(static::getBaseTabTitle())
            ->icon(static::$icon ?? null);

        // Disable tab if QE approval is required but not approved (and no obtained+selected quote)
        if ($requiresQEApproval && ! $isQEApproved && ! $hasObtainedSelected) {
            $tab->disabled()
                ->badgeColor('gray')
                ->badgeTooltip('Quotation Evaluation must be approved first')
                ->extraAttributes([
                    'class' => 'qe-disabled-tab',
                ]);
        } elseif ($requiresPNLApproval && ! $isPNLApproved) {
            // Disable tab if PNL approval is required but not approved
            $tab->disabled()
                ->badgeColor('gray')
                ->badgeTooltip('Profit & Loss must be approved first')
                ->extraAttributes([
                    'class' => 'qe-disabled-tab',
                ]);
        } elseif ($requiresAcceptedQuote && ! $hasAcceptedQuote) {
            $tab->disabled()
                ->badgeColor('gray')
                ->badgeTooltip('Send buyer quote and upload PO (quote status must be Accepted) to continue')
                ->extraAttributes([
                    'class' => 'qe-disabled-tab',
                ]);
        } elseif ($requiresAcceptedQuote && $hasSentQuote) {
            $tab->disabled()
                ->badgeColor('gray')
                ->badgeTooltip('Upload PO for all sent buyer quotes (status → Accepted) before continuing')
                ->extraAttributes([
                    'class' => 'qe-disabled-tab',
                ]);
        } elseif ($stage === RequestStage::GOODS_RECEIVE && static::hasUnapprovedSupplierOrders($ownerRecord)) {
            // Disable Goods Receive tab until all supplier orders are approved
            $tab->disabled()
                ->badgeColor('gray')
                ->badgeTooltip('All Supplier Orders must be approved first')
                ->extraAttributes([
                    'class' => 'qe-disabled-tab',
                ]);
        } else {
            // Add completion badge
            if ($isCompleted) {
                $tab->badge('✓')
                    ->badgeColor('success')
                    ->badgeTooltip('Completed');
            } elseif ($isCurrentStage) {
                $tab->badge('●')
                    ->badgeColor('primary')
                    ->badgeTooltip('Current stage');
            }
        }

        return $tab;
    }
}
