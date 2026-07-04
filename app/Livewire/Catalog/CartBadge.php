<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Services\Catalog\QuoteCart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Header cart counter; refreshes whenever any catalog component dispatches
 * the catalog-cart-updated event.
 */
final class CartBadge extends Component
{
    #[On('catalog-cart-updated')]
    public function refreshBadge(): void
    {
        // Re-render happens automatically when the event is handled.
    }

    public function render(): View
    {
        return view('livewire.catalog.cart-badge', [
            'count' => app(QuoteCart::class)->count(),
        ]);
    }
}
