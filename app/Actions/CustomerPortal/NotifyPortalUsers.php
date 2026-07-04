<?php

declare(strict_types=1);

namespace App\Actions\CustomerPortal;

use App\Enums\PortalType;
use App\Models\Request;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final readonly class NotifyPortalUsers
{
    public function forRequest(Request $request, Notification $notification): void
    {
        $users = $this->portalUsersForCompany($request->buyer_id);

        if ($users->isEmpty()) {
            return;
        }

        NotificationFacade::send($users, $notification);
    }

    public function forCompany(int $companyId, Notification $notification): void
    {
        $users = $this->portalUsersForCompany($companyId);

        if ($users->isEmpty()) {
            return;
        }

        NotificationFacade::send($users, $notification);
    }

    /**
     * @return Collection<int, User>
     */
    private function portalUsersForCompany(int $companyId): Collection
    {
        return User::query()
            ->whereHas('portalMemberships', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('portal', PortalType::Customer)
                ->where('is_active', true))
            ->whereNotNull('email_verified_at')
            ->get();
    }
}
