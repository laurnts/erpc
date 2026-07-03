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

test('the QE form still saves a quotation evaluation', function (): void {
    livewire(QuotationEvaluationForm::class, ['request' => $this->request])
        ->call('save')
        ->assertHasNoErrors();

    expect(QuotationEvaluation::query()->where('request_id', $this->request->getKey())->exists())->toBeTrue();
});
