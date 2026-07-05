<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\AcceptanceReport;
use App\Models\BuyerQuote;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;

/**
 * Resolves the fully-qualified v3 storage prefix for a document upload, anchored to
 * its owning Request:
 *
 *   documents/team-{team_id}/{year}/{request_number}/{segment}[/{number}]
 *
 * This runs once at attach time (in {@see \App\Actions\Media\AttachUploadedFiles}) and
 * the result is stamped onto the media's custom properties, so path resolution stays
 * query-free and stable even if the parent is later renumbered. It may query relations
 * but is fully defensive: a broken chain yields null (never an exception), letting the
 * caller fall back to the v2 path.
 */
final readonly class DocumentPathResolver
{
    /**
     * Map of model class => collection => path descriptor. Strict superset of
     * {@see DocumentPathGenerator::folderMap()} plus AcceptanceReport attachments.
     *
     * @return array<class-string, array<string, array{segment: string, number?: string}>>
     */
    private static function segmentMap(): array
    {
        return [
            Request::class => [
                'attachments' => ['segment' => 'request-attachments'],
                'goods_receive' => ['segment' => 'goods-receive'],
                'completion_reports' => ['segment' => 'completion-reports'],
            ],
            SupplierQuote::class => [
                'quotation' => ['segment' => 'supplier-quotes', 'number' => 'quote_number'],
            ],
            BuyerQuote::class => [
                'buyer_po' => ['segment' => 'buyer-quotes', 'number' => 'quote_number'],
            ],
            SupplierOrder::class => [
                'documents' => ['segment' => 'supplier-orders', 'number' => 'po_number'],
            ],
            QuotationEvaluation::class => [
                'documents' => ['segment' => 'quotation-evaluations', 'number' => 'qe_number'],
            ],
            ProfitAndLoss::class => [
                'documents' => ['segment' => 'profit-and-loss', 'number' => 'pnl_number'],
            ],
            AcceptanceReport::class => [
                'attachments' => ['segment' => 'acceptance-reports', 'number' => 'report_number'],
            ],
        ];
    }

    /**
     * Compute the v3 path prefix for the given record + collection, or null when the model
     * is unmapped (silent) or the anchoring chain is incomplete (logged as a warning).
     */
    public static function prefixFor(HasMedia $record, string $collection): ?string
    {
        $descriptor = self::descriptorFor($record, $collection);
        if ($descriptor === null || ! $record instanceof Model) {
            return null;
        }

        $anchor = $record instanceof Request ? $record : $record->getAttribute('request');
        if (! $anchor instanceof Request) {
            return self::fallback('anchor request missing', $record, $collection);
        }

        $teamId = $record->team_id ?? $anchor->team_id;
        if ($teamId === null) {
            return self::fallback('team_id missing', $record, $collection);
        }

        $year = $anchor->created_at?->year;
        if ($year === null) {
            return self::fallback('anchor created_at missing', $record, $collection);
        }

        $requestNumber = (string) $anchor->request_number;
        if ($requestNumber === '') {
            return self::fallback('request_number missing', $record, $collection);
        }

        $segments = [
            'documents',
            'team-'.$teamId,
            (string) $year,
            self::sanitize($requestNumber),
            $descriptor['segment'],
        ];

        if (isset($descriptor['number'])) {
            $number = (string) $record->{$descriptor['number']};
            if ($number === '') {
                return self::fallback($descriptor['number'].' missing', $record, $collection);
            }
            $segments[] = self::sanitize($number);
        }

        return implode('/', $segments);
    }

    /**
     * @return array{segment: string, number?: string}|null
     */
    private static function descriptorFor(HasMedia $record, string $collection): ?array
    {
        foreach (self::segmentMap() as $mappedClass => $collections) {
            if (! $record instanceof $mappedClass) {
                continue;
            }
            if (isset($collections[$collection])) {
                return $collections[$collection];
            }
        }

        return null;
    }

    private static function sanitize(string $segment): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $segment);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function fallback(string $reason, Model $record, string $collection, array $extra = []): null
    {
        Log::warning('Document path chain incomplete; falling back to v2', array_merge([
            'reason' => $reason,
            'model' => $record::class,
            'model_id' => $record->getKey(),
            'collection' => $collection,
        ], $extra));

        return null;
    }
}
