<?php

declare(strict_types=1);

namespace App\Actions\CustomerPortal;

use App\Models\Request;
use App\Models\Team;
use App\Notifications\PortalRequestReceivedConfirmationNotification;
use App\Notifications\PortalRequestSubmittedNotification;
use Illuminate\Support\Facades\Notification;

final readonly class NotifyTeamOfPortalRequest
{
    public function __construct(
        private NotifyPortalUsers $notifyPortalUsers,
    ) {}

    public function execute(Request $request): void
    {
        $this->notifyStaff($request);
        $this->notifyPortalUsers->forRequest(
            $request,
            new PortalRequestReceivedConfirmationNotification($request),
        );
    }

    private function notifyStaff(Request $request): void
    {
        $team = $request->team;

        if (! $team instanceof Team) {
            return;
        }

        $recipients = $team->allUsers()
            ->filter(fn ($user) => $user->hasVerifiedEmail())
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PortalRequestSubmittedNotification($request));
    }
}
