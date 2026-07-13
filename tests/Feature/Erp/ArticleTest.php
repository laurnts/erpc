<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Company;
use App\Models\Tag;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

// Model Tests
test('article can be created via factory', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'code' => 'ART-0001',
        'name' => 'Test Article',
        'description' => 'A test article',
        'creator_id' => $this->user->id,
    ]);

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->code)->toBe('ART-0001')
        ->and($article->name)->toBe('Test Article')
        ->and($article->team_id)->toBe($this->user->personalTeam()->id)
        ->and($article->creator_id)->toBe($this->user->id);
});

test('article belongs to team', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create();

    expect($article->team)->toBeInstanceOf(Team::class)
        ->and($article->team->id)->toBe($this->user->personalTeam()->id);
});

test('article belongs to creator', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    expect($article->creator)->toBeInstanceOf(User::class)
        ->and($article->creator->id)->toBe($this->user->id);
});

test('article has default values', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'unit' => 'pcs',
        'is_active' => true,
    ]);

    expect($article->unit)->toBe(\App\Enums\Unit::PCS)
        ->and($article->is_active)->toBeTrue();
});

test('article code is unique per team', function (): void {
    Article::factory()->for($this->user->personalTeam())->create(['code' => 'UNIQUE-001']);

    expect(fn () => Article::factory()->for($this->user->personalTeam())->create(['code' => 'UNIQUE-001']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same article code can exist in different teams', function (): void {
    $user2 = User::factory()->withPersonalTeam()->create();

    $article1 = Article::factory()->for($this->user->personalTeam())->create(['code' => 'SHARED-001']);
    $article2 = Article::factory()->for($user2->personalTeam())->create(['code' => 'SHARED-001']);

    expect($article1->id)->not->toBe($article2->id)
        ->and($article1->code)->toBe($article2->code)
        ->and($article1->team_id)->not->toBe($article2->team_id);
});

test('article can be deactivated', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create(['is_active' => true]);

    $article->update(['is_active' => false]);

    expect($article->fresh()->is_active)->toBeFalse();
});

test('article factory creates valid article', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create();

    expect($article->code)->not->toBeEmpty()
        ->and($article->name)->not->toBeEmpty()
        ->and($article->team_id)->not->toBeNull()
        ->and($article->team_id)->toBe($this->user->personalTeam()->id);
});

test('inactive factory state works', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->inactive()->create();

    expect($article->is_active)->toBeFalse();
});

test('article can have tax code', function (): void {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $article = Article::factory()
        ->for($this->user->personalTeam())
        ->create([
            'default_tax_code_id' => $taxCode->id,
            'creator_id' => $this->user->id,
        ]);

    expect($article->defaultTaxCode)->toBeInstanceOf(TaxCode::class)
        ->and($article->defaultTaxCode->id)->toBe($taxCode->id);
});

test('article can have custom attributes', function (): void {
    $attributes = [
        'color' => 'blue',
        'size' => 'L',
        'weight' => 2.5,
    ];

    $article = Article::factory()
        ->for($this->user->personalTeam())
        ->withAttributes($attributes)
        ->create();

    expect($article->attributes)->toBe($attributes)
        ->and($article->attributes['color'])->toBe('blue')
        ->and($article->attributes['size'])->toBe('L')
        ->and($article->attributes['weight'])->toBe(2.5);
});

test('with common attributes factory state works', function (): void {
    $article = Article::factory()
        ->for($this->user->personalTeam())
        ->withCommonAttributes()
        ->create();

    expect($article->attributes)->toHaveKey('color')
        ->and($article->attributes)->toHaveKey('size')
        ->and($article->attributes)->toHaveKey('weight');
});

test('article observer sets team and creator on create', function (): void {
    $article = Article::create([
        'code' => 'OBSERVER-001',
        'name' => 'Observer Test',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    expect($article->team_id)->toBe($this->user->personalTeam()->id)
        ->and($article->creator_id)->toBe($this->user->id);
});

test('article observer auto-generates code if not provided', function (): void {
    // Manually test the observer's code generation
    $observer = new \App\Observers\ArticleObserver;

    // Create first article without code - manually trigger observer
    $article1 = new Article([
        'name' => 'First Article',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);
    $observer->creating($article1);
    $article1->saveQuietly();

    expect($article1->code)->toBe('ART-0001');

    // Create second article without code - manually trigger observer
    $article2 = new Article([
        'name' => 'Second Article',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);
    $observer->creating($article2);
    $article2->saveQuietly();

    expect($article2->code)->toBe('ART-0002');
});

test('article code auto-generation continues after gap', function (): void {
    // Create articles with specific codes using factory
    Article::factory()->for($this->user->personalTeam())->create([
        'code' => 'ART-0001',
        'creator_id' => $this->user->id,
    ]);
    Article::factory()->for($this->user->personalTeam())->create([
        'code' => 'ART-0005',
        'creator_id' => $this->user->id,
    ]);

    // Manually test the observer's code generation
    $observer = new \App\Observers\ArticleObserver;

    // Next auto-generated code should be ART-0006
    $newArticle = new Article([
        'name' => 'New Article',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);
    $observer->creating($newArticle);
    $newArticle->saveQuietly();

    expect($newArticle->code)->toBe('ART-0006');
});

test('article display name includes code and name', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'code' => 'ART-0001',
        'name' => 'Test Product',
    ]);

    expect($article->display_name)->toBe('[ART-0001] Test Product');
});

test('article can be soft deleted', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create();

    $article->delete();

    expect($article->trashed())->toBeTrue()
        ->and(Article::withTrashed()->find($article->id))->not->toBeNull()
        ->and(Article::find($article->id))->toBeNull();
});

test('article can be restored', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create();
    $article->delete();

    $article->restore();

    expect($article->trashed())->toBeFalse()
        ->and(Article::find($article->id))->not->toBeNull();
});

