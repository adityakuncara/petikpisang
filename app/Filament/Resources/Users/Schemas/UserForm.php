<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required(),

                TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->required()
                    ->disabled(fn ($record) => $record !== null)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('Kata Sandi')
                    ->password()
                    ->revealable()
                    ->required(fn ($context) => $context === 'create')
                    ->dehydrated(fn ($state) => filled($state)) // hanya kirim kalau ada isi
                    ->afterStateHydrated(fn ($state, $set) => $set('password', ''))
                    ->rule('min:8'),

                TextInput::make('password_confirmation')
                    ->label('Konfirmasi Kata Sandi')
                    ->password()
                    ->revealable() 
                    ->required(fn ($context) => $context === 'create')
                    ->same('password')
                    ->dehydrated(false),

                Select::make('roles')
                    ->label('Peran (Roles)')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}