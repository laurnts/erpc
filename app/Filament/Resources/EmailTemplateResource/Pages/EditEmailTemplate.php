<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Models\Team;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => !$this->record->is_default)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var EmailTemplate $record */
                    $record = $this->record;
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

                    Notification::make()
                        ->title('Template Deleted')
                        ->body($isSelected 
                            ? "Template '{$record->name}' has been deleted. The default template is now selected for this type."
                            : "Template '{$record->name}' has been deleted successfully.")
                        ->success()
                        ->send();

                    $this->redirect(EmailTemplateResource::getUrl('index'));
                }),
        ];
    }


    protected function getRedirectUrl(): string
    {
        return EmailTemplateResource::getUrl('index');
    }
}
