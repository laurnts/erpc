<?php

declare(strict_types=1);

namespace App\Livewire\App\Teams;

use App\Data\TeamErpSettings;
use App\Livewire\BaseLivewireComponent;
use App\Models\Team;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class UpdateTeamCompanyInfo extends BaseLivewireComponent
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;

        $settings = $team->getErpSettings();

        $this->form->fill([
            'company_name' => $settings->company_name,
            'company_address' => $settings->company_address,
            'company_phone' => $settings->company_phone,
            'company_email' => $settings->company_email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Company Information')
                    ->aside()
                    ->description('Your company details will appear on generated documents such as quotes, orders, and invoices.')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),
                        TextInput::make('company_address')
                            ->label('Company Address')
                            ->maxLength(500),
                        TextInput::make('company_phone')
                            ->label('Company Phone')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('company_email')
                            ->label('Company Email')
                            ->email()
                            ->maxLength(255),
                        Actions::make([
                            Action::make('save')
                                ->label('Save')
                                ->action(fn () => $this->updateCompanyInfo()),
                        ])->alignEnd(),
                    ]),
            ])
            ->statePath('data');
    }

    public function updateCompanyInfo(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->sendRateLimitedNotification($exception);

            return;
        }

        $data = $this->form->getState();
        $currentSettings = $this->team->getErpSettings();

        $settings = TeamErpSettings::from([
            ...$currentSettings->toArray(),
            'company_name' => $data['company_name'] ?? '',
            'company_address' => $data['company_address'] ?? '',
            'company_phone' => $data['company_phone'] ?? '',
            'company_email' => $data['company_email'] ?? '',
        ]);

        $this->team->erp_settings = $settings;
        $this->team->save();

        $this->sendNotification('Company Information Saved', 'Your company details have been updated successfully.');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.app.teams.update-team-company-info');
    }
}
