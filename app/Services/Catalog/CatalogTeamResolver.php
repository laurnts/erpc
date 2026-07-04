<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Team;

/**
 * Resolves the single team whose articles the public catalog serves.
 *
 * Public pages must never depend on auth() or Filament tenancy (guests would
 * fatal) — the catalog team comes from config('catalog.team_id') with a
 * first-team fallback (design D1). Null when no team exists yet (fresh
 * install): the catalog then renders empty instead of erroring.
 */
final readonly class CatalogTeamResolver
{
    public function teamId(): ?int
    {
        $configured = config('catalog.team_id');

        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        $firstTeamId = Team::query()->orderBy('id')->value('id');

        return $firstTeamId === null ? null : (int) $firstTeamId;
    }

    public function team(): ?Team
    {
        $teamId = $this->teamId();

        return $teamId === null ? null : Team::query()->find($teamId);
    }
}
