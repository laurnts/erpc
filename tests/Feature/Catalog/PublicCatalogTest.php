<?php

declare(strict_types=1);

use App\Data\TeamErpSettings;
use App\Livewire\Catalog\CatalogHome;
use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\SupplierArticle;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->owner = User::factory()->withPersonalTeam()->create();
    $this->team = $this->owner->personalTeam();

    config(['catalog.team_id' => $this->team->getKey()]);

    $this->team->forceFill([
        'erp_settings' => new TeamErpSettings(default_currency: 'USD'),
    ])->save();

    Currency::factory()->usd()->create();
});

function makeCatalogArticle(Team $team, array $attributes = []): Article
{
    return Article::factory()->for($team)->create([
        'is_active' => true,
        'show_in_product_grid' => true,
        ...$attributes,
    ]);
}

describe('Homepage smoke', function (): void {
    it('renders the catalog homepage for guests without errors', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Smoke Test Product']);

        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Smoke Test Product');
    });

    it('renders for guests even when no team exists', function (): void {
        config(['catalog.team_id' => null]);
        $this->team->delete();

        $this->get('/')->assertOk();
    });
});

describe('Grid scoping', function (): void {
    it('shows only active, grid-published articles of the catalog team', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Visible Product Alpha']);
        makeCatalogArticle($this->team, ['name' => 'Hidden Unpublished Product', 'show_in_product_grid' => false]);
        makeCatalogArticle($this->team, ['name' => 'Hidden Inactive Product', 'is_active' => false]);

        $otherOwner = User::factory()->withPersonalTeam()->create();
        makeCatalogArticle($otherOwner->personalTeam(), ['name' => 'Foreign Team Product']);

        livewire(CatalogHome::class)
            ->assertSee('Visible Product Alpha')
            ->assertDontSee('Hidden Unpublished Product')
            ->assertDontSee('Hidden Inactive Product')
            ->assertDontSee('Foreign Team Product');
    });

    it('serves no article detail route', function (): void {
        $published = makeCatalogArticle($this->team, ['name' => 'Published Detail Product']);

        $this->get('/articles/'.$published->getKey())->assertNotFound();
    });
});

describe('Search', function (): void {
    it('filters by name, SKU, and description with an empty state and clear affordance', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Steel Bracket Deluxe', 'sku' => 'SKU-111111', 'description' => 'A very sturdy bracket']);
        makeCatalogArticle($this->team, ['name' => 'Copper Pipe', 'sku' => 'SKU-222222', 'description' => 'Round copper tubing']);

        livewire(CatalogHome::class)
            ->set('search', 'steel bracket')
            ->assertSee('Steel Bracket Deluxe')
            ->assertDontSee('Copper Pipe')
            ->set('search', 'SKU-222222')
            ->assertSee('Copper Pipe')
            ->assertDontSee('Steel Bracket Deluxe')
            ->set('search', 'sturdy')
            ->assertSee('Steel Bracket Deluxe')
            ->set('search', 'zzz-no-match-zzz')
            ->assertSee('No articles found')
            ->assertSee('Clear search')
            ->call('clearSearch')
            ->assertSee('Steel Bracket Deluxe')
            ->assertSee('Copper Pipe');
    });

    it('does not surface unpublished articles through search', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Secret Draft Product', 'show_in_product_grid' => false]);

        livewire(CatalogHome::class)
            ->set('search', 'Secret Draft')
            ->assertDontSee('Secret Draft Product')
            ->assertSee('No articles found');
    });
});

describe('Category menu', function (): void {
    it('lists only tags attached to grid-visible articles and filters by category', function (): void {
        $visibleTag = Tag::factory()->create(['team_id' => $this->team->getKey(), 'name' => 'Fasteners', 'is_active' => true]);
        $orphanTag = Tag::factory()->create(['team_id' => $this->team->getKey(), 'name' => 'Lubricants', 'is_active' => true]);

        $tagged = makeCatalogArticle($this->team, ['name' => 'Tagged Hex Bolt']);
        $tagged->tags()->attach($visibleTag);

        $unpublished = makeCatalogArticle($this->team, ['name' => 'Unpublished Grease', 'show_in_product_grid' => false]);
        $unpublished->tags()->attach($orphanTag);

        makeCatalogArticle($this->team, ['name' => 'Untagged Washer']);

        livewire(CatalogHome::class)
            ->assertSee('Fasteners')
            ->assertDontSee('Lubricants')
            ->call('selectCategory', $visibleTag->getKey())
            ->assertSee('Tagged Hex Bolt')
            ->assertDontSee('Untagged Washer')
            ->call('selectCategory', null)
            ->assertSee('Untagged Washer');
    });

    it('combines category filter with search', function (): void {
        $tag = Tag::factory()->create(['team_id' => $this->team->getKey(), 'name' => 'Pipes', 'is_active' => true]);
        $a = makeCatalogArticle($this->team, ['name' => 'Copper Pipe Large']);
        $b = makeCatalogArticle($this->team, ['name' => 'Steel Pipe Large']);
        $a->tags()->attach($tag);
        $b->tags()->attach($tag);

        livewire(CatalogHome::class)
            ->call('selectCategory', $tag->getKey())
            ->set('search', 'Copper')
            ->assertSee('Copper Pipe Large')
            ->assertDontSee('Steel Pipe Large');
    });
});

