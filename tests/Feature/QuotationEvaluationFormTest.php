<?php

declare(strict_types=1);

use App\Livewire\QuotationEvaluationForm;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->request = Request::factory()
        ->for($this->team)
        ->create(['creator_id' => $this->user->getKey()]);

    // QE is only available for requests with goods items
    \App\Models\RequestItem::factory()->recycle($this->request)->create();
});

test('the QE form renders the approval selects without an inline create-key-account button', function (): void {
    livewire(QuotationEvaluationForm::class, ['request' => $this->request])
        ->assertOk()
        ->assertSee('Prepared By')
        ->assertSee('Central Purchasing')
        ->assertSee('Save QE')
        ->assertDontSee('Add new key account')
        ->assertDontSee('Create Key Account');
});

test('the inline key-account creation actions no longer exist on the component', function (): void {
    $component = livewire(QuotationEvaluationForm::class, ['request' => $this->request]);

    expect(method_exists($component->instance(), 'createKeyAccount'))->toBeFalse()
        ->and(method_exists($component->instance(), 'saveNewKeyAccount'))->toBeFalse()
        ->and(method_exists($component->instance(), 'openKeyAccountForm'))->toBeFalse();
});

test('the QE form does not nest a form element inside the Filament modal form', function (): void {
    $html = livewire(QuotationEvaluationForm::class, ['request' => $this->request])->html();

    expect($html)->not->toContain('<form');
});

test('the QE form still saves a quotation evaluation', function (): void {
    livewire(QuotationEvaluationForm::class, ['request' => $this->request])
        ->call('save')
        ->assertHasNoErrors();

    expect(QuotationEvaluation::query()->where('request_id', $this->request->getKey())->exists())->toBeTrue();
});

/**
 * Regression: mounting the form for a request with no goods items takes the
 * early-return branch, which redirects back to the request. That redirect was
 * built with a hardcoded route() call naming a panel this app does not have
 * ('admin'), so it threw RouteNotFoundException; correcting the panel then
 * exposed a second fault, because the app panel is multi-tenant and a bare
 * route() cannot supply the {tenant} segment. It now goes through
 * RequestResource::getUrl(), which resolves panel and tenant itself.
 */
test('mounting the QE form for a request with no goods items redirects instead of throwing', function (): void {
    $serviceOnly = Request::factory()
        ->for($this->team)
        ->create(['creator_id' => $this->user->getKey()]);

    expect($serviceOnly->canCreateQuotationEvaluation())->toBeFalse();

    $expected = \App\Filament\Resources\RequestResource::getUrl('view', ['record' => $serviceOnly]);

    expect($expected)->toContain((string) $this->team->getKey());

    livewire(QuotationEvaluationForm::class, ['request' => $serviceOnly])
        ->assertRedirect($expected);
});
