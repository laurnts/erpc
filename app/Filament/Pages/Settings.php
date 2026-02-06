<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Data\TeamErpSettings;
use App\Models\Currency;
use App\Models\Team;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * @property Schema $erpForm
 * @property Schema $prefixForm
 */
final class Settings extends Page implements HasForms
{
    use InteractsWithForms;
    use WithRateLimiting;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'general';

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $erpData = [];

    /** @var array<string, mixed>|null */
    public ?array $prefixData = [];

    public function getTitle(): string
    {
        return 'General';
    }

    public static function getNavigationLabel(): string
    {
        return 'General';
    }

    public function mount(): void
    {
        /** @var Team $team */
        $team = Filament::getTenant();
        $settings = $team->getErpSettings();

        $this->erpForm->fill([
            'default_currency' => $settings->default_currency,
            'quote_validity_days' => $settings->quote_validity_days,
            'default_payment_terms_days' => $settings->default_payment_terms_days,
            'default_margin_percent' => $settings->default_margin_percent,
        ]);

        $this->prefixForm->fill([
            'request_number_prefix' => $settings->request_number_prefix,
            'project_number_prefix' => $settings->project_number_prefix,
            'buyer_quote_number_prefix' => $settings->buyer_quote_number_prefix,
            'buyer_order_number_prefix' => $settings->buyer_order_number_prefix,
            'supplier_order_number_prefix' => $settings->supplier_order_number_prefix,
            'shipment_number_prefix' => $settings->shipment_number_prefix,
            'buyer_invoice_number_prefix' => $settings->buyer_invoice_number_prefix,
            'supplier_invoice_number_prefix' => $settings->supplier_invoice_number_prefix,
            'buyer_payment_number_prefix' => $settings->buyer_payment_number_prefix,
            'supplier_payment_number_prefix' => $settings->supplier_payment_number_prefix,
        ]);
    }

    /**
     * @return array<string>
     */
    protected function getForms(): array
    {
        return [
            'erpForm',
            'prefixForm',
        ];
    }

