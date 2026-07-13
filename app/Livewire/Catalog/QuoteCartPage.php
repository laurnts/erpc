<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Actions\Catalog\SubmitQuoteCart;
use App\Models\Article;
use App\Models\User;
use App\Services\Catalog\CatalogTeamResolver;
use App\Services\Catalog\QuoteCart;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Quote cart summary page: edit quantities, remove lines, and submit the cart
 * as a portal-originated Request. Guests are prompted to sign in (buyer
 * guard, inline — the cart lives in this session and survives the sign-in)
 * or register; submission requires an active buyer portal membership.
 */
#[Layout('components.layouts.catalog')]
final class QuoteCartPage extends Component
{
    use WithRateLimiting;

    /**
     * @var array<int, string|int|float|null>
     */
    public array $quantities = [];

    public string $email = '';

    public string $password = '';

    public ?string $confirmedRequestNumber = null;

    public function mount(): void
    {
        $this->syncQuantitiesFromCart();
    }

    public function updateQuantity(int $articleId): void
    {
        $quantity = $this->quantities[$articleId] ?? null;

        if (! is_numeric($quantity) || (float) $quantity <= 0) {
            $this->addError('quantities.'.$articleId, 'Quantity must be greater than zero.');

            return;
        }

        app(QuoteCart::class)->update($articleId, (float) $quantity);

        $this->resetErrorBag('quantities.'.$articleId);
        $this->dispatch('catalog-cart-updated');
    }

    public function removeLine(int $articleId): void
    {
        app(QuoteCart::class)->remove($articleId);

        unset($this->quantities[$articleId]);
        $this->resetErrorBag('quantities.'.$articleId);
        $this->dispatch('catalog-cart-updated');
    }

    public function signIn(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->addError('email', sprintf('Too many attempts. Please try again in %d seconds.', $exception->secondsUntilAvailable));

            return;
        }

        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard('buyer');

        if (! $guard->attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        $user = $guard->user();

        if (! $user instanceof User || ! $user->hasVerifiedEmail()) {
            $guard->logout();
            $this->addError('email', 'Please verify your email address first — sign in to the buyer portal to receive a verification link.');

            return;
        }

        if (! $user->hasActiveBuyerPortalAccess()) {
            $guard->logout();
            $this->addError('email', 'No active buyer portal access found for this account.');

            return;
        }

        // Regenerating migrates the session (cart data included) to a new id.
        session()->regenerate();

        $this->reset('password');
    }

    public function submit(): void
    {
        $user = Auth::guard('buyer')->user();

        if (! $user instanceof User) {
            $this->addError('cart', 'Please sign in to submit your quote request.');

            return;
        }

        $cart = app(QuoteCart::class);

        $request = app(SubmitQuoteCart::class)->execute($user, $cart->items());

        $cart->clear();
        $this->quantities = [];
        $this->confirmedRequestNumber = $request->request_number;
        $this->dispatch('catalog-cart-updated');
    }

    public function render(): View
    {
        $resolver = app(CatalogTeamResolver::class);
        $teamId = $resolver->teamId() ?? 0;
        $items = app(QuoteCart::class)->items();

        $articles = Article::query()
            ->whereKey(array_keys($items))
            ->select(['articles.id', 'articles.name', 'articles.unit', 'articles.list_price', 'articles.show_price'])
            ->with('media')
            ->orderBy('articles.name')
            ->get();

        $availableIds = Article::query()
            ->inPublicCatalog($teamId)
            ->whereKey(array_keys($items))
            ->pluck('articles.id')
            ->all();

        $buyerUser = Auth::guard('buyer')->user();

        return view('livewire.catalog.quote-cart-page', [
            'items' => $items,
            'articles' => $articles,
            'availableIds' => $availableIds,
            'baseCurrency' => $resolver->team()?->getBaseCurrency(),
            'isSignedIn' => $buyerUser instanceof User && $buyerUser->hasActiveBuyerPortalAccess(),
            'buyerName' => $buyerUser?->name,
        ])->title('Quote Cart — '.config('app.name'));
    }

    private function syncQuantitiesFromCart(): void
    {
        foreach (app(QuoteCart::class)->items() as $articleId => $quantity) {
            $this->quantities[$articleId] = $quantity;
        }
    }
}
