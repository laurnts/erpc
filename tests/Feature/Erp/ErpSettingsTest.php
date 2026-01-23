<?php

declare(strict_types=1);

use App\Models\Company;

use App\Settings\ErpSettings;

test('erp settings have default values', function () {
    $settings = app(ErpSettings::class);

    expect($settings->default_currency)->toBe('USD')
        ->and($settings->default_tax_percent)->toBe(11.0)
        ->and($settings->quote_validity_days)->toBe(30)
        ->and($settings->default_payment_terms_days)->toBe(30)
        ->and($settings->prices_include_tax)->toBeFalse()
        ->and($settings->request_number_prefix)->toBe('REQ')
        ->and($settings->buyer_quote_number_prefix)->toBe('BQ')
        ->and($settings->buyer_order_number_prefix)->toBe('BO')
        ->and($settings->supplier_order_number_prefix)->toBe('PO')
        ->and($settings->buyer_invoice_number_prefix)->toBe('INV')
        ->and($settings->project_number_prefix)->toBe('PRJ');
});

test('erp settings can be updated', function () {
    $settings = app(ErpSettings::class);

    $settings->default_currency = 'IDR';
    $settings->default_tax_percent = 10.0;
    $settings->quote_validity_days = 14;
    $settings->save();

    // Re-fetch settings to verify persistence
    $updatedSettings = app(ErpSettings::class);

    expect($updatedSettings->default_currency)->toBe('IDR')
        ->and($updatedSettings->default_tax_percent)->toBe(10.0)
        ->and($updatedSettings->quote_validity_days)->toBe(14);
});

test('erp settings company info can be set', function () {
    $settings = app(ErpSettings::class);

    $settings->company_name = 'Test Company';
    $settings->company_address = '123 Test Street';
    $settings->company_phone = '+1234567890';
    $settings->company_email = 'test@example.com';
    $settings->save();

    $updatedSettings = app(ErpSettings::class);

    expect($updatedSettings->company_name)->toBe('Test Company')
        ->and($updatedSettings->company_address)->toBe('123 Test Street')
        ->and($updatedSettings->company_phone)->toBe('+1234567890')
        ->and($updatedSettings->company_email)->toBe('test@example.com');
});

test('erp settings group is erp', function () {
    expect(ErpSettings::group())->toBe('erp');
});
