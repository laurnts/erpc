<?php

declare(strict_types=1);

use App\Models\Buyer;
use App\Models\BuyerOrder;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Buyer::factory()->recycle($this->team)->create();
    $this->supplier = Supplier::factory()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD']);
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->actingAs($this->user);
});

describe('Request Buyer Total Calculation', function (): void {
    it('returns zero when no buyer orders exist', function (): void {
        expect($this->request->buyer_total)->toBe(0.0);
    });

    it('calculates buyer total from single buyer order', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 100, 1100)
            ->create();

        expect($this->request->buyer_total)->toBe(1100.0);
    });

    it('calculates buyer total from multiple buyer orders', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(500, 50, 550)
            ->create();

        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 100, 1100)
            ->create();

        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(750, 75, 825)
            ->create();

        expect($this->request->buyer_total)->toBe(2475.0);
    });
});

describe('Request Supplier Cost Calculation', function (): void {
    it('returns zero when no supplier orders exist', function (): void {
        expect($this->request->supplier_cost)->toBe(0.0);
    });

    it('calculates supplier cost from single supplier order', function (): void {
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(800, 80, 880)
            ->create();

        expect($this->request->supplier_cost)->toBe(880.0);
    });

    it('calculates supplier cost from multiple supplier orders', function (): void {
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(300, 30, 330)
            ->create();

        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(500, 50, 550)
            ->create();

        expect($this->request->supplier_cost)->toBe(880.0);
    });

    it('uses base_total for supplier cost (currency conversion)', function (): void {
        // Create supplier order with different base total (simulating exchange rate)
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->create([
                'exchange_rate' => '1.50000000',
                'subtotal' => '1000.0000',
                'tax_total' => '100.0000',
                'total' => '1100.0000',
                'base_subtotal' => '1500.0000',
                'base_tax_total' => '150.0000',
                'base_total' => '1650.0000',
            ]);

        // Should use base_total (1650) not total (1100)
        expect($this->request->supplier_cost)->toBe(1650.0);
    });
});

describe('Request Gross Margin Calculation', function (): void {
    it('returns zero when no orders exist', function (): void {
        expect($this->request->gross_margin)->toBe(0.0);
    });

    it('calculates gross margin correctly', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 0, 1000)
            ->create();

        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(700, 0, 700)
            ->create();

        // Gross margin = buyer_total - supplier_cost = 1000 - 700 = 300
        expect($this->request->gross_margin)->toBe(300.0);
    });

    it('handles negative margin when cost exceeds revenue', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(500, 0, 500)
            ->create();

        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(800, 0, 800)
            ->create();

        // Gross margin = 500 - 800 = -300
        expect($this->request->gross_margin)->toBe(-300.0);
    });
});

describe('Request Margin Percent Calculation', function (): void {
    it('returns zero when buyer total is zero', function (): void {
        expect($this->request->margin_percent)->toBe(0.0);
    });

    it('calculates margin percent correctly', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 0, 1000)
            ->create();

        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(700, 0, 700)
            ->create();

        // Margin percent = (300 / 1000) * 100 = 30%
        expect($this->request->margin_percent)->toBe(30.0);
    });

    it('handles 100% margin when no supplier cost', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(1000, 0, 1000)
            ->create();

        // No supplier orders = 100% margin
        expect($this->request->margin_percent)->toBe(100.0);
    });

    it('handles negative margin percent', function (): void {
        BuyerOrder::factory()
            ->recycle($this->team)
            ->recycle($this->buyer)
            ->forRequest($this->request)
            ->withTotals(500, 0, 500)
            ->create();

        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(750, 0, 750)
            ->create();

        // Margin percent = (-250 / 500) * 100 = -50%
        expect($this->request->margin_percent)->toBe(-50.0);
    });

    it('does not throw division by zero error', function (): void {
        // Only supplier orders, no buyer orders
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(500, 0, 500)
            ->create();

        // Should return 0.0 not throw error
        expect($this->request->margin_percent)->toBe(0.0);
    });
});

