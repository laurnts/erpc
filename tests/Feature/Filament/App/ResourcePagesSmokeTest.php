<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->user->assignRole('superadmin');
    actingAs($this->user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->user->personalTeam());
});

/**
 * Discovers resource page classes by prefix so every new resource is covered
 * automatically without registering it here.
 *
 * @return array<string, class-string>
 */
function smokeResourcePages(string $prefix): array
{
    $pages = [];

    foreach (glob(dirname(__DIR__, 4).'/app/Filament/Resources/*/Pages/*.php') ?: [] as $path) {
        $class = basename($path, '.php');

        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $resourceDir = basename(dirname($path, 2));
        $pages[$resourceDir.'\\'.$class] = 'App\\Filament\\Resources\\'.$resourceDir.'\\Pages\\'.$class;
    }

    ksort($pages);

    return $pages;
}

/**
 * Mounting a list page builds the full table (columns, filters, default sort)
 * and runs its query, so schema/SQL mistakes surface even on an empty table.
 */
test('list page renders: :dataset', function (string $page): void {
    livewire($page)->assertOk();
})->with(smokeResourcePages('List'));

/**
 * Mounting a create page instantiates the entire form schema, catching broken
 * component definitions, bad relationship references, and invalid options.
 */
test('create page renders: :dataset', function (string $page): void {
    /** @var class-string<\Filament\Resources\Pages\CreateRecord> $page */
    if (! $page::getResource()::canCreate()) {
        $this->markTestSkipped('creation is disabled for this resource');
    }

    livewire($page)->assertOk();
})->with(smokeResourcePages('Create'));

/**
 * Submitting an empty create form must be handled by the validation layer
 * (form errors or a clean create) — never by a database-level exception,
 * which would mean a required column is missing its form validation.
 */
test('empty create submission is handled by validation: :dataset', function (string $page): void {
    /** @var class-string<\Filament\Resources\Pages\CreateRecord> $page */
    if (! $page::getResource()::canCreate()) {
        $this->markTestSkipped('creation is disabled for this resource');
    }

    livewire($page)->call('create')->assertOk();
})->with(smokeResourcePages('Create'));
