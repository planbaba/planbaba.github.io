<?php

namespace App\Filament\Resources\CrawlTargets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('pages_crawled'),
                Tables\Columns\TextColumn::make('links_found'),
                Tables\Columns\TextColumn::make('failed_urls'),
                Tables\Columns\TextColumn::make('started_at')->dateTime(),
                Tables\Columns\TextColumn::make('finished_at')->dateTime(),
                Tables\Columns\TextColumn::make('error_message')->limit(50),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
