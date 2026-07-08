<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ActorType;
use App\Enums\NoteVisibility;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\User;
use App\Support\ActivityLogContext;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorizes request-note reads and writes against the same audience model
 * the timeline enforces (design D2): staff/admin see and write everything on
 * their team's requests, a buyer may only author/read Buyer-visibility notes
 * on a request their company owns, and a supplier may only author/read
 * Supplier-visibility notes scoped to their own company. The acting side is
 * resolved from the authenticated guard via {@see ActivityLogContext} — the
 * same signal the note's author_actor_type is stamped from.
 */
final readonly class RequestNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return ActivityLogContext::currentActorType() !== ActorType::System;
    }

    /**
     * Read access mirrors the timeline note-visibility scope exactly.
     */
    public function view(User $user, RequestNote $note): bool
    {
        return match (ActivityLogContext::currentActorType()) {
            ActorType::Staff, ActorType::Admin => $user->belongsToTeam($note->team),
            ActorType::Buyer => $note->visibility === NoteVisibility::Buyer
                && in_array((int) $note->request->buyer_id, $user->activeBuyerPortalCompanyIds(), true),
            ActorType::Supplier => $note->visibility === NoteVisibility::Supplier
                && $note->audience_company_id !== null
                && in_array((int) $note->audience_company_id, $user->activeSupplierPortalCompanyIds(), true),
            ActorType::System => false,
        };
    }

    /**
     * Coarse create gate: any non-system party may attempt to add a note; the
     * specific request + visibility combination is authorized by
     * {@see self::createNote()}, which the note-creation flow must call.
     */
    public function create(User $user): bool
    {
        return ActivityLogContext::currentActorType() !== ActorType::System;
    }

    /**
     * Whether the acting party may author a note with this exact visibility on
     * this request. Staff/admin author any visibility on their team's request;
     * a buyer author is limited to Buyer-visibility notes on a request their
     * company owns; a supplier author is limited to Supplier-visibility notes
     * scoped to their own company.
     */
    public function createNote(User $user, Request $request, NoteVisibility $visibility, ?int $audienceCompanyId = null): bool
    {
        return match (ActivityLogContext::currentActorType()) {
            ActorType::Staff, ActorType::Admin => $user->belongsToTeam($request->team),
            ActorType::Buyer => $visibility === NoteVisibility::Buyer
                && $audienceCompanyId === null
                && in_array((int) $request->buyer_id, $user->activeBuyerPortalCompanyIds(), true),
            ActorType::Supplier => $visibility === NoteVisibility::Supplier
                && $audienceCompanyId !== null
                && in_array($audienceCompanyId, $user->activeSupplierPortalCompanyIds(), true),
            ActorType::System => false,
        };
    }

    public function update(User $user, RequestNote $note): bool
    {
        return in_array(ActivityLogContext::currentActorType(), [ActorType::Staff, ActorType::Admin], true)
            && $user->belongsToTeam($note->team)
            && ((int) $note->author_id === (int) $user->getKey());
    }

    public function delete(User $user, RequestNote $note): bool
    {
        return in_array(ActivityLogContext::currentActorType(), [ActorType::Staff, ActorType::Admin], true)
            && $user->belongsToTeam($note->team);
    }

    public function restore(User $user, RequestNote $note): bool
    {
        return $this->delete($user, $note);
    }

    public function forceDelete(User $user, RequestNote $note): bool
    {
        return ActivityLogContext::currentActorType() === ActorType::Admin
            && $user->belongsToTeam($note->team);
    }
}
