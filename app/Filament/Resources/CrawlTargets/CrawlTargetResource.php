<?php

namespace App\Filament\Resources\CrawlTargets;

use App\Filament\Resources\CrawlTargets\Pages\CreateCrawlTarget;
use App\Filament\Resources\CrawlTargets\Pages\EditCrawlTarget;
use App\Filament\Resources\CrawlTargets\Pages\ListCrawlTargets;
use App\Filament\Resources\CrawlTargets\RelationManagers\RunsRelationManager;
use App\Filament\Resources\CrawlTargets\Schemas\CrawlTargetForm;
use App\Filament\Resources\CrawlTargets\Tables\CrawlTargetsTable;
use App\Models\CrawlTarget;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CrawlTargetResource extends Resource
{
    protected static ?string $model = CrawlTarget::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Crawlers';

    public static function form(Form $form): Form
    {
        return CrawlTargetForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return CrawlTargetsTable::configure($table);
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
