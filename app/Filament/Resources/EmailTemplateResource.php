<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\Team;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $modelLabel = 'Email Template';

    protected static ?string $pluralModelLabel = 'Email Templates';

    protected static ?string $slug = 'email-templates';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 17;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Get the form components for creating/editing email templates.
     * This can be reused in other contexts like EmailSettings page.
     *
     * @param  string|null  $defaultType  The default template type (for createOptionForm context)
     * @param  bool  $showLoadButton  Whether to show the "Load Default Template" button
     * @param  string|null  $loadButtonMethod  The Livewire method to call when loading default template (null uses default)
     * @param  bool  $useAlpineJs  Whether to use Alpine.js $wire.call() instead of wire:click (for modal contexts)
     * @param  string|null  $loadButtonParam  Additional parameter to pass to the load method (e.g., template type key)
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getTemplateFormComponents(
        ?string $defaultType = null,
        bool $showLoadButton = true,
        ?string $loadButtonMethod = null,
        bool $useAlpineJs = false,
        ?string $loadButtonParam = null
    ): array {
        $loadButtonMethod ??= 'loadDefaultTemplate';

        $components = [
            Select::make('type')
                ->label('Template Type')
                ->selectablePlaceholder(false)
                ->options([
                    EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
                    EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
                    EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
                    EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
                ])
                ->required()
                ->disabled(fn ($record): bool => $record !== null && $record->is_default)
                ->default($defaultType)
                ->dehydrated()
                ->helperText($defaultType ? 'Template type is automatically set based on the email template section' : 'Template type cannot be changed for default templates')
                ->live(),
        ];

        if ($showLoadButton) {
            if ($useAlpineJs) {
                // Use Alpine.js for modal contexts
                $buttonHtml = '
                    <div class="flex justify-start mt-[-0.5rem] mb-2" 
                         x-data="{ loading: false }"
                         x-on:load-default-template-content.window="
                             loading = false;
                             const content = $event.detail.content;
                             setTimeout(() => {
                                 let contentField = document.getElementById(\'create-template-content-field\');
                                 if (!contentField) {
                                     contentField = document.querySelector(\'textarea[name*=\\\'content\\\']\');
                                 }
                                 if (!contentField) {
                                     const modal = document.querySelector(\'[role=\\\'dialog\\\']\');
                                     if (modal) {
                                         contentField = modal.querySelector(\'textarea\');
                                     }
                                 }
                                 if (contentField) {
                                     contentField.value = content;
                                     contentField.dispatchEvent(new Event(\'input\', { bubbles: true }));
                                     contentField.dispatchEvent(new Event(\'change\', { bubbles: true }));
                                     const wireElement = contentField.closest(\'[wire\\:id]\');
                                     if (wireElement) {
                                         const wireId = wireElement.getAttribute(\'wire\\:id\');
                                         if (wireId && window.Livewire) {
                                             const component = window.Livewire.find(wireId);
                                             if (component) {
                                                 component.set(\'data.content\', content, false);
                                             }
                                         }
                                     }
                                 }
                             }, 100);
                         ">
                        <button 
                            type="button"
                            x-on:click="loading = true; $wire.call(\''.$loadButtonMethod.'\''.($loadButtonParam ? ', \''.$loadButtonParam.'\'' : '').').then(() => { loading = false; })"
                            x-bind:disabled="loading"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none disabled:opacity-50"
                        >
                            <svg x-show="!loading" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            <svg x-show="loading" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-show="!loading">Load Default Template</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                    </div>';
            } else {
                // Use wire:click for regular form contexts
                $buttonHtml = '
                    <div class="flex justify-start mt-[-0.5rem] mb-2">
                        <button 
                            type="button"
                            wire:click.prevent="'.$loadButtonMethod.'"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none disabled:opacity-50"
                        >
                            <svg wire:loading.remove wire:target="'.$loadButtonMethod.'" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            <svg wire:loading wire:target="'.$loadButtonMethod.'" class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="'.$loadButtonMethod.'">Load Default Template</span>
                            <span wire:loading wire:target="'.$loadButtonMethod.'">Loading...</span>
                        </button>
                    </div>';
            }

            $components[] = Placeholder::make('load_default_template')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString($buttonHtml))
                ->visible(fn ($record): bool => $record === null);
        }

        $components[] = TextInput::make('name')
            ->label('Template Name')
            ->required()
            ->maxLength(255)
            ->disabled(fn ($record): bool => $record !== null && $record->is_default)
            ->helperText('A descriptive name for this template');

        $components[] = Textarea::make('content')
            ->label('Template Content')
            ->required()
            ->rows(10)
            ->id($defaultType ? 'create-template-content-field' : null)
            ->disabled(fn ($record): bool => $record !== null && $record->is_default)
            ->helperText('Use {{variable_name}} for dynamic content. Available variables: {{supplier_name}}, {{buyer_name}}, {{quote_number}}, {{order_number}}, etc.');

        return $components;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->schema(self::getTemplateFormComponents())
                    ->columns(1),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
                        EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
                        EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
                        EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EmailTemplate::TYPE_BUYER_QUOTE => 'info',
                        EmailTemplate::TYPE_BUYER_ORDER => 'success',
                        EmailTemplate::TYPE_SUPPLIER_ORDER => 'warning',
                        EmailTemplate::TYPE_DELIVERY_ORDER => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Template Type')
                    ->options([
                        EmailTemplate::TYPE_BUYER_QUOTE => 'Buyer Quote',
                        EmailTemplate::TYPE_BUYER_ORDER => 'Buyer Order',
                        EmailTemplate::TYPE_SUPPLIER_ORDER => 'Supplier Order',
                        EmailTemplate::TYPE_DELIVERY_ORDER => 'Delivery Order',
                    ])
                    ->multiple(),

                SelectFilter::make('is_default')
                    ->label('Status')
                    ->options([
                        '1' => 'Default Templates',
                        '0' => 'Custom Templates',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn (EmailTemplate $record): bool => ! $record->is_default)
                        ->requiresConfirmation()
                        ->action(function (EmailTemplate $record): void {
                            /** @var Team $team */
                            $team = Filament::getTenant();
                            $settings = $team->getErpSettings();
                            $templateType = $record->type;
                            $templateIdField = "email_template_{$templateType}_id";

                            // Check if this template is currently selected
                            $isSelected = isset($settings->{$templateIdField}) && $settings->{$templateIdField} === $record->id;

                            // Delete the template
                            $record->delete();

                            // If template was selected, reset to default (null)
                            if ($isSelected) {
                                $updatedSettings = new \App\Data\TeamErpSettings(
                                    company_name: $settings->company_name,
                                    company_address: $settings->company_address,
                                    company_phone: $settings->company_phone,
                                    company_email: $settings->company_email,
                                    default_currency: $settings->default_currency,
                                    default_tax_percent: $settings->default_tax_percent,
                                    quote_validity_days: $settings->quote_validity_days,
                                    default_payment_terms_days: $settings->default_payment_terms_days,
                                    prices_include_tax: $settings->prices_include_tax,
                                    default_margin_percent: $settings->default_margin_percent,
                                    request_number_prefix: $settings->request_number_prefix,
                                    project_number_prefix: $settings->project_number_prefix,
                                    buyer_quote_number_prefix: $settings->buyer_quote_number_prefix,
                                    buyer_order_number_prefix: $settings->buyer_order_number_prefix,
                                    supplier_order_number_prefix: $settings->supplier_order_number_prefix,
                                    shipment_number_prefix: $settings->shipment_number_prefix,
                                    buyer_invoice_number_prefix: $settings->buyer_invoice_number_prefix,
                                    supplier_invoice_number_prefix: $settings->supplier_invoice_number_prefix,
                                    buyer_payment_number_prefix: $settings->buyer_payment_number_prefix,
                                    supplier_payment_number_prefix: $settings->supplier_payment_number_prefix,
                                    email_from_address: $settings->email_from_address,
                                    email_from_name: $settings->email_from_name,
                                    email_logo_media_id: $settings->email_logo_media_id,
                                    email_signature: $settings->email_signature,
                                    test_email_address: $settings->test_email_address,
                                    smtp_host: $settings->smtp_host,
                                    smtp_port: $settings->smtp_port,
                                    smtp_username: $settings->smtp_username,
                                    smtp_password: $settings->smtp_password,
                                    smtp_encryption: $settings->smtp_encryption,
                                    email_template_buyer_quote_id: $templateType === EmailTemplate::TYPE_BUYER_QUOTE ? null : $settings->email_template_buyer_quote_id,
                                    email_template_buyer_order_id: $templateType === EmailTemplate::TYPE_BUYER_ORDER ? null : $settings->email_template_buyer_order_id,
                                    email_template_supplier_order_id: $templateType === EmailTemplate::TYPE_SUPPLIER_ORDER ? null : $settings->email_template_supplier_order_id,
                                    email_template_delivery_order_id: $templateType === EmailTemplate::TYPE_DELIVERY_ORDER ? null : $settings->email_template_delivery_order_id,
                                );
                                $team->setErpSettings($updatedSettings);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Template Deleted')
                                ->body($isSelected
                                    ? "Template '{$record->name}' has been deleted. The default template is now selected for this type."
                                    : "Template '{$record->name}' has been deleted successfully.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            /** @var Team $team */
                            $team = Filament::getTenant();
                            $settings = $team->getErpSettings();

                            $updatedSettings = $settings;
                            $needsUpdate = false;

                            foreach ($records as $record) {
                                if ($record->is_default) {
                                    continue; // Skip default templates
                                }

                                $templateType = $record->type;
                                $templateIdField = "email_template_{$templateType}_id";

                                // Check if this template is currently selected
                                if (isset($settings->{$templateIdField}) && $settings->{$templateIdField} === $record->id) {
                                    $needsUpdate = true;
                                    $updatedSettings = new \App\Data\TeamErpSettings(
                                        company_name: $settings->company_name,
                                        company_address: $settings->company_address,
                                        company_phone: $settings->company_phone,
                                        company_email: $settings->company_email,
                                        default_currency: $settings->default_currency,
                                        default_tax_percent: $settings->default_tax_percent,
                                        quote_validity_days: $settings->quote_validity_days,
                                        default_payment_terms_days: $settings->default_payment_terms_days,
                                        prices_include_tax: $settings->prices_include_tax,
                                        default_margin_percent: $settings->default_margin_percent,
                                        request_number_prefix: $settings->request_number_prefix,
                                        project_number_prefix: $settings->project_number_prefix,
                                        buyer_quote_number_prefix: $settings->buyer_quote_number_prefix,
                                        buyer_order_number_prefix: $settings->buyer_order_number_prefix,
                                        supplier_order_number_prefix: $settings->supplier_order_number_prefix,
                                        shipment_number_prefix: $settings->shipment_number_prefix,
                                        buyer_invoice_number_prefix: $settings->buyer_invoice_number_prefix,
                                        supplier_invoice_number_prefix: $settings->supplier_invoice_number_prefix,
                                        buyer_payment_number_prefix: $settings->buyer_payment_number_prefix,
                                        supplier_payment_number_prefix: $settings->supplier_payment_number_prefix,
                                        email_from_address: $settings->email_from_address,
                                        email_from_name: $settings->email_from_name,
                                        email_logo_media_id: $settings->email_logo_media_id,
                                        email_signature: $settings->email_signature,
                                        test_email_address: $settings->test_email_address,
                                        smtp_host: $settings->smtp_host,
                                        smtp_port: $settings->smtp_port,
                                        smtp_username: $settings->smtp_username,
                                        smtp_password: $settings->smtp_password,
                                        smtp_encryption: $settings->smtp_encryption,
                                        email_template_buyer_quote_id: $templateType === EmailTemplate::TYPE_BUYER_QUOTE ? null : $settings->email_template_buyer_quote_id,
                                        email_template_buyer_order_id: $templateType === EmailTemplate::TYPE_BUYER_ORDER ? null : $settings->email_template_buyer_order_id,
                                        email_template_supplier_order_id: $templateType === EmailTemplate::TYPE_SUPPLIER_ORDER ? null : $settings->email_template_supplier_order_id,
                                        email_template_delivery_order_id: $templateType === EmailTemplate::TYPE_DELIVERY_ORDER ? null : $settings->email_template_delivery_order_id,
                                    );
                                }

                                $record->delete();
                            }

                            if ($needsUpdate) {
                                $team->setErpSettings($updatedSettings);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Templates Deleted')
                                ->body('Selected templates have been deleted successfully.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordUrl(fn (EmailTemplate $record): string => $record->is_default
                    ? null
                    : EmailTemplateResource::getUrl('edit', ['record' => $record])
            )
            ->recordAction('edit');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<EmailTemplate>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Team|null $team */
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->when($team, fn (Builder $query) => $query->forTeam($team))
            ->orderBy('type')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'type'];
    }
}
