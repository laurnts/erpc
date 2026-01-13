<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Article;
use App\Models\User;

final readonly class ArticleObserver
{
    /**
     * Handle the Article "creating" event.
     */
    public function creating(Article $article): void
    {
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($article->team_id === null && $user->currentTeam !== null) {
                $article->team_id = $user->currentTeam->getKey();
            }

            if ($article->creator_id === null) {
                $article->creator_id = $user->getKey();
            }
        }

        // Auto-generate article code if not provided
        if (($article->code === null || $article->code === '') && $article->team_id !== null) {
            $article->code = $this->generateArticleCode($article->team_id);
        }
    }

    /**
     * Generate the next article code for a team (ART-0001, ART-0002, etc.).
     */
    private function generateArticleCode(int $teamId): string
    {
        $driver = config('database.default');
        $substringFunc = $driver === 'sqlite' ? 'SUBSTR' : 'SUBSTRING';

        $latestArticle = Article::withTrashed()
            ->where('team_id', $teamId)
            ->where('code', 'like', 'ART-%')
            ->orderByRaw("CAST({$substringFunc}(code, 5) AS INTEGER) DESC")
            ->first();

        $nextNumber = 1;

        if ($latestArticle !== null) {
            $currentNumber = (int) mb_substr((string) $latestArticle->code, 4);
            $nextNumber = $currentNumber + 1;
        }

        return sprintf('ART-%04d', $nextNumber);
    }
}
