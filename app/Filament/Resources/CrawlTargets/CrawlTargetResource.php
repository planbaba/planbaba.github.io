<?php

namespace App\Filament\Resources\CrawlTargets;

use App\Filament\Resources\CrawlTargets\Pages\CreateCrawlTarget;
use App\Filament\Resources\CrawlTargets\Pages\EditCrawlTarget;
use App\Filament\Resources\CrawlTargets\Pages\ListCrawlTargets;
use App\Filament\Resources\CrawlTargets\RelationManagers\RunsRelationManager;
use App\Jobs\ProcessWebsiteCrawl;
use App\Models\CrawlRun;
use App\Models\CrawlTarget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CrawlTargetResource extends Resource
{
    protected static ?string $model = CrawlTarget::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Crawlers';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('url')->required()->url()->maxLength(2048),
            Forms\Components\TextInput::make('max_depth')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_urls')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('concurrency')->numeric()->minValue(1)->maxValue(50)->required(),
            Forms\Components\Toggle::make('respect_robots_txt')->default(true),
            Forms\Components\TextInput::make('user_agent')->maxLength(255),
            Forms\Components\TagsInput::make('allowed_domains')->separator(','),
            Forms\Components\TagsInput::make('ignored_urls')->separator(','),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
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
                    ->action(function (CrawlTarget $record): void {
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

    public static function getRelations(): array
    {
        return [
            RunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrawlTargets::route('/'),
            'create' => CreateCrawlTarget::route('/create'),
            'edit' => EditCrawlTarget::route('/{record}/edit'),
        ];
    }
}
