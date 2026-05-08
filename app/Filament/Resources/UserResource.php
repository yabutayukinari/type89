<?php declare(strict_types=1);

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use BackedEnum;

/**
 * @extends Resource<User>
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'ユーザー';

    protected static ?string $pluralModelLabel = 'ユーザー';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('名前')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        TextInput::make('password')
                            ->label('パスワード')
                            ->password()
                            ->dehydrateStateUsing(static fn (string $state): string => Hash::make($state))
                            ->dehydrated(static fn (?string $state): bool => filled($state))
                            ->required(static fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        DateTimePicker::make('email_verified_at')
                            ->label('メール認証日時'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('名前')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->label('メール認証')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('最終ログイン')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('削除済み'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
