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
use App\Support\Media\DocumentPathGenerator;
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

it('stamps the staff-uploaded quotation document with a v3 document path', function (): void {
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

    $media = $this->quote->refresh()->getFirstMedia('quotation');

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});

it('rejects a path traversal attempt outside the supplier quote staging directory', function (): void {
    $legit = SupplierQuote::QUOTATION_UPLOAD_DIRECTORY;
    $uploadDir = storage_path('app/'.$legit);
    \Illuminate\Support\Facades\File::ensureDirectoryExists($uploadDir);
    $legitFile = $uploadDir.'/legit-'.uniqid().'.pdf';
    file_put_contents($legitFile, '%PDF-1.4 test');

    app(\App\Actions\Media\AttachUploadedFiles::class)->execute(
        $this->quote,
        [
            '../../.env',
            $legit.'/../../../.env',
        ],
        'quotation',
        $legit,
    );

    expect($this->quote->refresh()->getMedia('quotation'))->toHaveCount(0);

    @unlink($legitFile);
});