describe('Pagination', function (): void {
    it('paginates at 50 per page and navigates between pages', function (): void {
        foreach (range(1, 50) as $i) {
            makeCatalogArticle($this->team, [
                'name' => sprintf('Bulk Product %03d', $i),
                'creator_id' => $this->owner->getKey(),
            ]);
        }
        makeCatalogArticle($this->team, [
            'name' => 'ZZZ Overflow Product',
            'creator_id' => $this->owner->getKey(),
        ]);

        livewire(CatalogHome::class)
            ->assertSee('Bulk Product 001')
            ->assertDontSee('ZZZ Overflow Product')
            ->assertSee('Showing 1-50 of 51 products')
            ->assertSee('Next')
            ->call('gotoPage', 2, 'page')
            ->assertSee('ZZZ Overflow Product')
            ->assertSee('Showing 51-51 of 51 products')
            ->assertDontSee('Bulk Product 001');
    });

    it('shows the result total even when everything fits on one page', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Only Product']);

        livewire(CatalogHome::class)
            ->assertSee('Showing 1-1 of 1 product')
            ->assertDontSee('Next');
    });
});

describe('Price display', function (): void {
    it('shows the list price formatted in the team default currency', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Priced Product', 'list_price' => '1250.0000']);

        livewire(CatalogHome::class)
            ->assertSee('Priced Product')
            ->assertSee('$ 1,250.00');
    });

    it('shows Price on request when list_price is null', function (): void {
        makeCatalogArticle($this->team, ['name' => 'Unpriced Product', 'list_price' => null]);

        livewire(CatalogHome::class)->assertSee('Price on request');
    });

    it('shows Price on request when show_price is disabled even with a list price', function (): void {
        makeCatalogArticle($this->team, [
            'name' => 'Hidden Price Product',
            'list_price' => '1250.0000',
            'show_price' => false,
        ]);

        livewire(CatalogHome::class)
            ->assertSee('Hidden Price Product')
            ->assertSee('Price on request')
            ->assertDontSee('$ 1,250.00');
    });

    it('shows the preferred supplier name above the product name', function (): void {
        $article = makeCatalogArticle($this->team, ['name' => 'Supplied Product']);
        $supplier = Company::factory()->supplier()->for($this->team)->create([
            'name' => 'PT. Elang Cakrawala Inti',
        ]);
        SupplierArticle::factory()->create([
            'article_id' => $article->getKey(),
            'supplier_id' => $supplier->getKey(),
            'is_preferred' => true,
            'is_active' => true,
        ]);

        livewire(CatalogHome::class)
            ->assertSee('Supplied Product')
            ->assertSee('PT. Elang Cakrawala Inti');
    });
});

describe('Stock visibility', function (): void {
    it('does not show stock availability badges on the public catalog', function (): void {
        $article = makeCatalogArticle($this->team, ['name' => 'Stocked Product']);
        SupplierArticle::factory()->create([
            'article_id' => $article->getKey(),
            'supplier_id' => Company::factory()->supplier()->for($this->team)->create()->getKey(),
            'available_quantity' => '25.0000',
            'is_active' => true,
        ]);

        livewire(CatalogHome::class)
            ->assertSee('Stocked Product')
            ->assertDontSee('In stock')
            ->assertDontSee('Out of stock');
    });
});

describe('Confidentiality', function (): void {
    it('never exposes supplier identities, costs, or article codes on public pages', function (): void {
        $article = makeCatalogArticle($this->team, [
            'name' => 'Public Facing Product',
            'code' => 'ARTSECRETCODE',
            'list_price' => '150.0000',
        ]);

        $supplier = Company::factory()->supplier()->for($this->team)->create(['name' => 'Very Secret Supplier GmbH']);
        SupplierArticle::factory()->create([
            'article_id' => $article->getKey(),
            'supplier_id' => $supplier->getKey(),
            'supplier_price' => '4242.4242',
            'available_quantity' => '99.0000',
            'is_active' => true,
            'last_quoted_price' => '3333.33',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Public Facing Product')
            ->assertDontSee('Very Secret Supplier')
            ->assertDontSee('ARTSECRETCODE')
            ->assertDontSee('4242.4242')
            ->assertDontSee('4,242.42')
            ->assertDontSee('3333.33')
            ->assertDontSee('3,333.33')
            ->assertDontSee('99.0000');
    });
});
