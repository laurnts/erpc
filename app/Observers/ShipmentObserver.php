<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\BuyerPortal\NotifyPortalUsers;
use App\Data\TeamErpSettings;
use App\Models\Shipment;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PortalShipmentStatusChangedNotification;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

final readonly class ShipmentObserver
{
    /**
     * Handle the Shipment "creating" event.
     */
    public function creating(Shipment $shipment): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($shipment->creator_id === null) {
                $shipment->creator_id = $user->getKey();
            }

            if ($shipment->team_id === null && $user->currentTeam !== null) {
                $shipment->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate shipment number if not provided
        /** @var string|null $shipmentNumber */
        $shipmentNumber = $shipment->shipment_number;
        if ($shipmentNumber === null || $shipmentNumber === '') {
            $shipment->shipment_number = $this->generateShipmentNumber($shipment);
        }
    }

    /**
     * Generate a unique shipment number (SHP-YYYY-NNNN format).
     */
    private function generateShipmentNumber(Shipment $shipment): string
    {
        $team = $shipment->team ?? ($shipment->team_id !== null ? Team::find($shipment->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->shipment_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $shipment->team_id, 'shipment', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    public function updated(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('status')) {
            return;
        }

        $shipment->loadMissing('request');

        if ($shipment->request?->buyer_id === null) {
            return;
        }

        app(NotifyPortalUsers::class)->forRequest(
            $shipment->request,
            new PortalShipmentStatusChangedNotification($shipment),
        );
    }
}
