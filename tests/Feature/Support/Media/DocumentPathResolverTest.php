<?php

declare(strict_types=1);

use App\Models\AcceptanceReport;
use App\Models\BuyerQuote;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Support\Media\DocumentPathGenerator;
use App\Support\Media\DocumentPathResolver;
use Illuminate\Support\Facades\Log;

describe('mapped model + collection', function () {
    it('resolves a SupplierQuote quotation prefix anchored to its request', function () {
        $request = Request::factory()->create([
            'request_number' => 'REQ-2026-0001',
            'created_at' => '2026-03-09 10:00:00',
        ]);
        $quote = SupplierQuote::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'quote_number' => 'SQ-2026-0007',
        ]);

        expect(DocumentPathResolver::prefixFor($quote, 'quotation'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0001/supplier-quotes/SQ-2026-0007');
    });

    it('resolves a Request attachments prefix (anchor is itself)', function () {
        $request = Request::factory()->create([
            'request_number' => 'REQ-2025-0100',
            'created_at' => '2025-11-20 08:00:00',
        ]);

        expect(DocumentPathResolver::prefixFor($request, 'attachments'))
            ->toBe('documents/team-'.$request->team_id.'/2025/REQ-2025-0100/request-attachments');
    });

    it('resolves Request goods_receive and completion_reports segments', function () {
        $request = Request::factory()->create([
            'request_number' => 'REQ-2026-0002',
            'created_at' => '2026-01-01 00:00:00',
        ]);
        $base = 'documents/team-'.$request->team_id.'/2026/REQ-2026-0002/';

        expect(DocumentPathResolver::prefixFor($request, 'goods_receive'))->toBe($base.'goods-receive');
        expect(DocumentPathResolver::prefixFor($request, 'completion_reports'))->toBe($base.'completion-reports');
    });

    it('resolves a BuyerQuote buyer_po prefix', function () {
        $request = Request::factory()->create(['request_number' => 'REQ-2026-0003', 'created_at' => '2026-02-02']);
        $quote = BuyerQuote::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'quote_number' => 'BQ-2026-0011',
        ]);

        expect(DocumentPathResolver::prefixFor($quote, 'buyer_po'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0003/buyer-quotes/BQ-2026-0011');
    });

    it('resolves a SupplierOrder documents prefix', function () {
        $request = Request::factory()->create(['request_number' => 'REQ-2026-0004', 'created_at' => '2026-04-04']);
        $order = SupplierOrder::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'po_number' => 'PO-2026-0022',
        ]);

        expect(DocumentPathResolver::prefixFor($order, 'documents'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0004/supplier-orders/PO-2026-0022');
    });

    it('resolves an AcceptanceReport attachments prefix', function () {
        $request = Request::factory()->create(['request_number' => 'REQ-2026-0005', 'created_at' => '2026-05-05']);
        $report = AcceptanceReport::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'report_number' => 'AR-2026-0003',
        ]);

        expect(DocumentPathResolver::prefixFor($report, 'attachments'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0005/acceptance-reports/AR-2026-0003');
    });
});

describe('segment sanitization', function () {
    it('sanitizes slash-bearing qe_number segments', function () {
        $request = Request::factory()->create(['request_number' => 'REQ-2026-0006', 'created_at' => '2026-06-06']);
        $evaluation = QuotationEvaluation::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'qe_number' => '001-DS/QE/VII/2026',
        ]);

        expect(DocumentPathResolver::prefixFor($evaluation, 'documents'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0006/quotation-evaluations/001-DS-QE-VII-2026');
    });

    it('sanitizes slash-bearing pnl_number segments', function () {
        $request = Request::factory()->create(['request_number' => 'REQ-2026-0007', 'created_at' => '2026-07-07']);
        $pnl = ProfitAndLoss::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
            'pnl_number' => '0004/EL-PNL/VII/2026',
        ]);

        expect(DocumentPathResolver::prefixFor($pnl, 'documents'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0007/profit-and-loss/0004-EL-PNL-VII-2026');
    });

    it('sanitizes the request_number segment', function () {
        $request = Request::factory()->create(['request_number' => 'REQ/2026/0008', 'created_at' => '2026-08-08']);

        expect(DocumentPathResolver::prefixFor($request, 'attachments'))
            ->toBe('documents/team-'.$request->team_id.'/2026/REQ-2026-0008/request-attachments');
    });
});

describe('fallback semantics', function () {
    it('returns null without warning for an unmapped collection', function () {
        Log::spy();
        $request = Request::factory()->create();

        expect(DocumentPathResolver::prefixFor($request, 'unknown_collection'))->toBeNull();

        Log::shouldNotHaveReceived('warning');
    });

    it('returns null and warns when the request chain is broken', function () {
        Log::spy();
        $quote = SupplierQuote::factory()->make([
            'quote_number' => 'SQ-2026-9999',
        ]);
        $quote->setRelation('request', null);

        expect(DocumentPathResolver::prefixFor($quote, 'quotation'))->toBeNull();

        Log::shouldHaveReceived('warning')->once();
    });

    it('returns null and warns when the numbered segment is empty', function () {
        Log::spy();
        $request = Request::factory()->create(['created_at' => '2026-09-09']);
        $quote = SupplierQuote::factory()->create([
            'team_id' => $request->team_id,
            'request_id' => $request->getKey(),
        ]);
        $quote->quote_number = '';

        expect(DocumentPathResolver::prefixFor($quote, 'quotation'))->toBeNull();

        Log::shouldHaveReceived('warning')->once();
    });
});

describe('map is a superset of the generator folder map', function () {
    it('covers every generator folder-map entry plus AcceptanceReport attachments', function () {
        $generatorMap = (function (): array {
            $reflection = new ReflectionMethod(DocumentPathGenerator::class, 'folderMap');
            $reflection->setAccessible(true);

            return $reflection->invoke(null);
        })();

        $resolverMap = (function (): array {
            $reflection = new ReflectionMethod(DocumentPathResolver::class, 'segmentMap');
            $reflection->setAccessible(true);

            return $reflection->invoke(null);
        })();

        foreach ($generatorMap as $modelClass => $collections) {
            expect($resolverMap)->toHaveKey($modelClass);
            foreach (array_keys($collections) as $collection) {
                expect($resolverMap[$modelClass])->toHaveKey($collection);
            }
        }

        expect($resolverMap)->toHaveKey(AcceptanceReport::class);
        expect($resolverMap[AcceptanceReport::class])->toHaveKey('attachments');
    });
});
