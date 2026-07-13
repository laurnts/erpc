<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\ActorType;
use App\Enums\NoteVisibility;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\User;
use App\Policies\RequestNotePolicy;
use App\Services\Portal\SupplierPortalContext;
use App\Support\ActivityLogContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

/**
 * Note + file composer pinned to the bottom of every request timeline surface.
 *
 * The composer is audience-aware but never trusts its rendering context for
 * authorization: the acting party (and thus the note's author_actor_type and
 * the visibility a portal may post) is resolved server-side from the
 * authenticated guard via {@see ActivityLogContext}, then gated by
 * {@see RequestNotePolicy}. Staff/admin pick a visibility (internal by
 * default, or shared with the buyer / a specific supplier); a buyer posts a
 * Buyer-visibility note automatically, and a supplier posts a
 * Supplier-visibility note auto-scoped to their own company.
 */
final class RequestNoteComposer extends BaseLivewireComponent
{
    use WithFileUploads;

    public Request $request;

    public ?string $body = null;

    /**
     * Staff-only visibility choice. Ignored for portal parties, whose
     * visibility is forced server-side.
     */
    public string $visibility = NoteVisibility::Internal->value;

    /**
     * Staff-only supplier target when sharing with a single supplier.
     */
    public ?int $supplierCompanyId = null;

    /**
     * Temporary uploaded files (Livewire), converted to note attachments on
     * submit and stamped with the uploader through {@see AttachUploadedFiles}.
     *
     * @var array<int, mixed>
     */
    public array $attachments = [];

    public function mount(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * The party looking at (and posting to) this timeline, resolved from the
     * authenticated guard rather than any client-supplied value.
     */
    #[Computed]
    public function actorType(): ActorType
    {
        return ActivityLogContext::currentActorType();
    }

    /**
     * Whether the visibility selector is shown (staff/admin only).
     */
    #[Computed]
    public function canChooseVisibility(): bool
    {
        return in_array($this->actorType(), [ActorType::Staff, ActorType::Admin], true);
    }

    /**
     * Suppliers involved in this request, offered as share targets for staff.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function supplierOptions(): array
    {
        return $this->request->supplierQuotes()
            ->with('supplier:id,name')
            ->get()
            ->pluck('supplier.name', 'supplier.id')
            ->filter()
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    public function submit(): void
    {
        $actorType = $this->actorType();

        if ($actorType === ActorType::System) {
            throw new AuthorizationException('You must be signed in to post a note.');
        }

        [$visibility, $audienceCompanyId] = $this->resolveAudience($actorType);

        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $body = trim((string) $this->body);

        if ($body === '' && $this->attachments === []) {
            throw ValidationException::withMessages([
                'body' => 'Write a note or attach at least one file before posting.',
            ]);
        }

        /** @var User $user */
        $user = $this->authUser();
        $policy = app(RequestNotePolicy::class);

        if (! $policy->create() || ! $policy->createNote($user, $this->request, $visibility, $audienceCompanyId)) {
            throw new AuthorizationException('You are not allowed to post this note on this request.');
        }

        $note = RequestNote::query()->create([
            'team_id' => $this->request->team_id,
            'request_id' => $this->request->getKey(),
            'author_id' => ActivityLogContext::currentCauser()?->getKey(),
            'author_actor_type' => $actorType,
            'body' => $body === '' ? null : $body,
            'visibility' => $visibility,
            'audience_company_id' => $audienceCompanyId,
        ]);

        $this->attachUploads($note);

        $this->reset('body', 'attachments', 'supplierCompanyId');
        $this->visibility = NoteVisibility::Internal->value;

        $this->dispatch('note-posted');

        $this->sendNotification('Note posted');
    }

    public function render(): View
    {
        return view('livewire.request-note-composer');
    }

    /**
     * Resolve the note's visibility and supplier audience from the acting
     * party, forcing portal parties onto their only allowed visibility and
     * scoping a supplier to their own company.
     *
     * @return array{0: NoteVisibility, 1: int|null}
     */
    private function resolveAudience(ActorType $actorType): array
    {
        return match ($actorType) {
            ActorType::Staff, ActorType::Admin => $this->resolveStaffAudience(),
            ActorType::Buyer => [NoteVisibility::Buyer, null],
            ActorType::Supplier => [NoteVisibility::Supplier, app(SupplierPortalContext::class)->companyId()],
            ActorType::System => [NoteVisibility::Internal, null],
        };
    }

    /**
     * @return array{0: NoteVisibility, 1: int|null}
     */
    private function resolveStaffAudience(): array
    {
        $visibility = NoteVisibility::tryFrom($this->visibility) ?? NoteVisibility::Internal;

        if ($visibility !== NoteVisibility::Supplier) {
            return [$visibility, null];
        }

        if ($this->supplierCompanyId === null) {
            throw ValidationException::withMessages([
                'supplierCompanyId' => 'Choose which supplier to share this note with.',
            ]);
        }

        return [NoteVisibility::Supplier, $this->supplierCompanyId];
    }

    /**
     * Attach the pending Livewire temp uploads to the note through the shared
     * media action so each file is stamped with the uploader's identity.
     *
     * Livewire keeps temporary uploads on its own temp disk (a dedicated fake
     * disk under test), while {@see AttachUploadedFiles} validates paths on the
     * local disk. Each temp file is therefore copied onto the local disk under
     * a per-note directory (keeping its original name) and handed to the action
     * as a relative path; Spatie moves the copy into the media library.
     */
    private function attachUploads(RequestNote $note): void
    {
        if ($this->attachments === []) {
            return;
        }

        $directory = 'note-uploads-tmp/'.$note->getKey();
        $paths = [];

        foreach ($this->attachments as $file) {
            if (! is_object($file)) {
                continue;
            }
            if (! method_exists($file, 'storeAs')) {
                continue;
            }
            if (! method_exists($file, 'getClientOriginalName')) {
                continue;
            }
            $stored = $file->storeAs($directory, $file->getClientOriginalName(), 'local');

            if (is_string($stored) && $stored !== '') {
                $paths[] = $stored;
            }
        }

        app(AttachUploadedFiles::class)->execute(
            $note,
            $paths,
            RequestNote::ATTACHMENTS_COLLECTION,
            $directory,
        );
    }
}
