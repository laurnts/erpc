<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EmailTemplate::query()
            ->where('type', EmailTemplate::TYPE_BUYER_QUOTE)
            ->where('content', 'like', '%@foreach($quote->items as $index => $item)%')
            ->each(function (EmailTemplate $template): void {
                $updated = preg_replace(
                    '/@if\(\$quote->items && \$quote->items->count\(\) > 0\)\s*<!-- Items Table -->.*?<\/table>\s*@endif/s',
                    "@include('emails.partials.buyer-quote-items-table', ['quote' => \$quote])",
                    (string) $template->content,
                    1,
                    $count,
                );

                if ($count > 0 && is_string($updated)) {
                    $template->content = $updated;
                    $template->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Non-reversible: restored templates would need the old flat foreach markup.
    }
};
