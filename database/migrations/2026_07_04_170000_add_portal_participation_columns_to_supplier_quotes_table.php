<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier-portal request participation state on supplier_quotes:
 * - sent_to_supplier_at is the portal visibility gate — stamped when the
 *   "Send to Suppliers" mail is successfully dispatched; auto-generated
 *   pending quotes without a send stay invisible to the portal.
 * - submitted_via/at/by record a supplier's own portal submission.
 * - declined_at records "decline to quote" (status stays PENDING).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->string('submitted_via', 20)->default('internal')->after('obtained');
            $table->dateTime('submitted_at')->nullable()->after('submitted_via');
            $table->foreignId('submitted_by_user_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->dateTime('declined_at')->nullable()->after('submitted_by_user_id');
            $table->dateTime('sent_to_supplier_at')->nullable()->after('declined_at');
        });

        // Backfill the visibility gate from notification metadata: the
        // "Send to Suppliers" path records email_sent=true/email_sent_at on
        // successful dispatch, which is the only reliable prior-send marker.
        $quotes = DB::table('supplier_quotes')
            ->whereNotNull('notification_metadata')
            ->get(['id', 'notification_metadata', 'updated_at']);

        foreach ($quotes as $quote) {
            /** @var array<string, mixed>|null $metadata */
            $metadata = json_decode((string) $quote->notification_metadata, true);
            if (! is_array($metadata)) {
                continue;
            }
            if (($metadata['email_sent'] ?? false) !== true) {
                continue;
            }

            $sentAt = isset($metadata['email_sent_at']) && is_string($metadata['email_sent_at'])
                ? Carbon::parse($metadata['email_sent_at'])->toDateTimeString()
                : $quote->updated_at;

            DB::table('supplier_quotes')
                ->where('id', $quote->id)
                ->update(['sent_to_supplier_at' => $sentAt]);
        }
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn([
                'submitted_via',
                'submitted_at',
                'declined_at',
                'sent_to_supplier_at',
            ]);
        });
    }
};
