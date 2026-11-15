<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Core\Plugins\Contracts\UsersTableColumnContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        $columns = [
            TextColumn::make('name')
                ->label('الاسم')
                ->sortable()
                ->searchable(),
            TextColumn::make('email')
                ->label('بريد إلكتروني')
                ->sortable()
                ->searchable(),
            TextColumn::make('addresses.phone_number')
                ->label('رقم الهاتف')
                ->listWithLineBreaks()
                ->limitList(1)
                ->searchable()
                ->copyable()
                ->visible(fn ($livewire): bool => ($livewire->activeTab ?? 'customers') !== 'admins'),
            TextColumn::make('email_verified_at')
                ->label('تاريخ التحقق من البريد')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')
                ->label('تاريخ الإنشاء')
                ->dateTime()
                ->sortable()
                ->toggleable(),
            TextColumn::make('updated_at')
                ->label('تاريخ التحديث')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        /** @var PluginContributions $contributions */
        $contributions = app(PluginContributions::class);
        foreach ($contributions->active('admin.users.table.columns') as $contributor) {
            if ($contributor instanceof UsersTableColumnContributor) {
                foreach ($contributor->columns() as $column) {
                    $columns[] = $column;
                }
            }
        }

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns($columns)
            ->filters([
                Filter::make('has_orders')
                    ->label('لديه طلبات')
                    ->query(fn (Builder $query): Builder => $query->has('orders')),
                Filter::make('email_verified')
                    ->label('بريد تم التحقق منه')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at')),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('من تاريخ'),
                        DatePicker::make('created_until')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->emptyStateHeading('لا يوجد مستخدمون')
            ->emptyStateDescription('لم يتم تسجيل أي مستخدمين بعد — أضف أول مستخدم')
            ->emptyStateActions([
                Action::make('create')
                    ->label('أضف أول مستخدم')
                    ->url(fn (): string => UserResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
