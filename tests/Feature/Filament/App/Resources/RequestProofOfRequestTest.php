<?php

declare(strict_types=1);

use App\Enums\RequestPriority;
use App\Filament\Resources\RequestResource\Pages\CreateRequest;
use App\Models\Company;
use App\Models\Request;
use App\Models\User;
use App\Support\Media\DocumentPathGenerator;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

/**
 * Stage a real file inside the proof upload directory so AttachUploadedFiles
 * can resolve its realpath (Filament FileUpload state is just the relative path).
 */
function stageProofFile(string $name): string
{
    $relativePath = Request::PROOF_UPLOAD_DIRECTORY.'/'.$name;
    $absolutePath = storage_path('app/'.$relativePath);

    if (! is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0755, true);
    }

    file_put_contents($absolutePath, '%PDF-1.4 proof');

    return $relativePath;
}

describe('Staff request proof of request', function (): void {
    it('rejects a staff request created without a proof file', function (): void {
        livewire(CreateRequest::class)
            ->fillForm([
                'buyer_id' => $this->buyer->getKey(),
                'title' => 'Emailed request without proof',
                'priority' => RequestPriority::NORMAL->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['proof_files']);

        expect(Request::query()->where('title', 'Emailed request without proof')->exists())->toBeFalse();
    });

    it('creates a staff request and attaches the proof to the attachments collection', function (): void {
        $proofPath = stageProofFile('buyer-email.pdf');

        livewire(CreateRequest::class)
            ->fillForm([
                'buyer_id' => $this->buyer->getKey(),
                'title' => 'Emailed request with proof',
                'priority' => RequestPriority::NORMAL->value,
                'proof_files' => [$proofPath],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = Request::query()->where('title', 'Emailed request with proof')->first();

        expect($record)->not->toBeNull()
            ->and($record->submission_method)->toBeNull()
            ->and($record->getMedia('attachments'))->toHaveCount(1)
            ->and($record->hasProofOfRequest())->toBeTrue();

        $media = $record->getFirstMedia('attachments');

        expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
            ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$record->team_id.'/');
    });
});
