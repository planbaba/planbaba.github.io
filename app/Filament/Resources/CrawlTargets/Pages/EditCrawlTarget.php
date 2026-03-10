<?php

namespace App\Filament\Resources\CrawlTargets\Pages;

use App\Filament\Resources\CrawlTargets\CrawlTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCrawlTarget extends EditRecord
{
    protected static string $resource = CrawlTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
