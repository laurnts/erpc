<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

final class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Load Default Template button is now in the form field itself
        ];
    }

    public function loadDefaultTemplate(): void
    {
        // Get the type value directly from the form component state without validation
        // Access the raw state of the 'type' field
        $type = null;
        
        // Try to get type from form state without validation
        try {
            $formState = $this->form->getRawState();
            $type = $formState['type'] ?? null;
        } catch (\Exception $e) {
            // If that fails, try accessing via data property
            $type = $this->data['type'] ?? null;
        }
        
        if (!$type) {
            Notification::make()
                ->title('Template Type Required')
                ->body('Please select a template type first.')
                ->warning()
                ->send();
            return;
        }

        // Map template types to Blade file paths
        $templateFileMap = [
            EmailTemplate::TYPE_BUYER_QUOTE => 'emails/quote-to-buyer.blade.php',
            EmailTemplate::TYPE_BUYER_ORDER => 'emails/buyer-order-to-buyer.blade.php',
            EmailTemplate::TYPE_SUPPLIER_ORDER => 'emails/purchase-order-to-supplier.blade.php',
            EmailTemplate::TYPE_DELIVERY_ORDER => 'emails/shipment-to-buyer.blade.php',
        ];

        $bladeFilePath = $templateFileMap[$type] ?? null;

        if (!$bladeFilePath) {
            Notification::make()
                ->title('Invalid Template Type')
                ->body('No default template file found for this type.')
                ->warning()
                ->send();
            return;
        }

        // Read the Blade file content
        $fullPath = resource_path("views/{$bladeFilePath}");
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->title('Default Template Not Found')
                ->body("Template file not found: {$bladeFilePath}")
                ->warning()
                ->send();
            return;
        }

        $content = file_get_contents($fullPath);

        if ($content === false || empty(trim($content))) {
            Notification::make()
                ->title('Default Template Empty')
                ->body('The default template file is empty.')
                ->warning()
                ->send();
            return;
        }

        // Update only the content field without affecting other fields
        // Get current form state to preserve other fields
        $currentState = $this->data ?? [];
        
        // Update only the content field
        $currentState['content'] = $content;
        $this->data = $currentState;
        
        // Update the form component state directly
        $this->form->getComponent('content')->state($content);
        
        Notification::make()
            ->title('Default Template Loaded')
            ->body('Default template content has been loaded.')
            ->success()
            ->send();
    }
}
