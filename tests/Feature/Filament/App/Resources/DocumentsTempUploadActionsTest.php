<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Filament\Resources\ProfitAndLossResource\Pages\ViewProfitAndLoss;
use App\Filament\Resources\QuotationEvaluationResource\Pages\ViewQuotationEvaluation;
use App\Filament\Resources\SupplierOrderApprovals\Pages\ViewSupplierOrderApproval;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use App\Support\Media\DocumentPathGenerator;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

/**
 * These "documents" collection upload actions were converted from raw
 * addMedia() calls (staging under the old "documents-temp" literal) to
 * App\Actions\Media\AttachUploadedFiles, which stamps v3 paths and enforces
 * the traversal guard. The custom "Document Name" field is preserved via a
 * post-attach rename, since the action itself doesn't carry a name param.
 */
function documentsTempPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
    );
}

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);

    $this->request = Request::factory()->recycle($this->team)->create();
});

it('stamps a v3 path and preserves the custom name for a quotation evaluation document', function (): void {
    $qe = QuotationEvaluation::factory()->forRequest($this->request)->create(['creator_id' => $this->user->getKey()]);

    livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
        ->assertOk()
        ->callAction('uploadDocument', data: [
            'document' => [documentsTempPdf('qe-doc.pdf')],
            'name' => 'My QE Document',
        ])
        ->assertHasNoActionErrors();

    $media = $qe->refresh()->getFirstMedia('documents');

    expect($media)->not->toBeNull()
        ->and($media->name)->toBe('My QE Document')
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});

it('stamps a v3 path for a profit and loss document', function (): void {
    $pnl = ProfitAndLoss::factory()->forRequest($this->request)->create(['creator_id' => $this->user->getKey()]);

    livewire(ViewProfitAndLoss::class, ['record' => $pnl->getKey()])
        ->assertOk()
        ->callAction('uploadDocument', data: [
            'document' => [documentsTempPdf('pnl-doc.pdf')],
        ])
        ->assertHasNoActionErrors();

    $media = $pnl->refresh()->getFirstMedia('documents');

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});

it('keeps the file extension in the display name when no custom name is supplied', function (): void {
    $qe = QuotationEvaluation::factory()->forRequest($this->request)->create(['creator_id' => $this->user->getKey()]);

    livewire(ViewQuotationEvaluation::class, ['record' => $qe->getKey()])
        ->assertOk()
        ->callAction('uploadDocument', data: [
            'document' => [documentsTempPdf('unnamed-doc.pdf')],
        ])
        ->assertHasNoActionErrors();

    $media = $qe->refresh()->getFirstMedia('documents');

    // Pre-convergence behavior: name defaults to the staged file's basename,
    // extension included (Spatie's default would strip it).
    expect($media)->not->toBeNull()
        ->and($media->name)->toBe($media->file_name)
        ->and($media->name)->toEndWith('.pdf');
});

it('stamps a v3 path for a supplier order document uploaded from the view page', function (): void {
    $order = SupplierOrder::factory()->recycle($this->team)->for($this->request)->create(['status' => OrderStatus::APPROVED]);

    livewire(ViewSupplierOrderApproval::class, ['record' => $order->getKey()])
        ->assertOk()
        ->callAction('uploadDocument', data: [
            'document' => [documentsTempPdf('order-doc.pdf')],
        ])
        ->assertHasNoActionErrors();

    $media = $order->refresh()->getFirstMedia('documents');

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});
