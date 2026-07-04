<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Catalog\RefreshArticlePriceReview;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RefreshArticlePriceReviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:refresh-price-review';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute the price_review_needed flag for articles with a published list price (catches exchange-rate drift and quoted-price changes)';

    public function handle(RefreshArticlePriceReview $refreshArticlePriceReview): int
    {
        $count = 0;

        Article::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('list_price')
                ->orWhere('price_review_needed', true))
            ->with('team')
            ->chunkById(100, function (Collection $articles) use ($refreshArticlePriceReview, &$count): void {
                foreach ($articles as $article) {
                    $refreshArticlePriceReview->execute($article);
                    $count++;
                }
            });

        $this->info(sprintf('Refreshed price review flags for %d articles.', $count));

        return self::SUCCESS;
    }
}
