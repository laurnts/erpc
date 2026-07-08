<?php

declare(strict_types=1);

namespace App\Services\RequestDetail;

use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\CompletionReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\GoodsReceiveRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\Request;

/**
 * Builds the vertical workflow stage bar for the admin request detail page.
 */
final readonly class RequestStageBarPresenter
{
    /**
     * @var array<string, class-string>
     */
    private const MANAGERS = [
        'items' => ItemsRelationManager::class,
        'supplierQuotes' => SupplierQuotesRelationManager::class,
        'buyerQuotes' => BuyerQuotesRelationManager::class,
        'supplierOrders' => SupplierOrdersRelationManager::class,
        'goodsReceive' => GoodsReceiveRelationManager::class,
        'buyerOrders' => BuyerOrdersRelationManager::class,
        'completionReports' => CompletionReportsRelationManager::class,
    ];

    /**
     * @return list<array{
     *     relationKey: string,
     *     label: string,
     *     icon: string|\BackedEnum|null,
     *     index: string|null,
     *     state: 'completed'|'current'|'upcoming'|'disabled',
     *     tooltip: string|null,
     * }>
     */
    public function stepsFor(Request $request, int|string|null $activeRelationManager = null): array
    {
        $steps = [];

        foreach (self::orderedKeys() as $relationKey) {
            $meta = $this->metaFor($request, $relationKey);
            $index = ViewRequest::relationManagerIndexForKey($relationKey);

            if ($index !== null && (string) $activeRelationManager === (string) $index && $meta['state'] !== 'disabled') {
                $meta['state'] = 'current';
            }

            $steps[] = [
                'relationKey' => $relationKey,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'index' => $index,
                'state' => $meta['state'],
                'tooltip' => $meta['tooltip'],
            ];
        }

        return $steps;
    }

    /**
     * @return array{
     *     label: string,
     *     icon: string|\BackedEnum|null,
     *     state: 'completed'|'current'|'upcoming'|'disabled',
     *     tooltip: string|null,
     * }
     */
    private function metaFor(Request $request, string $relationKey): array
    {
        if ($relationKey === 'fulfillment') {
            $meta = AcceptanceReportsRelationManager::getStageBarMeta($request);
            $meta['label'] = 'Fulfillment';

            return $meta;
        }

        /** @var class-string $managerClass */
        $managerClass = self::MANAGERS[$relationKey];

        return $managerClass::getStageBarMeta($request);
    }

    /**
     * @return list<string>
     */
    private static function orderedKeys(): array
    {
        return [
            'items',
            'supplierQuotes',
            'buyerQuotes',
            'supplierOrders',
            'goodsReceive',
            'buyerOrders',
            'fulfillment',
            'completionReports',
        ];
    }
}
