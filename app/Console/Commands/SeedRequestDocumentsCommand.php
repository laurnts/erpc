<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\AttachUploadedFiles;
use App\Models\GoodsReceiveBatch;
use App\Models\PaymentDocumentApproval;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Support\Media\UploaderProvenance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class SeedRequestDocumentsCommand extends Command
{
    private const DEFAULT_USER_NAME = 'Jun Sin';

    private const STAGING_DIRECTORY = 'uploads-tmp/seed-documents';

    private const GATES = ['goods-receive', 'completion-report', 'documents', 'quotation'];

    protected $signature = 'request:seed-documents
                            {identifier : PO number (goods-receive, documents), request number (completion-report, quotation), QE/PNL number (documents), or SQ quote number (quotation)}
                            {--gate= : Upload gate to satisfy: goods-receive, completion-report, documents, or quotation}
                            {--approve : Also create PaymentDocumentApproval rows so the gate is already satisfied}
                            {--payment-terms=100% : Payment terms recorded on the completion-report payment document}
                            {--user= : Name of the user recorded as uploader/approver (default: Jun Sin)}';

    protected $description = 'Seed a placeholder document into an approval-gated media collection for testing, skipping the UI upload';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifier'));
        $gate = (string) $this->option('gate');

        if (! in_array($gate, self::GATES, true)) {
            $this->error('Option --gate is required and must be one of: '.implode(', ', self::GATES));

            return self::FAILURE;
        }

        $userName = (string) ($this->option('user') ?: self::DEFAULT_USER_NAME);
        $user = User::where('name', 'like', "%{$userName}%")->first();
        if ($user === null) {
            $this->error("User not found: {$userName}");

            return self::FAILURE;
        }

        try {
            return match ($gate) {
                'goods-receive' => $this->seedGoodsReceive($identifier, $user),
                'completion-report' => $this->seedCompletionReport($identifier, $user),
                'documents' => $this->seedDocuments($identifier, $user),
                'quotation' => $this->seedQuotation($identifier, $user),
            };
        } catch (Throwable $e) {
            $this->error('Failed to seed documents:');
            $this->line($e->getMessage());
            if ($this->option('verbose')) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function seedGoodsReceive(string $identifier, User $user): int
    {
        $order = SupplierOrder::query()
            ->where('po_number', $this->stripPrefix($identifier, ['PO ']))
            ->with('request')
            ->first();
        if ($order === null || $order->request === null) {
            $this->error("No supplier order (with request) found for PO: {$identifier}");

            return self::FAILURE;
        }

        $media = $this->seedMedia($order->request, 'goods_receive', 'Placeholder Goods Receive Document', [
            'uploaded_by' => $user->id,
            'supplier_order_id' => $order->id,
        ], $user);

        $batch = GoodsReceiveBatch::create([
            'request_id' => $order->request->id,
            'supplier_order_id' => $order->id,
            'user_id' => $user->id,
            'media_ids' => [$media->id],
        ]);
        $this->info("Seeded goods receive media {$media->id} and batch {$batch->id} for PO {$order->po_number}.");

        if ((bool) $this->option('approve')) {
            $this->approveMedia($media, $order->team_id, $user);
        }

        return self::SUCCESS;
    }

    private function seedCompletionReport(string $identifier, User $user): int
    {
        $request = Request::query()->where('request_number', $identifier)->first();
        if ($request === null) {
            $this->error("No request found for request number: {$identifier}");

            return self::FAILURE;
        }

        $media = $this->seedMedia($request, 'completion_reports', 'Placeholder Completion Report', [
            'uploaded_by' => $user->id,
            'is_payment_document' => true,
            'payment_terms' => (string) $this->option('payment-terms'),
        ], $user);
        $this->info("Seeded completion report media {$media->id} for request {$request->request_number}.");

        if ((bool) $this->option('approve')) {
            $this->approveMedia($media, $request->team_id, $user);
        }

        return self::SUCCESS;
    }

    private function seedDocuments(string $identifier, User $user): int
    {
        $model = $this->resolveDocumentsTarget($this->stripPrefix($identifier, ['QE ', 'PNL ', 'PO ']));
        if ($model === null) {
            $this->error("No QE, PNL, or PO found for: {$identifier}");

            return self::FAILURE;
        }

        $media = $this->seedMedia($model, 'documents', 'Placeholder Approval Document', [
            'uploaded_by' => $user->id,
        ], $user);
        $this->info('Seeded documents media '.$media->id.' for '.class_basename($model).' #'.$model->id.'.');

        if ((bool) $this->option('approve')) {
            $this->approveMedia($media, $model->team_id, $user);
            $model->approveViaDocumentAcceptance($user);
            $this->info(class_basename($model).' marked as approved via document acceptance.');
        }

        return self::SUCCESS;
    }

    private function seedQuotation(string $identifier, User $user): int
    {
        $quotes = $this->resolveSupplierQuotes($identifier);
        if ($quotes === null) {
            $this->error("No supplier quote or request found for: {$identifier}");

            return self::FAILURE;
        }

        $pending = $quotes->filter(fn (SupplierQuote $quote): bool => $quote->getMedia('quotation')->isEmpty());
        if ($pending->isEmpty()) {
            $this->warn('All matched supplier quote(s) already have a quotation document.');

            return self::SUCCESS;
        }

        foreach ($pending as $quote) {
            $media = $this->seedMedia($quote, 'quotation', 'Placeholder Supplier Quotation', [
                'uploaded_by' => $user->id,
            ], $user);
            $this->info("Seeded quotation media {$media->id} for supplier quote {$quote->quote_number}.");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve an SQ quote number to that quote, or a request number to all its supplier quotes.
     *
     * @return \Illuminate\Support\Collection<int, SupplierQuote>|null
     */
    private function resolveSupplierQuotes(string $identifier): ?\Illuminate\Support\Collection
    {
        $quote = SupplierQuote::query()->where('quote_number', $identifier)->first();
        if ($quote !== null) {
            return collect([$quote]);
        }

        $request = Request::query()->where('request_number', $identifier)->first();
        if ($request === null) {
            return null;
        }

        return SupplierQuote::query()->where('request_id', $request->id)->get()->collect();
    }

    private function resolveDocumentsTarget(string $number): QuotationEvaluation|ProfitAndLoss|SupplierOrder|null
    {
        if (preg_match('/^\d+-DS\/QE\//', $number) === 1) {
            return QuotationEvaluation::where('qe_number', $number)->first();
        }
        if (preg_match('/^\d+\/EL-PNL\//', $number) === 1) {
            return ProfitAndLoss::where('pnl_number', $number)->first();
        }
        if (preg_match('/^PO-\d+/', $number) === 1) {
            return SupplierOrder::where('po_number', $number)->first();
        }

        return null;
    }

    /**
     * Attach a placeholder document, stamping the uploader identity from the
     * resolved seed user and the actor kind implied by the collection so the
     * seeded history reads as a person (never System). Caller-supplied
     * uploader stamps win inside {@see AttachUploadedFiles}, so these override
     * the runtime (unauthenticated) System default the command runs under.
     *
     * @param  array<string, mixed>  $customProperties
     */
    private function seedMedia(HasMedia $model, string $collection, string $name, array $customProperties, User $user): Media
    {
        $fileName = 'placeholder-'.str_replace('_', '-', $collection).'.pdf';
        $relativePath = self::STAGING_DIRECTORY.'/'.Str::uuid().'/'.$fileName;
        Storage::disk('local')->put($relativePath, $this->placeholderPdfContents($name));

        $attached = app(AttachUploadedFiles::class)->execute(
            $model,
            [$relativePath],
            $collection,
            self::STAGING_DIRECTORY,
            [
                'uploader_id' => $user->id,
                'uploader_actor_type' => UploaderProvenance::actorTypeFor($collection)->value,
                ...$customProperties,
            ],
        );

        $media = $attached[0] ?? null;
        if (! $media instanceof Media) {
            throw new \RuntimeException('Failed to attach placeholder document.');
        }
        $media->update(['name' => $name]);

        return $media;
    }

    private function approveMedia(Media $media, int $teamId, User $user): void
    {
        PaymentDocumentApproval::create([
            'team_id' => $teamId,
            'media_id' => $media->id,
            'user_id' => $user->id,
            'approved_at' => now(),
            'notes' => 'Approved via request:seed-documents command',
        ]);
        $this->info("Approved media {$media->id} for team {$teamId}.");
    }

    private function placeholderPdfContents(string $label): string
    {
        $text = $label.' - seeded via request:seed-documents';

        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
            .'4 0 obj << /Length '.(strlen($text) + 42)." >> stream\nBT /F1 12 Tf 72 720 Td ({$text}) Tj ET\nendstream endobj\n"
            ."5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function stripPrefix(string $value, array $prefixes): string
    {
        $value = trim($value);
        foreach ($prefixes as $prefix) {
            if (stripos($value, $prefix) === 0) {
                return trim(substr($value, strlen($prefix)));
            }
        }

        return $value;
    }
}
