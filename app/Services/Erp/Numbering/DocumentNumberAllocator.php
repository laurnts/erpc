<?php

declare(strict_types=1);

namespace App\Services\Erp\Numbering;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Allocates document sequence numbers from a locked counter row.
 *
 * Formatting stays at the call site: the four document-number formats in this
 * system differ structurally (dashed, roman-month, slashed, suffixed), and a
 * generic formatter would be harder to read than the sprintf() it replaced.
 * Only the part that was unsafe — deciding which integer comes next — lives here.
 *
 * Numbers are strictly monotonic per (team, key, period). A rolled-back create
 * burns its number; gaps are not refilled. That is deliberate: reusing a number
 * that briefly existed is worse than skipping one.
 */
final readonly class DocumentNumberAllocator
{
    private const string TABLE = 'document_number_sequences';

    /**
     * Take the next sequence value and advance the counter.
     */
    public function next(int $teamId, string $key, string $period): int
    {
        try {
            return $this->attempt($teamId, $key, $period);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return $this->attempt($teamId, $key, $period);
        }
    }

    /**
     * What next() would return, without advancing. Diagnostics and backfill
     * verification only — never use this to assign a number.
     */
    public function peek(int $teamId, string $key, string $period): int
    {
        $value = DB::table(self::TABLE)
            ->where('team_id', $teamId)
            ->where('key', $key)
            ->where('period', $period)
            ->value('next_value');

        return $value === null ? 1 : (int) $value;
    }

    /**
     * Set the counter so the next allocation returns $nextValue.
     */
    public function seed(int $teamId, string $key, string $period, int $nextValue): void
    {
        $updated = DB::table(self::TABLE)
            ->where('team_id', $teamId)
            ->where('key', $key)
            ->where('period', $period)
            ->update([
                'next_value' => $nextValue,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            DB::table(self::TABLE)->insert([
                'team_id' => $teamId,
                'key' => $key,
                'period' => $period,
                'next_value' => $nextValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function attempt(int $teamId, string $key, string $period): int
    {
        return DB::transaction(function () use ($teamId, $key, $period): int {
            $row = DB::table(self::TABLE)
                ->where('team_id', $teamId)
                ->where('key', $key)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $this->insertSequence($teamId, $key, $period, 2);

                return 1;
            }

            $allocated = (int) $row->next_value;

            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->update([
                    'next_value' => $allocated + 1,
                    'updated_at' => now(),
                ]);

            return $allocated;
        });
    }

    /**
     * Insert a fresh counter row. Two concurrent first-allocations both find no
     * row; the unique index rejects the loser with a QueryException, which
     * propagates to next() and is retried there, taking the lock path.
     */
    private function insertSequence(int $teamId, string $key, string $period, int $nextValue): void
    {
        DB::table(self::TABLE)->insert([
            'team_id' => $teamId,
            'key' => $key,
            'period' => $period,
            'next_value' => $nextValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * SQLSTATE 23505 is PostgreSQL's unique_violation; SQLite reports the class
     * code 23000 with a "UNIQUE constraint failed" message. Both must be
     * recognised: the suite runs on SQLite locally and PostgreSQL in CI, and
     * matching on the index name works on neither reliably.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23505'
            || ($sqlState === '23000' && stripos($e->getMessage(), 'unique') !== false);
    }
}
