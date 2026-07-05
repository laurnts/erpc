<?php

declare(strict_types=1);

use App\Data\TeamErpSettings;
use App\Enums\PortalType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Livewire\Catalog\CatalogHome;
use App\Livewire\Catalog\QuoteCartPage;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Currency;
use App\Models\Request;
use App\Models\User;
use App\Services\Catalog\QuoteCart;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Notification::fake();

    $this->owner = User::factory()->withPersonalTeam()->create();
    $this->team = $this->owner->personalTeam();

    config(['catalog.team_id' => $this->team->getKey()]);

    $this->team->forceFill([
        'erp_settings' => new TeamErpSettings(default_currency: 'USD'),
    ])->save();

    Currency::factory()->usd()->create();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();

    $this->portalUser = User::factory()->create(['email' => 'buyer@catalog.test']);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'portal' => PortalType::Customer,
        'is_active' => true,
    ]);

    $this->article = Article::factory()->for($this->team)->create([
        'name' => 'Cartable Product',
        'is_active' => true,
        'show_in_product_grid' => true,
        'unit' => 'pcs',
    ]);
});

describe('Cart lifecycle', function (): void {
    it('adds an article with a chosen quantity from the grid', function (): void {
        livewire(CatalogHome::class)
            ->set('quantities.'.$this->article->getKey(), 3)
            ->call('addToCart', $this->article->getKey());

        expect(app(QuoteCart::class)->items())->toBe([$this->article->getKey() => 3.0]);
    });

    it('accumulates quantities when the same article is added again', function (): void {
        livewire(CatalogHome::class)
            ->set('quantities.'.$this->article->getKey(), 2)
            ->call('addToCart', $this->article->getKey())
            ->set('quantities.'.$this->article->getKey(), 3)
            ->call('addToCart', $this->article->getKey());

        expect(app(QuoteCart::class)->items())->toBe([$this->article->getKey() => 5.0]);
    });

    it('rejects non-positive quantities when adding', function (): void {
        livewire(CatalogHome::class)
            ->set('quantities.'.$this->article->getKey(), 0)
            ->call('addToCart', $this->article->getKey())
            ->assertHasErrors('quantities.'.$this->article->getKey());

        expect(app(QuoteCart::class)->isEmpty())->toBeTrue();
    });

    it('refuses to add articles that are not in the public catalog', function (): void {
        $hidden = Article::factory()->for($this->team)->create(['show_in_product_grid' => false]);

        livewire(CatalogHome::class)
            ->set('quantities.'.$hidden->getKey(), 1)
            ->call('addToCart', $hidden->getKey())
            ->assertHasErrors('quantities.'.$hidden->getKey());

        expect(app(QuoteCart::class)->isEmpty())->toBeTrue();
    });

    it('updates quantities and removes lines on the cart page', function (): void {
        app(QuoteCart::class)->add($this->article->getKey(), 2.0);

        livewire(QuoteCartPage::class)
            ->assertSee('Cartable Product')
            ->set('quantities.'.$this->article->getKey(), 7)
            ->call('updateQuantity', $this->article->getKey());

        expect(app(QuoteCart::class)->items())->toBe([$this->article->getKey() => 7.0]);

        livewire(QuoteCartPage::class)
            ->call('removeLine', $this->article->getKey());

        expect(app(QuoteCart::class)->isEmpty())->toBeTrue();
    });
});

describe('Guest gate', function (): void {
    it('prompts guests to sign in or register and preserves the cart', function (): void {
        app(QuoteCart::class)->add($this->article->getKey(), 4.0);

        livewire(QuoteCartPage::class)
            ->assertSee('Sign in to request a quote')
            ->assertSee('Register for portal access')
            ->call('submit')
            ->assertHasErrors('cart');

        expect(Request::query()->count())->toBe(0)
            ->and(app(QuoteCart::class)->items())->toBe([$this->article->getKey() => 4.0]);
    });

    it('signs the visitor into the customer guard inline and keeps the cart in the session', function (): void {
        app(QuoteCart::class)->add($this->article->getKey(), 4.0);

        livewire(QuoteCartPage::class)
            ->set('email', 'buyer@catalog.test')
            ->set('password', 'password')
            ->call('signIn')
            ->assertHasNoErrors();

        expect(auth()->guard('customer')->check())->toBeTrue()
            ->and(app(QuoteCart::class)->items())->toBe([$this->article->getKey() => 4.0]);
    });

    it('rejects sign-in for accounts without active customer portal access', function (): void {
        $outsider = User::factory()->create(['email' => 'outsider@catalog.test']);

        livewire(QuoteCartPage::class)
            ->set('email', 'outsider@catalog.test')
            ->set('password', 'password')
            ->call('signIn')
            ->assertHasErrors('email');

        expect(auth()->guard('customer')->check())->toBeFalse();
    });
});

