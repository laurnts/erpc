<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Enums\ContactRole;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Relaticle\CustomFields\Facades\CustomFields;

final class PeopleRelationManager extends RelationManager
{
    protected static string $relationship = 'people';

    protected static ?string $modelLabel = 'person';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('pivot.role')
                    ->label('Role')
                    ->options(ContactRole::class)
                    ->getOptionLabelUsing(fn (string $value): ?string => ContactRole::tryFrom($value)?->getLabel())
                    
                    ->nullable()
                    ->helperText('Contact role at this company'),
                Toggle::make('pivot.is_primary')
                    ->label('Primary Contact')
                    ->helperText('Mark as primary contact for this company')
                    ->default(false),
                CustomFields::form()->forSchema($schema)->build()
                    ->columnSpanFull()
                    ->columns(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? ContactRole::tryFrom($state)?->getLabel() : null)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'primary' => 'success',
                        'billing' => 'info',
                        'technical' => 'warning',
                        'sales' => 'primary',
                        'support' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('pivot.is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                ...CustomFields::table()->forModel($table->getModel())->columns(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->icon('heroicon-o-plus')->size(Size::Small),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
