<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Request;
use Filament\Widgets\Widget;

/**
 * Footer widget that renders the per-request History timeline at the very
 * bottom of the request view (below the information-flow guide), always open.
 * A footer widget is never collapsible, so the timeline + note composer stay
 * permanently visible — the chat-style surface the timeline is meant to be.
 */
final class RequestHistoryWidget extends Widget
{
    protected string $view = 'filament.widgets.request-history-widget';

    protected int|string|array $columnSpan = 'full';

    public ?Request $record = null;

    /**
     * Span full width at every breakpoint so the timeline is not constrained
     * by the footer widget grid.
     *
     * @return array<string, string|int>
     */
    public function getColumnSpan(): array
    {
        return [
            'default' => 'full',
            'sm' => 'full',
            'md' => 'full',
            'lg' => 'full',
            'xl' => 'full',
            '2xl' => 'full',
        ];
    }
}
