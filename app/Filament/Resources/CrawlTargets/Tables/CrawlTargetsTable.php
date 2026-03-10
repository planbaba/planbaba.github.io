<?php

namespace App\Filament\Resources\CrawlTargets\Tables;

use App\Jobs\ProcessWebsiteCrawl;
use App\Models\CrawlRun;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class CrawlTargetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('url')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('max_depth'),
                Tables\Columns\TextColumn::make('max_urls'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('last_crawled_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('startCrawl')
                    ->label('Start Crawl')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $run = CrawlRun::query()->create([
                            'crawl_target_id' => $record->id,
                            'status' => 'queued',
                        ]);

                        ProcessWebsiteCrawl::dispatch($record->id, $run->id);

                        Notification::make()
                            ->title('Crawl queued')
                            ->body("{$record->name} has been queued for crawling.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