describe('Request Amount Collected Calculation', function (): void {
    beforeEach(function (): void {
        // Skip if tables don't exist
        if (! Schema::hasTable('buyer_invoices') || ! Schema::hasTable('buyer_payments')) {
            $this->markTestSkipped('Invoice and payment tables not yet migrated');
        }
    });

    it('returns zero when no payments exist', function (): void {
        expect($this->request->amount_collected)->toBe(0.0);
    });

    it('calculates amount collected from buyer payments', function (): void {
        // Create buyer invoice (without buyer_id since it doesn't exist in schema)
        $invoiceId = DB::table('buyer_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'invoice_number' => 'INV-2024-0001',
            'type' => 'standard',
            'status' => 'sent',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'tax_total' => 100,
            'total' => 1100,
            'amount_paid' => 500,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create buyer payment (payment_method is required)
        DB::table('buyer_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'buyer_invoice_id' => $invoiceId,
            'payment_number' => 'PAY-2024-0001',
            'payment_method' => 'bank_transfer',
            'amount' => 500,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($this->request->amount_collected)->toBe(500.0);
    });

    it('calculates amount collected from multiple payments', function (): void {
        // Create buyer invoice
        $invoiceId = DB::table('buyer_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'invoice_number' => 'INV-2024-0002',
            'type' => 'standard',
            'status' => 'partial',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'tax_total' => 100,
            'total' => 1100,
            'amount_paid' => 700,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create multiple payments
        DB::table('buyer_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'buyer_invoice_id' => $invoiceId,
            'payment_number' => 'PAY-2024-0002',
            'payment_method' => 'bank_transfer',
            'amount' => 300,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buyer_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'buyer_invoice_id' => $invoiceId,
            'payment_number' => 'PAY-2024-0003',
            'payment_method' => 'cash',
            'amount' => 400,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($this->request->amount_collected)->toBe(700.0);
    });

    it('excludes payments from deleted invoices', function (): void {
        // Create and soft-delete buyer invoice
        $invoiceId = DB::table('buyer_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'invoice_number' => 'INV-2024-0003',
            'type' => 'standard',
            'status' => 'sent',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'tax_total' => 0,
            'total' => 1000,
            'amount_paid' => 1000,
            'net_days' => 30,
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buyer_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'buyer_invoice_id' => $invoiceId,
            'payment_number' => 'PAY-2024-0004',
            'payment_method' => 'bank_transfer',
            'amount' => 1000,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should not include payment from deleted invoice
        expect($this->request->amount_collected)->toBe(0.0);
    });
});

describe('Request Amount Paid Out Calculation', function (): void {
    beforeEach(function (): void {
        // Skip if tables don't exist
        if (! Schema::hasTable('supplier_invoices') || ! Schema::hasTable('supplier_payments')) {
            $this->markTestSkipped('Invoice and payment tables not yet migrated');
        }
    });

    it('returns zero when no payments exist', function (): void {
        expect($this->request->amount_paid_out)->toBe(0.0);
    });

    it('calculates amount paid out to suppliers', function (): void {
        // Create supplier invoice (reference_number is required and unique)
        $invoiceId = DB::table('supplier_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'invoice_number' => 'SINV-2024-0001',
            'reference_number' => 'SREF-2024-0001',
            'type' => 'standard',
            'status' => 'sent',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 800,
            'tax_total' => 80,
            'total' => 880,
            'amount_paid' => 880,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create supplier payment
        DB::table('supplier_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'supplier_invoice_id' => $invoiceId,
            'payment_number' => 'SPAY-2024-0001',
            'payment_method' => 'bank_transfer',
            'amount' => 880,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($this->request->amount_paid_out)->toBe(880.0);
    });

    it('calculates amount paid out from multiple supplier payments', function (): void {
        // Create supplier invoice
        $invoiceId = DB::table('supplier_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'invoice_number' => 'SINV-2024-0002',
            'reference_number' => 'SREF-2024-0002',
            'type' => 'standard',
            'status' => 'partial',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'tax_total' => 0,
            'total' => 1000,
            'amount_paid' => 1000,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create multiple payments
        DB::table('supplier_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'supplier_invoice_id' => $invoiceId,
            'payment_number' => 'SPAY-2024-0002',
            'payment_method' => 'bank_transfer',
            'amount' => 400,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'supplier_invoice_id' => $invoiceId,
            'payment_number' => 'SPAY-2024-0003',
            'payment_method' => 'cash',
            'amount' => 600,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($this->request->amount_paid_out)->toBe(1000.0);
    });

    it('excludes payments from deleted invoices', function (): void {
        // Create and soft-delete supplier invoice
        $invoiceId = DB::table('supplier_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'invoice_number' => 'SINV-2024-0003',
            'reference_number' => 'SREF-2024-0003',
            'type' => 'standard',
            'status' => 'sent',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 500,
            'tax_total' => 0,
            'total' => 500,
            'amount_paid' => 500,
            'net_days' => 30,
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'supplier_invoice_id' => $invoiceId,
            'payment_number' => 'SPAY-2024-0004',
            'payment_method' => 'bank_transfer',
            'amount' => 500,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should not include payment from deleted invoice
        expect($this->request->amount_paid_out)->toBe(0.0);
    });
});

describe('Request Net Cash Flow Calculation', function (): void {
    beforeEach(function (): void {
        // Skip if tables don't exist
        if (! Schema::hasTable('buyer_invoices') || ! Schema::hasTable('supplier_invoices')) {
            $this->markTestSkipped('Invoice tables not yet migrated');
        }
    });

    it('returns zero when no payments exist', function (): void {
        expect($this->request->net_cash_flow)->toBe(0.0);
    });

    it('calculates net cash flow correctly', function (): void {
        // Create buyer invoice and payment (collected)
        $buyerInvoiceId = DB::table('buyer_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'invoice_number' => 'INV-2024-CF001',
            'type' => 'standard',
            'status' => 'paid',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'tax_total' => 0,
            'total' => 1000,
            'amount_paid' => 1000,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buyer_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'buyer_invoice_id' => $buyerInvoiceId,
            'payment_number' => 'PAY-2024-CF001',
            'payment_method' => 'bank_transfer',
            'amount' => 1000,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create supplier invoice and payment (paid out)
        $supplierInvoiceId = DB::table('supplier_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'invoice_number' => 'SINV-2024-CF001',
            'reference_number' => 'SREF-2024-CF001',
            'type' => 'standard',
            'status' => 'paid',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 700,
            'tax_total' => 0,
            'total' => 700,
            'amount_paid' => 700,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'supplier_invoice_id' => $supplierInvoiceId,
            'payment_number' => 'SPAY-2024-CF001',
            'payment_method' => 'bank_transfer',
            'amount' => 700,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Net cash flow = amount_collected - amount_paid_out = 1000 - 700 = 300
        expect($this->request->net_cash_flow)->toBe(300.0);
    });

    it('handles negative cash flow when paid out exceeds collected', function (): void {
        // Create buyer invoice and payment (collected)
        $buyerInvoiceId = DB::table('buyer_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'invoice_number' => 'INV-2024-CF002',
            'type' => 'standard',
            'status' => 'partial',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'tax_total' => 0,
            'total' => 1000,
            'amount_paid' => 300,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buyer_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'buyer_invoice_id' => $buyerInvoiceId,
            'payment_number' => 'PAY-2024-CF002',
            'payment_method' => 'bank_transfer',
            'amount' => 300,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create supplier invoice and payment (paid out)
        $supplierInvoiceId = DB::table('supplier_invoices')->insertGetId([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'request_id' => $this->request->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'invoice_number' => 'SINV-2024-CF002',
            'reference_number' => 'SREF-2024-CF002',
            'type' => 'standard',
            'status' => 'paid',
            'currency_id' => $this->currency->getKey(),
            'exchange_rate' => 1,
            'subtotal' => 700,
            'tax_total' => 0,
            'total' => 700,
            'amount_paid' => 700,
            'net_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_payments')->insert([
            'team_id' => $this->team->getKey(),
            'creator_id' => $this->user->getKey(),
            'supplier_invoice_id' => $supplierInvoiceId,
            'payment_number' => 'SPAY-2024-CF002',
            'payment_method' => 'bank_transfer',
            'amount' => 700,
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Net cash flow = 300 - 700 = -400
        expect($this->request->net_cash_flow)->toBe(-400.0);
    });
});

describe('Request P&L with Zero Values', function (): void {
    it('handles all zero values without errors', function (): void {
        // Fresh request with no orders or payments
        expect($this->request->buyer_total)->toBe(0.0)
            ->and($this->request->supplier_cost)->toBe(0.0)
            ->and($this->request->gross_margin)->toBe(0.0)
            ->and($this->request->margin_percent)->toBe(0.0)
            ->and($this->request->amount_collected)->toBe(0.0)
            ->and($this->request->amount_paid_out)->toBe(0.0)
            ->and($this->request->net_cash_flow)->toBe(0.0);
    });

    it('does not throw division by zero when calculating margin with zero buyer total', function (): void {
        // Only supplier orders, no buyer orders
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->withTotals(500, 50, 550)
            ->create();

        // All calculations should return float 0 or negative values without error
        expect($this->request->buyer_total)->toBe(0.0)
            ->and($this->request->supplier_cost)->toBe(550.0)
            ->and($this->request->gross_margin)->toBe(-550.0)
            ->and($this->request->margin_percent)->toBe(0.0); // 0 because buyer_total is 0
    });
});

describe('Request P&L with Multiple Currencies', function (): void {
    it('uses base currency for supplier cost calculation', function (): void {
        // Create a different currency with exchange rate
        $eurCurrency = Currency::factory()->create(['code' => 'EUR']);

        // Create supplier orders in different currencies
        // USD order
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($this->currency)
            ->forRequest($this->request)
            ->create([
                'exchange_rate' => '1.00000000',
                'subtotal' => '500.0000',
                'tax_total' => '0.0000',
                'total' => '500.0000',
                'base_subtotal' => '500.0000',
                'base_tax_total' => '0.0000',
                'base_total' => '500.0000',
            ]);

        // EUR order with exchange rate of 1.2 (EUR->USD)
        SupplierOrder::factory()
            ->recycle($this->team)
            ->forSupplier($this->supplier)
            ->withCurrency($eurCurrency)
            ->forRequest($this->request)
            ->create([
                'exchange_rate' => '1.20000000',
                'subtotal' => '400.0000',
                'tax_total' => '0.0000',
                'total' => '400.0000',
                'base_subtotal' => '480.0000',
                'base_tax_total' => '0.0000',
                'base_total' => '480.0000',
            ]);

        // Total supplier cost should be sum of base_totals: 500 + 480 = 980
        expect($this->request->supplier_cost)->toBe(980.0);
    });
});
