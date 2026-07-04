<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Session;

/**
 * Session-backed quote cart for the public catalog (design D6).
 *
 * Stores article_id => quantity in the visitor's session so the cart survives
 * navigation and the sign-in redirect. No cross-session persistence.
 */
final readonly class QuoteCart
{
    private const string SESSION_KEY = 'catalog_quote_cart';

    /**
     * @return array<int, float> article_id => quantity
     */
    public function items(): array
    {
        $raw = Session::get(self::SESSION_KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $articleId => $quantity) {
            if (is_numeric($articleId) && is_numeric($quantity) && (float) $quantity > 0) {
                $items[(int) $articleId] = (float) $quantity;
            }
        }

        return $items;
    }

    public function add(int $articleId, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $items = $this->items();
        $items[$articleId] = ($items[$articleId] ?? 0.0) + $quantity;

        $this->put($items);
    }

    public function update(int $articleId, float $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($articleId);

            return;
        }

        $items = $this->items();
        $items[$articleId] = $quantity;

        $this->put($items);
    }

    public function remove(int $articleId): void
    {
        $items = $this->items();
        unset($items[$articleId]);

        $this->put($items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /**
     * @param  array<int, float>  $items
     */
    private function put(array $items): void
    {
        Session::put(self::SESSION_KEY, $items);
    }
}
