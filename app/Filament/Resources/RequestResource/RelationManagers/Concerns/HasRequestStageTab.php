<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers\Concerns;

use App\Enums\RequestStage;
use App\Models\Request;
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

        // Build tab
        $tab = Tab::make(static::getBaseTabTitle())
            ->icon(static::$icon ?? null);

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

        return $tab;
    }
}