    public function erpForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('default_currency')
                    ->label('Default Currency')
                    ->selectablePlaceholder(false)
                    ->options(fn (): array => Currency::query()
                        ->where('is_active', true)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Currency $currency): array => [
                            $currency->code => "{$currency->code} - {$currency->name}",
                        ])
                        ->all()
                    )
                    
                    ->required(),
                TextInput::make('quote_validity_days')
                    ->label('Quote Validity (Days)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(365),
                TextInput::make('default_payment_terms_days')
                    ->label('Default Payment Terms (Days)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(365),
                TextInput::make('default_margin_percent')
                    ->label('Default Margin %')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.1)
                    ->suffix('%')
                    ->helperText('Applied to buyer quotes based on supplier cost price'),
            ])
            ->statePath('erpData');
    }

    public function prefixForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('request_number_prefix')
                    ->label('Request Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('project_number_prefix')
                    ->label('Project Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('buyer_quote_number_prefix')
                    ->label('Buyer Quote Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('buyer_order_number_prefix')
                    ->label('Buyer Order Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('supplier_order_number_prefix')
                    ->label('Supplier Order (PO) Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('shipment_number_prefix')
                    ->label('Shipment Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('buyer_invoice_number_prefix')
                    ->label('Buyer Invoice Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('supplier_invoice_number_prefix')
                    ->label('Supplier Invoice Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('buyer_payment_number_prefix')
                    ->label('Buyer Payment Prefix')
                    ->required()
                    ->maxLength(10),
                TextInput::make('supplier_payment_number_prefix')
                    ->label('Supplier Payment Prefix')
                    ->required()
                    ->maxLength(10),
            ])
            ->columns(2)
            ->statePath('prefixData');
    }

    public function saveErpSettings(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Too many requests')
                ->body("Please wait {$exception->secondsUntilAvailable} seconds before trying again.")
                ->danger()
                ->send();

            return;
        }

        /** @var Team $team */
        $team = Filament::getTenant();
        $currentSettings = $team->getErpSettings();
        $erpData = $this->erpForm->getState();

        $settings = new TeamErpSettings(
            company_name: $currentSettings->company_name,
            company_address: $currentSettings->company_address,
            company_phone: $currentSettings->company_phone,
            company_email: $currentSettings->company_email,
            default_currency: $erpData['default_currency'] ?? 'USD',
            quote_validity_days: (int) ($erpData['quote_validity_days'] ?? 30),
            default_payment_terms_days: (int) ($erpData['default_payment_terms_days'] ?? 30),
            default_margin_percent: (float) ($erpData['default_margin_percent'] ?? 3.0),
            request_number_prefix: $currentSettings->request_number_prefix,
            project_number_prefix: $currentSettings->project_number_prefix,
            buyer_quote_number_prefix: $currentSettings->buyer_quote_number_prefix,
            buyer_order_number_prefix: $currentSettings->buyer_order_number_prefix,
            supplier_order_number_prefix: $currentSettings->supplier_order_number_prefix,
            shipment_number_prefix: $currentSettings->shipment_number_prefix,
            buyer_invoice_number_prefix: $currentSettings->buyer_invoice_number_prefix,
            supplier_invoice_number_prefix: $currentSettings->supplier_invoice_number_prefix,
            buyer_payment_number_prefix: $currentSettings->buyer_payment_number_prefix,
            supplier_payment_number_prefix: $currentSettings->supplier_payment_number_prefix,
            email_from_address: $currentSettings->email_from_address,
            email_from_name: $currentSettings->email_from_name,
            email_logo_media_id: $currentSettings->email_logo_media_id,
            email_signature: $currentSettings->email_signature,
            smtp_host: $currentSettings->smtp_host,
            smtp_port: $currentSettings->smtp_port,
            smtp_username: $currentSettings->smtp_username,
            smtp_password: $currentSettings->smtp_password,
            smtp_encryption: $currentSettings->smtp_encryption,
            email_template_buyer_quote: $currentSettings->email_template_buyer_quote,
            email_template_buyer_order: $currentSettings->email_template_buyer_order,
            email_template_supplier_order: $currentSettings->email_template_supplier_order,
            email_template_delivery_order: $currentSettings->email_template_delivery_order,
        );

        $team->erp_settings = $settings;
        $team->save();

        $this->sendNotification('Settings Saved', 'Default settings have been updated successfully.');
    }

    public function savePrefixSettings(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Too many requests')
                ->body("Please wait {$exception->secondsUntilAvailable} seconds before trying again.")
                ->danger()
                ->send();

            return;
        }

        /** @var Team $team */
        $team = Filament::getTenant();
        $currentSettings = $team->getErpSettings();
        $prefixData = $this->prefixForm->getState();

        $settings = new TeamErpSettings(
            company_name: $currentSettings->company_name,
            company_address: $currentSettings->company_address,
            company_phone: $currentSettings->company_phone,
            company_email: $currentSettings->company_email,
            default_currency: $currentSettings->default_currency,
            quote_validity_days: $currentSettings->quote_validity_days,
            default_payment_terms_days: $currentSettings->default_payment_terms_days,
            default_margin_percent: $currentSettings->default_margin_percent,
            request_number_prefix: $prefixData['request_number_prefix'] ?? 'REQ',
            project_number_prefix: $prefixData['project_number_prefix'] ?? 'PRJ',
            buyer_quote_number_prefix: $prefixData['buyer_quote_number_prefix'] ?? 'BQ',
            buyer_order_number_prefix: $prefixData['buyer_order_number_prefix'] ?? 'BO',
            supplier_order_number_prefix: $prefixData['supplier_order_number_prefix'] ?? 'PO',
            shipment_number_prefix: $prefixData['shipment_number_prefix'] ?? 'SHP',
            buyer_invoice_number_prefix: $prefixData['buyer_invoice_number_prefix'] ?? 'INV',
            supplier_invoice_number_prefix: $prefixData['supplier_invoice_number_prefix'] ?? 'SI',
            buyer_payment_number_prefix: $prefixData['buyer_payment_number_prefix'] ?? 'PAY',
            supplier_payment_number_prefix: $prefixData['supplier_payment_number_prefix'] ?? 'SP',
            email_from_address: $currentSettings->email_from_address,
            email_from_name: $currentSettings->email_from_name,
            email_logo_media_id: $currentSettings->email_logo_media_id,
            email_signature: $currentSettings->email_signature,
            smtp_host: $currentSettings->smtp_host,
            smtp_port: $currentSettings->smtp_port,
            smtp_username: $currentSettings->smtp_username,
            smtp_password: $currentSettings->smtp_password,
            smtp_encryption: $currentSettings->smtp_encryption,
            email_template_buyer_quote: $currentSettings->email_template_buyer_quote,
            email_template_buyer_order: $currentSettings->email_template_buyer_order,
            email_template_supplier_order: $currentSettings->email_template_supplier_order,
            email_template_delivery_order: $currentSettings->email_template_delivery_order,
        );

        $team->erp_settings = $settings;
        $team->save();

        $this->sendNotification('Prefixes Saved', 'Document number prefixes have been updated successfully.');
    }

    protected function sendNotification(string $title, ?string $message = null): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->success()
            ->send();
    }
}
