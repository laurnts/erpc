<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Repeater;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Repeater that safely handles null child schema when resolving item labels.
 * Avoids "Call to a member function getStateSnapshot() on null" when state/cache
 * is not yet in sync (e.g. wizard step transition).
 */
final class SafeLabelRepeater extends Repeater
{
    public function getItemLabel(string $key): string|Htmlable|null
    {
        $container = $this->getChildSchema($key);

        if (! $container instanceof \Filament\Schemas\Schema) {
            return null;
        }

        return $this->evaluate($this->itemLabel, [
            'container' => $container,
            'item' => $container,
            'key' => $key,
            'schema' => $container,
            'state' => $container->getStateSnapshot(),
            'uuid' => $key,
        ]);
    }
}