test('article can have tags', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $tag = Tag::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $article->attachTags([$tag->id]);

    expect($article->tags)->toHaveCount(1)
        ->and($article->hasTag($tag))->toBeTrue();
});

test('article can sync multiple tags', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $tags = Tag::factory()
        ->count(3)
        ->for($this->user->personalTeam())
        ->create(['creator_id' => $this->user->id]);

    $article->syncTags($tags->pluck('id')->toArray());

    expect($article->tags)->toHaveCount(3);

    // Sync with only one tag
    $article->syncTags([$tags->first()->id]);

    expect($article->fresh()->tags)->toHaveCount(1);
});

test('article can detach tags', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $tag = Tag::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $article->attachTags([$tag->id]);
    expect($article->tags)->toHaveCount(1);

    $article->detachTags([$tag->id]);
    expect($article->fresh()->tags)->toHaveCount(0);
});

test('with unit factory state works', function (): void {
    $article = Article::factory()
        ->for($this->user->personalTeam())
        ->withUnit('kg')
        ->create();

    expect($article->unit)->toBe(\App\Enums\Unit::KG);
});

test('with sku factory state works', function (): void {
    $article = Article::factory()
        ->for($this->user->personalTeam())
        ->withSku('SKU-123456')
        ->create();

    expect($article->sku)->toBe('SKU-123456');
});

// Supplier Assignment Tests
test('article can have suppliers assigned', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $supplier = Company::factory()
        ->for($this->user->personalTeam())
        ->supplier()
        ->create([
            'creator_id' => $this->user->id,
        ]);

    $article->suppliers()->attach($supplier->id);

    expect($article->suppliers)->toHaveCount(1)
        ->and($article->suppliers->first()->id)->toBe($supplier->id)
        ->and($article->suppliers->first()->is_supplier)->toBeTrue();
});

test('article can sync multiple suppliers', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $suppliers = Company::factory()
        ->count(3)
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    $article->suppliers()->sync($suppliers->pluck('id')->toArray());

    expect($article->suppliers)->toHaveCount(3);

    // Sync with only one supplier
    $article->suppliers()->sync([$suppliers->first()->id]);

    expect($article->fresh()->suppliers)->toHaveCount(1);
});

test('article can detach suppliers', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $supplier = Company::factory()
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    $article->suppliers()->attach($supplier->id);
    expect($article->suppliers)->toHaveCount(1);

    $article->suppliers()->detach($supplier->id);
    expect($article->fresh()->suppliers)->toHaveCount(0);
});

test('article suppliers are filtered by is_supplier and team', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    // Create supplier in same team
    $supplier = Company::factory()
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    // Create non-supplier company in same team
    $nonSupplier = Company::factory()
        ->for($this->user->personalTeam())
        ->create(['creator_id' => $this->user->id]);

    // Create supplier in different team
    $otherTeam = Team::factory()->create();
    $otherTeamSupplier = Company::factory()
        ->for($otherTeam)
        ->supplier()
        ->create();

    $article->suppliers()->attach($supplier->id);

    // Verify only the correct supplier is attached
    expect($article->suppliers)->toHaveCount(1)
        ->and($article->suppliers->first()->id)->toBe($supplier->id)
        ->and($article->suppliers->first()->is_supplier)->toBeTrue()
        ->and($article->suppliers->first()->team_id)->toBe($this->user->personalTeam()->id);
});

