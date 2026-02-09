<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderApprovals;

use App\Enums\CentralPurchasingRole;
use App\Enums\OrderStatus;
use App\Filament\Resources\SupplierOrderApprovals\Pages\ListSupplierOrderApprovals;
use App\Filament\Resources\SupplierOrderApprovals\Pages\ViewSupplierOrderApproval;
use App\Models\SupplierOrder;
use App\Policies\SupplierOrderApprovalPolicy;
use App\Services\TeamMemberService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

final class SupplierOrderApprovalResource extends Resource
{
    protected static ?string $model = SupplierOrder::class;

    protected static ?string $policy = SupplierOrderApprovalPolicy::class;

    protected static ?string $recordTitleAttribute = 'po_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 24;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Supplier Orders';

    protected static ?string $pluralModelLabel = 'Supplier Order Approvals';

    protected static ?string $modelLabel = 'Supplier Order Approval';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    ->sortable()
                    ->url(fn (SupplierOrder $record): string => \App\Filament\Resources\RequestResource::getUrl('view', ['record' => $record->request_id])),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->description(fn (SupplierOrder $record): string => $record->currency->code ?? ''),
                TextColumn::make('confirmed_at')
                    ->label('Confirmed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approver_1')
                    ->label('Approver 1')
                    ->getStateUsing(function (SupplierOrder $record): string {
                        if ($record->approver_1_id === null) {
                            return 'Pending';
                        }
                        // Ensure relationship is loaded
                        if (! $record->relationLoaded('approver1')) {
                            $record->load('approver1');
                        }
                        return $record->approver1->name ?? 'Unknown';
                    })
                    ->badge()
                    ->color(fn (SupplierOrder $record): string => $record->approver_1_id === null ? 'warning' : 'success'),
                TextColumn::make('approver_2')
                    ->label('Approver 2')
                    ->getStateUsing(function (SupplierOrder $record): string {
                        if ($record->approver_2_id === null) {
                            return 'Pending';
                        }
                        // Ensure relationship is loaded
                        if (! $record->relationLoaded('approver2')) {
                            $record->load('approver2');
                        }
                        return $record->approver2->name ?? 'Unknown';
                    })
                    ->badge()
                    ->color(fn (SupplierOrder $record): string => $record->approver_2_id === null ? 'warning' : 'success'),
            ])
            ->defaultSort('confirmed_at', 'desc')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name', fn ($query) => $query->where('is_supplier', true))
                    ->label('Supplier')
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->url(fn (SupplierOrder $record): string => self::getUrl('view', ['record' => $record])),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(function (?SupplierOrder $record): bool {
                            if ($record === null) {
                                return false;
                            }

                            // Must be in confirmed status
                            if ($record->status !== OrderStatus::CONFIRMED) {
                                return false;
                            }

                            // User must be authenticated
                            $user = auth()->user();
                            if ($user === null) {
                                return false;
                            }

                            // Check if user can approve
                            if (! $record->canBeApprovedBy($user)) {
                                return false;
                            }

                            // Check if user already approved
                            if ($record->approver_1_id === $user->id || $record->approver_2_id === $user->id) {
                                return false;
                            }

                            return true;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Approve this supplier order?')
                        ->modalDescription(function (SupplierOrder $record): string {
                            $approverCount = 0;
                            if ($record->approver_1_id !== null) {
                                $approverCount++;
                            }
                            if ($record->approver_2_id !== null) {
                                $approverCount++;
                            }

                            $remaining = 2 - $approverCount;
                            $description = 'This order requires approval from at least 2 approvers. ';
                            
                            if ($approverCount === 0) {
                                $description .= 'You will be the first approver. One more approval is needed.';
                            } elseif ($approverCount === 1) {
                                $description .= 'One approval has been received. Your approval will complete the approval process and the order will be ready to send.';
                            }

                            return $description;
                        })
                        ->action(function (SupplierOrder $record): void {
                            /** @var \App\Models\User $user */
                            $user = auth()->user();
                            
                            try {
                                $record->approve($user);
                                
                                $approverCount = 0;
                                if ($record->approver_1_id !== null) {
                                    $approverCount++;
                                }
                                if ($record->approver_2_id !== null) {
                                    $approverCount++;
                                }

                                if ($approverCount === 2) {
                                    Notification::make()
                                        ->title('Order approved')
                                        ->body('Order has been fully approved and is now ready to send to supplier.')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Approval recorded')
                                        ->body('Your approval has been recorded. One more approval is needed.')
                                        ->success()
                                        ->send();
                                }
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Cannot approve')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSupplierOrderApprovals::route('/'),
            'view' => ViewSupplierOrderApproval::route('/{record}'),
        ];
    }

    /**
     * Filter query to show only orders needing approval.
     *
     * @return Builder<SupplierOrder>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();
        
        if ($team === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0'); // Return empty query
        }

        // Get current user
        $user = auth()->user();
        if ($user === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0'); // Return empty query
        }

        // Check if user has approval role
        $approvalRoles = [
            CentralPurchasingRole::DEPT_HEAD_SALES,
            CentralPurchasingRole::DEPUTY_DIRECTOR,
            CentralPurchasingRole::DIRECTOR,
        ];

        $hasApprovalRole = false;
        foreach ($approvalRoles as $role) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, $role);
            if ($members->contains('id', $user->id)) {
                $hasApprovalRole = true;
                break;
            }
        }

        if (! $hasApprovalRole) {
            return parent::getEloquentQuery()->whereRaw('1 = 0'); // Return empty query
        }

        // Show APPROVED orders (all of them) OR CONFIRMED orders that need approval
        // APPROVED orders: show all (they're fully approved but not yet sent)
        // CONFIRMED orders: show those that still need at least one approval
        // This ensures approved orders stay visible and show approver names
        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($user): void {
                // Always show APPROVED orders (fully approved, ready to send)
                $query->where('status', OrderStatus::APPROVED)
                    // OR show CONFIRMED orders that still need at least one approval
                    ->orWhere(function (Builder $q): void {
                        $q->where('status', OrderStatus::CONFIRMED)
                            ->where(function (Builder $subQ): void {
                                // Still needs at least one approval
                                $subQ->whereNull('approver_1_id')
                                    ->orWhereNull('approver_2_id');
                            });
                    });
            })
            ->with(['supplier', 'request', 'approver1', 'approver2', 'currency', 'items']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['po_number', 'supplier.name'];
    }

    /**
     * Control navigation visibility - show only to users with approval roles.
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();
        
        if ($team === null) {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        // Check if user has one of the approval roles
        $approvalRoles = [
            CentralPurchasingRole::DEPT_HEAD_SALES,
            CentralPurchasingRole::DEPUTY_DIRECTOR,
            CentralPurchasingRole::DIRECTOR,
        ];

        foreach ($approvalRoles as $role) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, $role);
            if ($members->contains('id', $user->id)) {
                return true;
            }
        }

        // Also show to users with admin permissions (if they have view supplier orders permission)
        // This allows administrators to see the menu even if they don't have approval roles
        if ($user->hasPermissionTo('view supplier orders')) {
            return true;
        }

        return false;
    }
}
