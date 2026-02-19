<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers\Concerns;

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

        // Check if QE is approved for tabs after Supplier Quotes
        // Supplier Quotes is AWAITING_SUPPLIER_RESPONSE, so require approval for tabs from PREPARING_BUYER_QUOTE onwards
        $requiresQEApproval = $stage->getOrder() > RequestStage::AWAITING_SUPPLIER_RESPONSE->getOrder();
        
        if ($requiresQEApproval) {
            $latestQE = $request->quotationEvaluations()->latest()->first();
            $isQEApproved = $latestQE !== null && $latestQE->status === QEStatus::APPROVED;
            
            if (! $isQEApproved) {
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
            }
        }
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
        $isCompleted = $currentStage->getOrder() > $stage->getOrder();

        // Check if QE is approved for tabs after Supplier Quotes
        // Supplier Quotes is AWAITING_SUPPLIER_RESPONSE, so disable tabs from PREPARING_BUYER_QUOTE onwards
        $requiresQEApproval = $stage->getOrder() > RequestStage::AWAITING_SUPPLIER_RESPONSE->getOrder();
        $isQEApproved = false;
        
        if ($requiresQEApproval) {
            $latestQE = $ownerRecord->quotationEvaluations()->latest()->first();
            $isQEApproved = $latestQE !== null && $latestQE->status === QEStatus::APPROVED;
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

        // Build tab
        $tab = Tab::make(static::getBaseTabTitle())
            ->icon(static::$icon ?? null);

        // Disable tab if QE approval is required but not approved
        if ($requiresQEApproval && ! $isQEApproved) {
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
