<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TeamInvitation;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\AddsTeamMembers;

final class ApproveMemberInviteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member-invite:approve
                            {email : The invited email address}
                            {--team= : Team ID or name (required when the email is invited to multiple teams)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve a pending team member invitation from the CLI — adds the member exactly as the emailed accept link would';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $teamInput = $this->option('team');

        $invitations = TeamInvitation::query()
            ->where('email', $email)
            ->with('team')
            ->get();

        if ($invitations->isEmpty()) {
            $this->error("No pending invitation found for: {$email}");

            return self::FAILURE;
        }

        if ($teamInput !== null && $teamInput !== '') {
            $invitations = $invitations->filter(
                fn (TeamInvitation $invitation): bool => is_numeric($teamInput)
                    ? $invitation->team->getKey() === (int) $teamInput
                    : mb_stripos($invitation->team->name, $teamInput) !== false
            );

            if ($invitations->isEmpty()) {
                $this->error("No pending invitation for {$email} on team: {$teamInput}");

                return self::FAILURE;
            }
        }

        if ($invitations->count() > 1) {
            $this->error("{$email} is invited to multiple teams — pass --team to pick one:");
            foreach ($invitations as $invitation) {
                $this->line("  --team={$invitation->team->getKey()}  ({$invitation->team->name}, role: {$invitation->role})");
            }

            return self::FAILURE;
        }

        /** @var TeamInvitation $invitation */
        $invitation = $invitations->first();
        $team = $invitation->team;

        $owner = $team->owner;
        if (! $owner instanceof \App\Models\User) {
            $this->error("Team \"{$team->name}\" has no owner to authorize the invitation.");

            return self::FAILURE;
        }

        try {
            app(AddsTeamMembers::class)->add(
                $owner,
                $team,
                $invitation->email,
                $invitation->role,
                $invitation->central_purchasing_role?->value,
            );
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $invitation->delete();

        $this->info("✅ {$email} added to team \"{$team->name}\" as {$invitation->role}.");

        return self::SUCCESS;
    }
}
