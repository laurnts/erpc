<?php

declare(strict_types=1);

namespace App\Livewire\App\Teams;

use App\Filament\Pages\EditTeam;
use App\Livewire\BaseLivewireComponent;
use App\Models\Team;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UpdateTeamBranding extends BaseLivewireComponent
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Branding')
                    ->aside()
                    ->description('Upload your company logo and favicon. The logo appears in the sidebar, documents, and emails.')
                    ->schema([
                        ViewField::make('current_company_logo')
                            ->label('Current Logo')
                            ->view('filament.components.team-branding-logo-preview')
                            ->viewData(['team' => $this->team])
                            ->visible(fn (): bool => $this->team->getCompanyLogoUrl() !== null),

                        FileUpload::make('company_logo')
                            ->label(fn (): string => $this->team->getCompanyLogoUrl() ? 'Upload New Logo' : 'Company Logo')
                            ->image()
                            ->disk('public')
                            ->directory('team-branding/logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->fetchFileInformation(false)
                            ->helperText('Recommended: PNG or SVG with transparent background. Max 2 MB.'),

                        ViewField::make('current_favicon')
                            ->label('Current Favicon')
                            ->view('filament.components.team-branding-favicon-preview')
                            ->viewData(['team' => $this->team])
                            ->visible(fn (): bool => $this->team->getFaviconUrl() !== null),

                        FileUpload::make('favicon')
                            ->label(fn (): string => $this->team->getFaviconUrl() ? 'Upload New Favicon' : 'Favicon')
                            ->acceptedFileTypes([
                                'image/png',
                                'image/jpeg',
                                'image/svg+xml',
                                'image/x-icon',
                                'image/vnd.microsoft.icon',
                            ])
                            ->disk('public')
                            ->directory('team-branding/favicons')
                            ->visibility('public')
                            ->maxSize(512)
                            ->fetchFileInformation(false)
                            ->helperText('Square image recommended (32×32 or 64×64). Used as the browser tab icon.'),

                        Actions::make([
                            Action::make('remove_company_logo')
                                ->label('Remove Logo')
                                ->icon(Heroicon::Trash)
                                ->color('danger')
                                ->visible(fn (): bool => $this->team->getCompanyLogoUrl() !== null)
                                ->requiresConfirmation()
                                ->action(fn (): mixed => $this->removeCompanyLogo()),
                            Action::make('remove_favicon')
                                ->label('Remove Favicon')
                                ->icon(Heroicon::Trash)
                                ->color('danger')
                                ->visible(fn (): bool => $this->team->getFaviconUrl() !== null)
                                ->requiresConfirmation()
                                ->action(fn (): mixed => $this->removeFavicon()),
                            Action::make('save')
                                ->label('Save')
                                ->submit('saveBranding'),
                        ])->alignEnd(),
                    ]),
            ])
            ->statePath('data');
    }

    public function saveBranding(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->sendRateLimitedNotification($exception);

            return;
        }

        $data = $this->form->getState();
        $saved = false;

        if (array_key_exists('company_logo', $data)) {
            $saved = $this->persistMediaIfChanged($data['company_logo'], 'company_logo') || $saved;
        }

        if (array_key_exists('favicon', $data)) {
            $saved = $this->persistMediaIfChanged($data['favicon'], 'favicon') || $saved;
        }

        if (! $saved) {
            $this->sendNotification(
                'No Changes Saved',
                'Upload a logo or favicon, then click Save.',
                'warning'
            );

            return;
        }

        $this->team->refresh();

        $this->sendNotification('Branding Saved', 'Your workspace branding has been updated successfully.');

        $this->redirect(EditTeam::getUrl(), navigate: false);
    }

    public function removeCompanyLogo(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->sendRateLimitedNotification($exception);

            return;
        }

        $this->team->clearMediaCollection('company_logo');
        $this->team->refresh();

        $this->sendNotification('Logo Removed', 'Company logo has been removed.');

        $this->redirect(EditTeam::getUrl(), navigate: false);
    }

    public function removeFavicon(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->sendRateLimitedNotification($exception);

            return;
        }

        $this->team->clearMediaCollection('favicon');
        $this->team->refresh();

        $this->sendNotification('Favicon Removed', 'Favicon has been removed.');

        $this->redirect(EditTeam::getUrl(), navigate: false);
    }

    private function persistMediaIfChanged(mixed $state, string $collection): bool
    {
        $fullPath = $this->resolveUploadPath($state);

        if ($fullPath === null) {
            return false;
        }

        $existingMedia = $this->team->getFirstMedia($collection);

        if ($existingMedia instanceof Media) {
            $existingPath = realpath($existingMedia->getPath());
            $newPath = realpath($fullPath);

            if ($existingPath !== false && $newPath !== false && $existingPath === $newPath) {
                return false;
            }
        }

        $this->team
            ->addMedia($fullPath)
            ->toMediaCollection($collection);

        return true;
    }

    private function resolveUploadPath(mixed $state): ?string
    {
        if (in_array($state, [null, '', []], true)) {
            return null;
        }

        $filePath = is_array($state) ? ($state[0] ?? null) : $state;

        if (! is_string($filePath) || $filePath === '') {
            return null;
        }

        if (file_exists($filePath)) {
            return $filePath;
        }

        $candidates = [
            Storage::disk('public')->path(ltrim($filePath, '/')),
            storage_path('app/public/'.ltrim($filePath, '/')),
            storage_path('app/'.ltrim($filePath, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        app(LoggerInterface::class)->warning('Team branding upload file not found.', [
            'team_id' => $this->team->getKey(),
            'file_path' => $filePath,
            'candidates' => $candidates,
        ]);

        return null;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.app.teams.update-team-branding');
    }
}
