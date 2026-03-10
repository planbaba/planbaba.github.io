<?php

namespace App\Filament\Resources\CrawlTargets\Schemas;

use Filament\Forms;
use Filament\Forms\Form;

class CrawlTargetForm
{
    public static function configure(Form $form): Form
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
}
