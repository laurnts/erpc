<?php

declare(strict_types=1);

use App\Actions\Media\AttachUploadedFiles;
use App\Models\Request;
use App\Models\User;
use App\Support\Media\DocumentPathGenerator;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

function makeUploaderStampFixture(string $name): string
{
    $directory = 'uploader-stamp-fixtures';
    $absoluteDir = storage_path('app/'.$directory);

    if (! is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0777, true);
    }

    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    file_put_contents($absoluteDir.'/'.$name, $pdf);

    return $directory.'/'.$name;
}

afterEach(function (): void {
    $dir = storage_path('app/uploader-stamp-fixtures');

    if (is_dir($dir)) {
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
});

it('stamps the authenticated staff uploader on attached media', function (): void {
    $admin = User::factory()->withPersonalTeam()->create();

    actingAs($admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($admin->personalTeam());

    $request = Request::factory()->create();
    $file = makeUploaderStampFixture('proof.pdf');

    $attached = (new AttachUploadedFiles)->execute($request, [$file], 'attachments', 'uploader-stamp-fixtures');

    expect($attached)->toHaveCount(1);

    $media = $attached[0];

    expect($media->getCustomProperty('uploader_id'))->toBe($admin->id)
        ->and($media->getCustomProperty('uploader_actor_type'))->toBe('staff');

    $request->clearMediaCollection('attachments');
});

it('stamps a system uploader with no id when nothing is authenticated', function (): void {
    $request = Request::factory()->create();
    $file = makeUploaderStampFixture('system.pdf');

    $attached = (new AttachUploadedFiles)->execute($request, [$file], 'attachments', 'uploader-stamp-fixtures');

    expect($attached)->toHaveCount(1);

    $media = $attached[0];

    expect($media->getCustomProperty('uploader_actor_type'))->toBe('system')
        ->and($media->getCustomProperty('uploader_id'))->toBeNull();

    $request->clearMediaCollection('attachments');
});

it('keeps the path stamps alongside the uploader stamps', function (): void {
    $admin = User::factory()->withPersonalTeam()->create();

    actingAs($admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($admin->personalTeam());

    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-0800',
        'created_at' => '2026-06-01 09:00:00',
    ]);
    $file = makeUploaderStampFixture('stamped.pdf');

    $attached = (new AttachUploadedFiles)->execute($request, [$file], 'attachments', 'uploader-stamp-fixtures');

    expect($attached)->toHaveCount(1);

    $media = $attached[0];
    $expectedPrefix = 'documents/team-'.$request->team_id.'/2026/REQ-2026-0800/request-attachments';

    expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toBe($expectedPrefix)
        ->and($media->getCustomProperty('uploader_id'))->toBe($admin->id)
        ->and($media->getCustomProperty('uploader_actor_type'))->toBe('staff');

    $request->clearMediaCollection('attachments');
});

it('lets a caller-supplied uploader stamp win over the resolved one', function (): void {
    $admin = User::factory()->withPersonalTeam()->create();

    actingAs($admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($admin->personalTeam());

    $request = Request::factory()->create();
    $file = makeUploaderStampFixture('override.pdf');

    $attached = (new AttachUploadedFiles)->execute($request, [$file], 'attachments', 'uploader-stamp-fixtures', [
        'uploader_id' => 999,
        'uploader_actor_type' => 'buyer',
        'source' => 'import',
    ]);

    expect($attached)->toHaveCount(1);

    $media = $attached[0];

    expect($media->getCustomProperty('uploader_id'))->toBe(999)
        ->and($media->getCustomProperty('uploader_actor_type'))->toBe('buyer')
        ->and($media->getCustomProperty('source'))->toBe('import');

    $request->clearMediaCollection('attachments');
});