describe('Submission', function (): void {
    it('creates a draft Request with catalog submission method and one item per line', function (): void {
        $second = Article::factory()->for($this->team)->create([
            'name' => 'Second Cart Product',
            'is_active' => true,
            'show_in_product_grid' => true,
            'unit' => 'kg',
        ]);

        $cart = app(QuoteCart::class);
        $cart->add($this->article->getKey(), 3.0);
        $cart->add($second->getKey(), 1.5);

        $this->actingAs($this->portalUser, 'customer');

        $component = livewire(QuoteCartPage::class)
            ->assertSee('Request a quote')
            ->call('submit')
            ->assertHasNoErrors();

        $request = Request::query()->firstOrFail();

        expect($request->team_id)->toBe($this->team->getKey())
            ->and($request->buyer_id)->toBe($this->buyer->getKey())
            ->and($request->stage)->toBe(RequestStage::DRAFT)
            ->and($request->submission_method)->toBe(RequestSubmissionMethod::CATALOG)
            ->and($request->submitted_by_user_id)->toBe($this->portalUser->getKey())
            ->and($request->submitted_at)->not->toBeNull()
            ->and($request->requested_at)->not->toBeNull()
            ->and($request->request_number)->not->toBeNull();

        $items = $request->items()->orderBy('sort_order')->get();

        expect($items)->toHaveCount(2)
            ->and($items[0]->article_id)->toBe($this->article->getKey())
            ->and((float) $items[0]->quantity)->toBe(3.0)
            ->and($items[0]->description)->toBe('Cartable Product')
            ->and($items[1]->article_id)->toBe($second->getKey())
            ->and((float) $items[1]->quantity)->toBe(1.5)
            ->and($items[1]->description)->toBe('Second Cart Product');

        $component
            ->assertSee('Quote request submitted')
            ->assertSee($request->request_number);

        expect(app(QuoteCart::class)->isEmpty())->toBeTrue();
    });

    it('rejects the whole submission when a line is no longer grid-visible', function (): void {
        $cart = app(QuoteCart::class);
        $cart->add($this->article->getKey(), 2.0);

        $this->article->forceFill(['show_in_product_grid' => false])->save();

        $this->actingAs($this->portalUser, 'customer');

        livewire(QuoteCartPage::class)
            ->call('submit')
            ->assertHasErrors('cart');

        expect(Request::query()->count())->toBe(0)
            ->and(app(QuoteCart::class)->isEmpty())->toBeFalse();
    });

    it('rejects submission for users without an active membership at the catalog team', function (): void {
        app(QuoteCart::class)->add($this->article->getKey(), 1.0);

        CompanyPortalUser::query()
            ->where('user_id', $this->portalUser->getKey())
            ->update(['is_active' => false]);

        $this->actingAs($this->portalUser, 'customer');

        livewire(QuoteCartPage::class)
            ->call('submit')
            ->assertHasErrors('cart');

        expect(Request::query()->count())->toBe(0);
    });

    it('makes the submitted request visible through buyer scoping', function (): void {
        app(QuoteCart::class)->add($this->article->getKey(), 2.0);

        $this->actingAs($this->portalUser, 'customer');

        livewire(QuoteCartPage::class)->call('submit')->assertHasNoErrors();

        expect(Request::query()->forBuyer($this->buyer->getKey())->count())->toBe(1)
            ->and(Request::query()->forBuyer($this->buyer->getKey() + 999)->count())->toBe(0);
    });
});