test('article can be created with suppliers via form data sync', function (): void {
    $suppliers = Company::factory()
        ->count(2)
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    // Simulate form data with suppliers
    $article = Article::create([
        'name' => 'Test Article with Suppliers',
        'unit' => 'pcs',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    // Sync suppliers as done in ItemsRelationManager
    $article->suppliers()->sync($suppliers->pluck('id')->toArray());

    expect($article->suppliers)->toHaveCount(2)
        ->and($article->suppliers->pluck('id')->toArray())->toBe($suppliers->pluck('id')->toArray());
});

test('article supplier sync handles empty array', function (): void {
    $article = Article::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    $supplier = Company::factory()
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    $article->suppliers()->attach($supplier->id);
    expect($article->suppliers)->toHaveCount(1);

    // Sync with empty array (simulating no suppliers selected)
    $article->suppliers()->sync([]);

    expect($article->fresh()->suppliers)->toHaveCount(0);
});

// Product Images Tests
test('article accepts images into the product_images collection', function (): void {
    Storage::fake('public');

    $article = Article::factory()->for($this->user->personalTeam())->create();

    $article->addMedia(UploadedFile::fake()->image('front.jpg', 600, 600))
        ->toMediaCollection('product_images');

    expect($article->getMedia('product_images'))->toHaveCount(1)
        ->and($article->getFirstMedia('product_images')->collection_name)->toBe('product_images');
});

test('product images generate thumb and medium conversions', function (): void {
    Storage::fake('public');

    $article = Article::factory()->for($this->user->personalTeam())->create();

    $article->addMedia(UploadedFile::fake()->image('front.jpg', 600, 600))
        ->toMediaCollection('product_images');

    $media = $article->getFirstMedia('product_images');

    expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('medium'))->toBeTrue();
});

test('product images keep their upload order and expose a primary image', function (): void {
    Storage::fake('public');

    $article = Article::factory()->for($this->user->personalTeam())->create();

    $article->addMedia(UploadedFile::fake()->image('first.jpg', 600, 600))
        ->toMediaCollection('product_images');
    $article->addMedia(UploadedFile::fake()->image('second.jpg', 600, 600))
        ->toMediaCollection('product_images');

    $images = $article->getMedia('product_images');

    expect($images)->toHaveCount(2)
        ->and($images->first()->file_name)->toBe('first.jpg')
        ->and($article->getFirstMedia('product_images')->file_name)->toBe('first.jpg');
});

test('product_images collection rejects non-image files', function (): void {
    Storage::fake('public');

    $article = Article::factory()->for($this->user->personalTeam())->create();

    $article->addMedia(UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'))
        ->toMediaCollection('product_images');
})->throws(FileUnacceptableForCollection::class);

test('article create form uploads product images through the Images section', function (): void {
    Storage::fake('public');

    $unitOfMeasureId = \App\Models\UnitOfMeasure::query()
        ->where('team_id', $this->user->personalTeam()->id)
        ->value('id') ?? \App\Models\UnitOfMeasure::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
            'code' => 'pcs',
        ])->id;

    $supplier = Company::factory()
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    \Pest\Livewire\livewire(\App\Filament\Resources\ArticleResource\Pages\CreateArticle::class)
        ->fillForm([
            'name' => 'Imaged Article',
            'unit_of_measure_id' => $unitOfMeasureId,
            'suppliers' => [$supplier->id],
            'product_images' => [UploadedFile::fake()->image('front.jpg', 600, 600)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('name', 'Imaged Article')->firstOrFail();

    expect($article->getMedia('product_images'))->toHaveCount(1);
});

test('article create form requires at least one supplier', function (): void {
    $unitOfMeasureId = \App\Models\UnitOfMeasure::query()
        ->where('team_id', $this->user->personalTeam()->id)
        ->value('id') ?? \App\Models\UnitOfMeasure::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
            'code' => 'pcs',
        ])->id;

    \Pest\Livewire\livewire(\App\Filament\Resources\ArticleResource\Pages\CreateArticle::class)
        ->fillForm([
            'name' => 'Supplierless Article',
            'unit_of_measure_id' => $unitOfMeasureId,
        ])
        ->call('create')
        ->assertHasFormErrors(['suppliers' => 'required']);

    expect(Article::query()->where('name', 'Supplierless Article')->exists())->toBeFalse();
});

test('article create form attaches the selected suppliers', function (): void {
    $unitOfMeasureId = \App\Models\UnitOfMeasure::query()
        ->where('team_id', $this->user->personalTeam()->id)
        ->value('id') ?? \App\Models\UnitOfMeasure::factory()->create([
            'team_id' => $this->user->personalTeam()->id,
            'code' => 'pcs',
        ])->id;

    $suppliers = Company::factory()
        ->count(2)
        ->for($this->user->personalTeam())
        ->supplier()
        ->create(['creator_id' => $this->user->id]);

    \Pest\Livewire\livewire(\App\Filament\Resources\ArticleResource\Pages\CreateArticle::class)
        ->fillForm([
            'name' => 'Supplied Article',
            'unit_of_measure_id' => $unitOfMeasureId,
            'suppliers' => $suppliers->pluck('id')->toArray(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('name', 'Supplied Article')->firstOrFail();

    expect($article->suppliers)->toHaveCount(2);
});

test('factory state attaches product images', function (): void {
    Storage::fake('public');

    $article = Article::factory()
        ->for($this->user->personalTeam())
        ->withProductImages(2)
        ->create();

    expect($article->getMedia('product_images'))->toHaveCount(2);
});
