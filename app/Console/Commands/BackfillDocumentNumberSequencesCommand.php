<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds document_number_sequences from numbers already issued, so the first
 * allocation after cutover cannot collide with history.
 *
 * Extraction happens in PHP, not SQL, deliberately: the test suite runs on
 * SQLite in-memory (phpunit.xml) while CI and production run PostgreSQL
 * (phpunit.ci.xml), and PostgreSQL's regexp_match has no SQLite equivalent. A
 * portable chunked scan costs nothing here — this command runs once at cutover,
 * over a few thousand rows, and only reads two columns per row.
 */
final class BackfillDocumentNumberSequencesCommand extends Command
{
    protected $signature = 'erp:backfill-document-sequences {--dry-run : Report what would change without writing}';

    protected $description = 'Seed document number sequence counters from existing documents';

    private const int CHUNK = 500;

    /**
     * key => [table, column, pattern]. The pattern must capture the sequence
     * integer and the four-digit period; SEQUENCE_FIRST says which is which.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const array SOURCES = [
        'request' => ['requests', 'request_number', '/^.+-(\d{4})-(\d+)$/'],
        'project' => ['projects', 'project_number', '/^.+-(\d{4})-(\d+)$/'],
        'buyer_quote' => ['buyer_quotes', 'quote_number', '/^.+-(\d{4})-(\d+)$/'],
        'buyer_order' => ['buyer_orders', 'order_number', '/^.+-(\d{4})-(\d+)$/'],
        'buyer_invoice' => ['buyer_invoices', 'invoice_number', '/^.+-(\d{4})-(\d+)$/'],
        'buyer_payment' => ['buyer_payments', 'payment_number', '/^.+-(\d{4})-(\d+)$/'],
        'supplier_order' => ['supplier_orders', 'po_number', '/^.+-(\d{4})-(\d+)(?:-[A-Z])?$/'],
        'supplier_quote' => ['supplier_quotes', 'quote_number', '/^.+-(\d{4})-(\d+)$/'],
        'supplier_invoice' => ['supplier_invoices', 'reference_number', '/^.+-(\d{4})-(\d+)$/'],
        'supplier_payment' => ['supplier_payments', 'payment_number', '/^.+-(\d{4})-(\d+)$/'],
        'shipment' => ['shipments', 'shipment_number', '/^.+-(\d{4})-(\d+)$/'],
        'acceptance_report' => ['acceptance_reports', 'report_number', '/^AR-(\d{4})-(\d+)$/'],
        'quotation_evaluation' => ['quotation_evaluations', 'qe_number', '/^(\d+)-DS\/QE\/[IVX]+\/(\d{4})$/'],
        'profit_and_loss' => ['profit_and_losses', 'pnl_number', '/^(\d+)\/EL-PNL\/[IVX]+\/(\d{4})$/'],
    ];

    /**
     * Keys whose pattern captures the sequence first and the period second,
     * i.e. the reverse of the dashed formats.
     *
     * @var list<string>
     */
    private const array SEQUENCE_FIRST = ['quotation_evaluation', 'profit_and_loss'];

    public function handle(DocumentNumberAllocator $allocator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (self::SOURCES as $key => [$table, $column, $pattern]) {
            foreach ($this->highestPerPeriod($table, $column, $pattern, $key) as $groupKey => $maxSequence) {
                [$teamId, $period] = explode('|', (string) $groupKey, 2);
                $teamId = (int) $teamId;
                $target = $maxSequence + 1;
                $current = $allocator->peek($teamId, $key, $period);

                if ($current >= $target) {
                    continue;
                }

                $this->line(sprintf(
                    '%s team=%d period=%s: %d -> %d%s',
                    $key, $teamId, $period, $current, $target, $dryRun ? ' (dry run)' : '',
                ));

                if (! $dryRun) {
                    $allocator->seed($teamId, $key, $period, $target);
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete — nothing written.' : 'Sequence backfill complete.');

        return self::SUCCESS;
    }

    /**
     * Highest sequence number seen per "teamId|period", scanning in chunks so
     * memory stays bounded regardless of table size.
     *
     * @return array<string, int>
     */
    private function highestPerPeriod(string $table, string $column, string $pattern, string $key): array
    {
        $sequenceFirst = in_array($key, self::SEQUENCE_FIRST, true);
        $highest = [];

        DB::table($table)
            ->select(['team_id', $column])
            ->whereNotNull('team_id')
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) use ($column, $pattern, $sequenceFirst, &$highest): void {
                foreach ($rows as $row) {
                    if (preg_match($pattern, (string) $row->{$column}, $matches) !== 1) {
                        continue;
                    }

                    $period = $sequenceFirst ? $matches[2] : $matches[1];
                    $sequence = (int) ($sequenceFirst ? $matches[1] : $matches[2]);
                    $groupKey = $row->team_id.'|'.$period;

                    if (! isset($highest[$groupKey]) || $sequence > $highest[$groupKey]) {
                        $highest[$groupKey] = $sequence;
                    }
                }
            });

        return $highest;
    }
}
