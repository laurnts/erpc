<?php

declare(strict_types=1);

use App\Filament\Imports\ArticleImporter;
use App\Models\Article;
use App\Models\Company;
use App\Models\SupplierArticle;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->personalTeam();
});

/**
 * @param  array<string, string>  $columnMap
 * @param  array<string, mixed>  $row
 */
function importArticleRow(int $teamId, int $userId, array $columnMap, array $row): void
{
    $import = Import::query()->create([
        'team_id' => $teamId,
        'user_id' => $userId,
        'file_name' => 'articles.csv',
        'file_path' => 'articles.csv',
        'importer' => ArticleImporter::class,
        'total_rows' => 1,
    ]);

    $importer = new ArticleImporter($import, $columnMap, []);
    $importer($row);
}

test('article import links supplier by name with pivot data', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create([
        'name' => 'CV. Kawan Pena',
        'code' => 'CMP-0007',
    ]);

    importArticleRow($this->team->id, $this->user->id, [
        'name' => 'name',
        'supplier_name' => 'supplier_name',
        'lead_time_days' => 'lead_time_days',
        'supplier_sku' => 'supplier_sku',
    ], [
        'name' => 'meja makan kayu jati',
        'supplier_name' => 'CV. Kawan Pena',
        'lead_time_days' => '14',
        'supplier_sku' => 'SUP-001',
    ]);

    $article = Article::query()->where('team_id', $this->team->id)->where('name', 'meja makan kayu jati')->first();

    expect($article)->not->toBeNull()
        ->and($article->suppliers()->whereKey($supplier->id)->exists())->toBeTrue();

    $link = SupplierArticle::query()
        ->where('article_id', $article->id)
        ->where('supplier_id', $supplier->id)
        ->first();

    expect($link)->not->toBeNull()
        ->and($link->lead_time_days)->toBe(14)
        ->and($link->supplier_sku)->toBe('SUP-001')
        ->and($link->is_active)->toBeTrue()
        ->and($link->is_preferred)->toBeFalse();
});

test('article import links supplier by code', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create([
        'name' => 'CV. Kawan Pena',
        'code' => 'CMP-0007',
    ]);

    importArticleRow($this->team->id, $this->user->id, [
        'name' => 'name',
        'supplier_code' => 'supplier_code',
    ], [
        'name' => 'Kursi kerja',
        'supplier_code' => 'CMP-0007',
    ]);

    $article = Article::query()->where('name', 'Kursi kerja')->first();

    expect($article->suppliers()->whereKey($supplier->id)->exists())->toBeTrue();
});

test('article import can mark supplier as preferred', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create(['name' => 'Preferred Vendor']);
    $otherSupplier = Company::factory()->supplier()->for($this->team)->create(['name' => 'Other Vendor']);

    $article = Article::factory()->for($this->team)->create(['name' => 'Shared Article']);
    SupplierArticle::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $otherSupplier->id,
        'is_preferred' => true,
    ]);

    importArticleRow($this->team->id, $this->user->id, [
        'name' => 'name',
        'supplier_name' => 'supplier_name',
        'supplier_is_preferred' => 'supplier_is_preferred',
    ], [
        'name' => 'Shared Article',
        'supplier_name' => 'Preferred Vendor',
        'supplier_is_preferred' => 'Yes',
    ]);

    $preferredLink = SupplierArticle::query()
        ->where('article_id', $article->id)
        ->where('supplier_id', $supplier->id)
        ->first();

    $otherLink = SupplierArticle::query()
        ->where('article_id', $article->id)
        ->where('supplier_id', $otherSupplier->id)
        ->first();

    expect($preferredLink)->not->toBeNull()
        ->and($preferredLink->is_preferred)->toBeTrue()
        ->and($otherLink->is_preferred)->toBeFalse();
});

test('article import fails when supplier cannot be resolved', function (): void {
    importArticleRow($this->team->id, $this->user->id, [
        'name' => 'name',
        'supplier_name' => 'supplier_name',
    ], [
        'name' => 'Unknown Supplier Article',
        'supplier_name' => 'Missing Supplier',
    ]);
})->throws(ValidationException::class);

test('article import fails when a new article has no supplier', function (): void {
    try {
        importArticleRow($this->team->id, $this->user->id, [
            'name' => 'name',
        ], [
            'name' => 'Standalone Article',
        ]);

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('supplier');
    }

    expect(Article::query()->where('name', 'Standalone Article')->exists())->toBeFalse();
});

test('article import without supplier columns still updates an existing article', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();
    $article = Article::factory()->for($this->team)->create(['name' => 'Existing Article']);
    SupplierArticle::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
    ]);

    importArticleRow($this->team->id, $this->user->id, [
        'name' => 'name',
        'sku' => 'sku',
    ], [
        'name' => 'Existing Article',
        'sku' => 'UPDATED-SKU',
    ]);

    expect($article->refresh()->sku)->toBe('UPDATED-SKU')
        ->and($article->suppliers()->count())->toBe(1);
});

test('article import links supplier from csv header when column is not mapped', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create([
        'name' => 'ARRA',
        'code' => 'CMP-0021',
    ]);

    importArticleRow($this->team->id, $this->user->id, [
        'name' => 'Name',
        'sku' => 'SKU',
    ], [
        'Name' => 'ARRASO UNMAPPED SUPPLIER TEST',
        'SKU' => 'APW-TEST',
        'Supplier Code' => 'CMP-0021',
    ]);

    $article = Article::query()->where('name', 'ARRASO UNMAPPED SUPPLIER TEST')->first();

    expect($article)->not->toBeNull()
        ->and(SupplierArticle::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $supplier->id)
            ->exists())->toBeTrue();
});
