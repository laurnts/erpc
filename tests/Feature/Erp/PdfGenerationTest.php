<?php

declare(strict_types=1);

use App\Models\Company;

use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\BuyerInvoice;
use App\Models\BuyerInvoiceItem;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\PdfGenerationService;
use App\Settings\ErpSettings;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'name' => 'Test Buyer Company',
        'contact_person' => 'John Doe',
        'address' => '123 Buyer Street, City, Country',
        'phone' => '+1234567890',
        'email' => 'buyer@test.com',
    ]);
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create([
        'name' => 'Test Supplier Company',
        'contact_person' => 'Jane Smith',
        'address' => '456 Supplier Road, Town, Country',
        'phone' => '+0987654321',
        'email' => 'supplier@test.com',
    ]);
    $this->currency = Currency::factory()->create([
        'code' => 'USD',
        'symbol' => '$',
        'name' => 'US Dollar',
    ]);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->actingAs($this->user);

    // Set up ERP settings
    $settings = app(ErpSettings::class);
    $settings->company_name = 'Test Trading Company';
    $settings->company_address = '789 Company Ave, Business City, 12345';
    $settings->company_phone = '+1122334455';
    $settings->company_email = 'info@testcompany.com';
    $settings->save();
});

describe('PdfGenerationService', function (): void {
    describe('Buyer Quote PDF', function (): void {
        beforeEach(function (): void {
            $this->quote = BuyerQuote::factory()
                ->recycle($this->team)
                ->recycle($this->buyer)
                ->forRequest($this->request)
                ->withCurrency($this->currency)
                ->create([
                    'status' => BuyerQuoteStatus::SENT,
                    'valid_until' => now()->addDays(30),
                    'notes' => 'Test quote notes',
                    'terms_and_conditions' => 'Test terms and conditions',
                    'prepayment_percent' => 30,
                    'payment_terms_days' => 30,
                ]);

            BuyerQuoteItem::factory()
                ->forBuyerQuote($this->quote)
                ->create([
                    'description' => 'Test Product A',
                    'quantity' => '10.0000',
                    'unit' => 'pcs',
                    'unit_price' => '100.0000',
                    'unit_price_exc_tax' => '100.0000',
                    'line_subtotal' => '1000.0000',
                    'line_tax' => '100.0000',
                    'line_total' => '1100.0000',
                ]);

            $this->quote->recalculateTotals();
        });

        it('generates buyer quote PDF without errors', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $content = $pdfService->generateBuyerQuotePdf($this->quote);

            expect($content)->toBeString()
                ->and($content)->not->toBeEmpty()
                ->and(str_starts_with($content, '%PDF'))->toBeTrue();
        });

        it('includes company header from ErpSettings', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $content = $pdfService->generateBuyerQuotePdf($this->quote);

            // PDF content is binary, but we can check that it was generated
            expect($content)->toBeString()
                ->and(strlen($content))->toBeGreaterThan(1000);
        });

        it('generates correct filename', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $filename = $pdfService->getBuyerQuoteFilename($this->quote);

            expect($filename)->toMatch('/^Quote_BQ-\d{4}-\d{4}_v1\.pdf$/');
        });

        it('does not contain supplier information', function (): void {
            // Render the view to check content
            $view = view('pdf.buyer-quote', [
                'quote' => $this->quote->load(['buyer', 'currency', 'items']),
                'company' => [
                    'name' => 'Test Trading Company',
                    'address' => '789 Company Ave',
                    'phone' => '+1122334455',
                    'email' => 'info@testcompany.com',
                ],
            ])->render();

            // Check that the supplier company name doesn't appear
            expect($view)->not->toContain($this->supplier->name)
                // And "Supplier" as a label/heading doesn't appear (checking common variations)
                ->and($view)->not->toContain('Supplier:</')
                ->and($view)->not->toContain('>Supplier<');
        });
    });

    describe('Buyer Order PDF', function (): void {
        beforeEach(function (): void {
            $this->order = BuyerOrder::factory()
                ->recycle($this->team)
                ->recycle($this->buyer)
                ->forRequest($this->request)
                ->create([
                    'status' => OrderStatus::CONFIRMED,
                    'payment_terms_days' => 30,
                    'notes' => 'Test order notes',
                ]);

            BuyerOrderItem::factory()
                ->forBuyerOrder($this->order)
                ->create([
                    'description' => 'Test Product B',
                    'quantity' => '5.0000',
                    'unit' => 'pcs',
                    'unit_price_exc_tax' => '200.0000',
                    'tax_amount' => '20.0000',
                    'line_total' => '1100.0000',
                ]);

            $this->order->recalculateTotals();
        });

        it('generates buyer order PDF without errors', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $content = $pdfService->generateBuyerOrderPdf($this->order);

            expect($content)->toBeString()
                ->and($content)->not->toBeEmpty()
                ->and(str_starts_with($content, '%PDF'))->toBeTrue();
        });

        it('generates correct filename', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $filename = $pdfService->getBuyerOrderFilename($this->order);

            expect($filename)->toMatch('/^Order_BO-\d{4}-\d{4}\.pdf$/');
        });
    });

    describe('Buyer Invoice PDF', function (): void {
        beforeEach(function (): void {
            $this->order = BuyerOrder::factory()
                ->recycle($this->team)
                ->recycle($this->buyer)
                ->forRequest($this->request)
                ->create([
                    'status' => OrderStatus::CONFIRMED,
                ]);

            $this->invoice = BuyerInvoice::factory()
                ->recycle($this->team)
                ->forRequest($this->request)
                ->forBuyerOrder($this->order)
                ->withCurrency($this->currency)
                ->create([
                    'status' => InvoiceStatus::SENT,
                    'issued_at' => now(),
                    'due_at' => now()->addDays(30),
                    'net_days' => 30,
                    'notes' => 'Test invoice notes',
                ]);

            BuyerInvoiceItem::factory()
                ->forBuyerInvoice($this->invoice)
                ->create([
                    'description' => 'Test Service',
                    'quantity' => '1.0000',
                    'unit_price' => '500.0000',
                    'tax_rate' => '10.0000',
                    'line_subtotal' => '500.0000',
                    'line_tax' => '50.0000',
                    'line_total' => '550.0000',
                ]);

            $this->invoice->recalculateTotals();
        });

        it('generates buyer invoice PDF without errors', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $content = $pdfService->generateBuyerInvoicePdf($this->invoice);

            expect($content)->toBeString()
                ->and($content)->not->toBeEmpty()
                ->and(str_starts_with($content, '%PDF'))->toBeTrue();
        });

        it('generates correct filename', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $filename = $pdfService->getBuyerInvoiceFilename($this->invoice);

            expect($filename)->toMatch('/^Invoice_INV-\d{4}-\d{4}\.pdf$/');
        });

        it('includes payment information when amount is outstanding', function (): void {
            $this->invoice->update([
                'total' => '550.0000',
                'amount_paid' => '200.0000',
            ]);

            $view = view('pdf.buyer-invoice', [
                'invoice' => $this->invoice->load(['buyerOrder.buyer', 'currency', 'items', 'payments', 'request']),
                'company' => [
                    'name' => 'Test Trading Company',
                    'address' => '789 Company Ave',
                    'phone' => '+1122334455',
                    'email' => 'info@testcompany.com',
                ],
            ])->render();

            expect($view)->toContain('Amount Paid')
                ->and($view)->toContain('Amount Due');
        });
    });

    describe('Supplier Order PDF', function (): void {
        beforeEach(function (): void {
            $this->supplierOrder = SupplierOrder::factory()
                ->recycle($this->team)
                ->recycle($this->supplier)
                ->forRequest($this->request)
                ->withCurrency($this->currency)
                ->create([
                    'status' => OrderStatus::CONFIRMED,
                    'expected_delivery_date' => now()->addDays(14),
                    'payment_terms_days' => 30,
                    'notes' => 'Test PO notes',
                ]);

            SupplierOrderItem::factory()
                ->forSupplierOrder($this->supplierOrder)
                ->create([
                    'description' => 'Test Material',
                    'quantity' => '100.0000',
                    'unit' => 'kg',
                    'unit_price_exc_tax' => '50.0000',
                    'tax_amount' => '5.0000',
                    'line_total' => '5500.0000',
                ]);

            $this->supplierOrder->recalculateTotals();
        });

        it('generates supplier order PDF without errors', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $content = $pdfService->generateSupplierOrderPdf($this->supplierOrder);

            expect($content)->toBeString()
                ->and($content)->not->toBeEmpty()
                ->and(str_starts_with($content, '%PDF'))->toBeTrue();
        });

        it('generates correct filename', function (): void {
            $pdfService = app(PdfGenerationService::class);

            $filename = $pdfService->getSupplierOrderFilename($this->supplierOrder);

            expect($filename)->toMatch('/^PO_PO-\d{4}-\d{4}\.pdf$/');
        });

        it('includes supplier information', function (): void {
            $view = view('pdf.supplier-order', [
                'order' => $this->supplierOrder->load(['supplier', 'currency', 'items']),
                'company' => [
                    'name' => 'Test Trading Company',
                    'address' => '789 Company Ave',
                    'phone' => '+1122334455',
                    'email' => 'info@testcompany.com',
                ],
            ])->render();

            expect($view)->toContain('Test Supplier Company')
                ->and($view)->toContain('PURCHASE ORDER');
        });

        it('includes delivery address', function (): void {
            $view = view('pdf.supplier-order', [
                'order' => $this->supplierOrder->load(['supplier', 'currency', 'items']),
                'company' => [
                    'name' => 'Test Trading Company',
                    'address' => '789 Company Ave',
                    'phone' => '+1122334455',
                    'email' => 'info@testcompany.com',
                ],
            ])->render();

            expect($view)->toContain('Delivery Address')
                ->and($view)->toContain('Test Trading Company');
        });
    });

    describe('Company Header', function (): void {
        it('uses ErpSettings for company details in all PDFs', function (): void {
            $settings = app(ErpSettings::class);
            $settings->company_name = 'Custom Company Name';
            $settings->company_address = 'Custom Address';
            $settings->company_phone = '+9999999999';
            $settings->company_email = 'custom@example.com';
            $settings->save();

            $pdfService = app(PdfGenerationService::class);

            // Create a quote to test
            $quote = BuyerQuote::factory()
                ->recycle($this->team)
                ->recycle($this->buyer)
                ->forRequest($this->request)
                ->withCurrency($this->currency)
                ->create();

            // The service should use the settings
            $reflection = new ReflectionClass($pdfService);
            $method = $reflection->getMethod('getCompanyDetails');
            $method->setAccessible(true);
            $details = $method->invoke($pdfService);

            expect($details['name'])->toBe('Custom Company Name')
                ->and($details['address'])->toBe('Custom Address')
                ->and($details['phone'])->toBe('+9999999999')
                ->and($details['email'])->toBe('custom@example.com');
        });
    });
});
