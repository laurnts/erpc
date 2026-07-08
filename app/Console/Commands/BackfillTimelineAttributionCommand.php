<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Request;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Support\Media\UploaderProvenance;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Best-effort provenance backfill for legacy timeline data.
 *
 * The ERP intake is people-driven, but documents attached (and activity rows
 * written) before uploader/causer stamping default to System in the timeline,
 * misrepresenting a human process as automation. This command reconstructs the
 * most likely human actor from provenance that is already on record:
 *
 *  - MEDIA: any request-document upload missing an `uploader_actor_type` stamp
 *    is given the actor implied by its collection ({@see UploaderProvenance})
 *    and, when the owning model tracks a creator, an `uploader_id` from that
 *    creator. A document whose owner has no creator still gets a non-System
 *    actor (a person), just without a named uploader.
 *
 *  - ACTIVITY: an unattributed activity row (no causer) whose subject tracks a
 *    creator is attributed to that creator — creators are staff/team members,
 *    so the actor type is set to Staff when it was null or System. This is a
 *    heuristic, not a recovered fact: the true actor was not recorded, and the
 *    subject's creator is the best available stand-in.
 *
 * Genuine automation is preserved: rows under a non-default (system/email/
 * notification) log name are left as-is, and System stays the timeline's
 * fallback for anything this backfill cannot attribute.
 *
 * Idempotent and safe to re-run: only unstamped media and null-causer activity
 * rows are considered, so a second pass is a no-op on already-attributed data.
 */
final class BackfillTimelineAttributionCommand extends Command
{
    protected $signature = 'timeline:backfill-attribution
                            {--chunk=500 : Rows to process per batch}';

    protected $description = 'Backfill uploader/causer attribution on legacy media and activity rows so people-driven history stops reading as System';

    /**
     * Subject morph aliases that track a creator, mapped to their model class.
     * Only these subject types are eligible for causer backfill.
     *
     * @var array<string, class-string<Model>>
     */
    private const CREATOR_SUBJECTS = [
        'request' => Request::class,
        'buyer_quote' => BuyerQuote::class,
        'supplier_quote' => SupplierQuote::class,
        'buyer_order' => BuyerOrder::class,
        'supplier_order' => SupplierOrder::class,
        'buyer_invoice' => BuyerInvoice::class,
        'supplier_invoice' => SupplierInvoice::class,
    ];

    /**
     * Log names that denote genuine automation and must never be re-attributed
     * to a person.
     *
     * @var list<string>
     */
    private const SYSTEM_LOG_NAMES = ['system', 'email', 'emails', 'notification', 'notifications', 'scheduled'];

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));

        $mediaUpdated = $this->backfillMedia($chunk);
        $activityUpdated = $this->backfillActivity($chunk);

        $this->newLine();
        $this->info("Media rows attributed: {$mediaUpdated}");
        $this->info("Activity rows attributed: {$activityUpdated}");

        return self::SUCCESS;
    }

    /**
     * Stamp unstamped request-document media with a collection-implied actor
     * and, where available, the owning model's creator as uploader.
     */
    private function backfillMedia(int $chunk): int
    {
        $updated = 0;

        Media::query()
            ->whereIn('collection_name', UploaderProvenance::documentCollections())
            ->with('model')
            ->chunkById($chunk, function ($media) use (&$updated): void {
                foreach ($media as $item) {
                    if ($item->getCustomProperty('uploader_actor_type') !== null) {
                        continue;
                    }

                    $item->setCustomProperty(
                        'uploader_actor_type',
                        UploaderProvenance::actorTypeFor((string) $item->collection_name)->value,
                    );

                    if ($item->getCustomProperty('uploader_id') === null) {
                        $creatorId = $item->model?->getAttribute('creator_id');

                        if ($creatorId !== null) {
                            $item->setCustomProperty('uploader_id', $creatorId);
                        }
                    }

                    $item->save();
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Attribute null-causer activity rows to their subject's creator (a staff
     * member), skipping genuine-automation log names.
     */
    private function backfillActivity(int $chunk): int
    {
        $updated = 0;

        foreach (self::CREATOR_SUBJECTS as $subjectType => $modelClass) {
            $creatorIds = $modelClass::query()->pluck('creator_id', 'id');

            ActivityLog::query()
                ->whereNull('causer_id')
                ->where('subject_type', $subjectType)
                ->whereNotNull('subject_id')
                ->where(function (Builder $query): void {
                    $query->whereNull('log_name')
                        ->orWhereNotIn('log_name', self::SYSTEM_LOG_NAMES);
                })
                ->chunkById($chunk, function ($rows) use (&$updated, $creatorIds): void {
                    foreach ($rows as $row) {
                        $creatorId = $creatorIds->get((int) $row->subject_id);

                        if ($creatorId === null) {
                            continue;
                        }

                        $row->causer_type = (new User)->getMorphClass();
                        $row->causer_id = $creatorId;

                        if ($row->actor_type === null || $row->actor_type === ActorType::System) {
                            $row->actor_type = ActorType::Staff;
                        }

                        $row->save();
                        $updated++;
                    }
                });
        }

        return $updated;
    }
}
