<?php

declare(strict_types=1);

use App\Data\TeamErpSettings;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->actingAs($this->user);
});

test('team erp settings have default values', function (): void {
    $settings = $this->team->getErpSettings();

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

test('team erp settings can be updated', function (): void {
    $this->team->update([
        'erp_settings' => new TeamErpSettings(
            default_currency: 'IDR',
            default_tax_percent: 10.0,
            quote_validity_days: 14,
        ),
    ]);

    // Re-fetch settings to verify persistence
    $this->team->refresh();
    $settings = $this->team->getErpSettings();

    expect($settings->default_currency)->toBe('IDR')
        ->and($settings->default_tax_percent)->toBe(10.0)
        ->and($settings->quote_validity_days)->toBe(14);
});

test('team erp settings company info can be set', function (): void {
    $this->team->update([
        'erp_settings' => new TeamErpSettings(
            company_name: 'Test Company',
            company_address: '123 Test Street',
            company_phone: '+1234567890',
            company_email: 'test@example.com',
        ),
    ]);

    $this->team->refresh();
    $settings = $this->team->getErpSettings();

    expect($settings->company_name)->toBe('Test Company')
        ->and($settings->company_address)->toBe('123 Test Street')
        ->and($settings->company_phone)->toBe('+1234567890')
        ->and($settings->company_email)->toBe('test@example.com');
});

test('team erp settings are isolated per team', function (): void {
    $team1 = Team::factory()->create([
        'erp_settings' => new TeamErpSettings(
            default_currency: 'USD',
            company_name: 'Company One',
        ),
    ]);

    $team2 = Team::factory()->create([
        'erp_settings' => new TeamErpSettings(
            default_currency: 'IDR',
            company_name: 'Company Two',
        ),
    ]);

    expect($team1->getErpSettings()->default_currency)->toBe('USD')
        ->and($team1->getErpSettings()->company_name)->toBe('Company One')
        ->and($team2->getErpSettings()->default_currency)->toBe('IDR')
        ->and($team2->getErpSettings()->company_name)->toBe('Company Two');
});
