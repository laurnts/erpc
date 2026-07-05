<?php

declare(strict_types=1);

use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->create();
    $this->quote = SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->currency)
        ->create();

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

it('attaches the uploaded quotation document to the supplier quote', function (): void {
    livewire(SupplierQuotesRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('quotation')->table($this->quote), [
            'quotation_file' => [UploadedFile::fake()->createWithContent(
                'real-quote.pdf',
                "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
            )],
        ])
        ->assertHasNoActionErrors();

    expect($this->quote->refresh()->getMedia('quotation'))->toHaveCount(1);
});

it('notifies with an error instead of crashing when the file content is not an accepted type', function (): void {
    livewire(SupplierQuotesRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('quotation')->table($this->quote), [
            'quotation_file' => [UploadedFile::fake()->createWithContent('fake.pdf', 'not really a pdf')],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('File rejected');

    expect($this->quote->refresh()->getMedia('quotation'))->toHaveCount(0);
});
