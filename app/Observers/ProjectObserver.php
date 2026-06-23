<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

final readonly class ProjectObserver
{
    /**
     * Handle the Project "creating" event.
     */
    public function creating(Project $project): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($project->creator_id === null) {
                $project->creator_id = $user->getKey();
            }

            if ($project->team_id === null && $user->currentTeam !== null) {
                $project->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate project number if not provided
        /** @var string|null $projectNumber */
        $projectNumber = $project->project_number;
        if ($projectNumber === null || $projectNumber === '') {
            $project->project_number = $this->generateProjectNumber($project);
        }
    }

    /**
     * Generate a unique project number (PRJ-2026-0001, PRJ-2026-0002, etc.)
     */
    private function generateProjectNumber(Project $project): string
    {
        $team = $project->team ?? ($project->team_id !== null ? Team::find($project->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->project_number_prefix;

        $year = date('Y');
        $pattern = $prefix.'-'.$year.'-';

        // Get the highest sequence number for this team and year
        $lastProject = Project::query()
            ->withTrashed()
            ->where('team_id', $project->team_id)
            ->where('project_number', 'like', $pattern.'%')
            ->orderByDesc('project_number')
            ->first();

        $nextNumber = 1;
        if ($lastProject !== null) {
            $regex = '/^'.preg_quote((string) $prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastProject->project_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
