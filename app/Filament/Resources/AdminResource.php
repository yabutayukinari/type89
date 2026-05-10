<?php declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\AdminRole;
use App\Filament\Resources\AdminResource\Pages\CreateAdmin;
use App\Filament\Resources\AdminResource\Pages\EditAdmin;
use App\Filament\Resources\AdminResource\Pages\ListAdmins;
use App\Filament\Resources\AdminResource\Pages\ViewAdmin;
use App\Models\Admin;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Resource<Admin>
 */
class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = '管理者';

    protected static ?string $pluralModelLabel = '管理者';

    public static function canAccess(): bool
    {
        return Admin::isSystemAdminLoggedIn();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('名前')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Select::make('role')
                            ->label('ロール')
                            ->options([
                                AdminRole::SystemAdmin->value => AdminRole::SystemAdmin->label(),
                                AdminRole::GeneralAdmin->value => AdminRole::GeneralAdmin->label(),
                            ])
                            ->required()
                            ->default(AdminRole::GeneralAdmin->value),
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
                TextColumn::make('role')
                    ->label('ロール')
                    ->badge()
                    ->formatStateUsing(static fn (AdminRole $state): string => $state->label())
                    ->sortable(),
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'view' => ViewAdmin::route('/{record}'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
