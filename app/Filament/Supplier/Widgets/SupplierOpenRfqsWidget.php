<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Widgets;

use Filament\Widgets\Widget;

/**
 * Placeholder shipped with the panel shell: RFQ visibility requires the
 * `supplier_quotes.sent_to_supplier_at` gate, which lands with RFQ
 * participation (Slice 3). Gated off entirely until then.
 */
final class SupplierOpenRfqsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.supplier.widgets.supplier-open-rfqs-widget';

    public static function canView(): bool
    {
        return false;
    }
}
