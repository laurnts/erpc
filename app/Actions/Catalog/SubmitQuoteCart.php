<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\BuyerPortal\NotifyTeamOfPortalRequest;
use App\Enums\ItemType;
use App\Enums\PortalType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Models\Article;
use App\Models\CompanyPortalUser;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use App\Services\Catalog\CatalogTeamResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts a quote cart into a standard portal-originated Request (design D7).
 *
 * Validates every line first (positive quantity, article still grid-visible)
 * and rejects the whole submission when any line fails — never a partial
 * Request. The buyer company is the submitting user's active buyer-portal
 * membership at the catalog team; the request then flows through the existing
 * portal-originated workflow (buyer_id scoping makes it visible in the portal).
 */
final readonly class SubmitQuoteCart
{
    public function __construct(
        private CatalogTeamResolver $catalogTeam,
        private NotifyTeamOfPortalRequest $notifyTeam,
    ) {}

    /**
     * @param  array<int, float>  $lines  article_id => quantity
     */
    public function execute(User $user, array $lines): Request
    {
        $teamId = $this->catalogTeam->teamId();

        if ($teamId === null) {
            throw ValidationException::withMessages([
                'cart' => ['The catalog is currently unavailable.'],
            ]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'cart' => ['Your quote cart is empty.'],
            ]);
        }

        $membership = $this->resolveMembership($user, $teamId);

        $articles = Article::query()
            ->inPublicCatalog($teamId)
            ->whereKey(array_keys($lines))
            ->get()
            ->keyBy(fn (Article $article): int => (int) $article->getKey());

        $errors = [];

        foreach ($lines as $articleId => $quantity) {
            $article = $articles->get($articleId);

            if ($article === null) {
                $errors[] = sprintf('Line %d: this product is no longer available in the catalog.', $articleId);

                continue;
            }

            if ($quantity <= 0) {
                $errors[] = sprintf('%s: quantity must be greater than zero.', $article->name);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['cart' => $errors]);
        }

        $request = DB::transaction(function () use ($user, $teamId, $membership, $lines, $articles): Request {
            $request = Request::query()->create([
                'team_id' => $teamId,
                'buyer_id' => $membership->company_id,
                'title' => 'Catalog quote request — '.now()->toDateString(),
                'stage' => RequestStage::DRAFT,
                'submission_method' => RequestSubmissionMethod::CATALOG,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->getKey(),
                'requested_at' => now()->toDateString(),
                'creator_id' => $user->getKey(),
            ]);

            $sortOrder = 0;

            foreach ($lines as $articleId => $quantity) {
                /** @var Article $article */
                $article = $articles->get($articleId);

                RequestItem::query()->create([
                    'request_id' => $request->getKey(),
                    'article_id' => $article->getKey(),
                    'description' => $article->name,
                    'item_type' => ItemType::GOODS,
                    'quantity' => $quantity,
                    'unit' => $article->unit,
                    'is_matched' => true,
                    'sort_order' => $sortOrder++,
                ]);
            }

            return $request;
        });

        $this->notifyTeam->execute($request);

        return $request;
    }

    private function resolveMembership(User $user, int $teamId): CompanyPortalUser
    {
        $membership = CompanyPortalUser::query()
            ->where('user_id', $user->getKey())
            ->where('team_id', $teamId)
            ->where('portal', PortalType::Buyer)
            ->where('is_active', true)
            ->whereHas('company', fn (Builder $query) => $query->where('is_buyer', true))
            ->orderBy('company_id')
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'cart' => ['No active buyer portal access found for your account.'],
            ]);
        }

        return $membership;
    }
}
