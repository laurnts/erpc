<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Filament\Resources\AcceptanceReportResource\Pages\ViewAcceptanceReport;
use App\Models\AcceptanceReport;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Media\DocumentPathGenerator;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->request = Request::factory()->recycle($this->team)->create();
    RequestItem::factory()->for($this->request)->create(['item_type' => ItemType::SERVICE]);
    $this->report = AcceptanceReport::factory()->recycle($this->team)->for($this->request)->create();

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

it('stamps an uploaded acceptance report attachment with a v3 document path', function (): void {
    livewire(ViewAcceptanceReport::class, ['record' => $this->report->getKey()])
        ->assertOk()
        ->callAction('edit', data: [
            'attachments' => [UploadedFile::fake()->createWithContent(
                'attachment.pdf',
                "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
            )],
        ])
        ->assertHasNoActionErrors();

    $media = $this->report->refresh()->getFirstMedia('attachments');

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});
