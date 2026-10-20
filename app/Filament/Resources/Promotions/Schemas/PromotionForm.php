<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Models\Promotion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * A promotion page. Times are entered in Kenya time. The body is Markdown; HTML typed into it is stripped on the
 * site, and pictures go in the banner image rather than the body.
 */
class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Promotion $record): void {
                        // Only new promotions follow the title: a published link must keep working.
                        if ($record === null && blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('Link'))
                    ->prefix('/promotions/')
                    ->required()
                    ->maxLength(120)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText(__('Changing it breaks links already shared.')),
                Textarea::make('summary')
                    ->required()
                    ->maxLength(160)
                    ->rows(2)
                    ->helperText(__('Shown on the promotions list. Up to 160 characters.'))
                    ->columnSpanFull(),
                MarkdownEditor::make('body')
                    ->required()
                    ->fileAttachments(false)
                    ->helperText(__('Explain the offer and its terms in full: who can take part, minimum odds, dates and any limits.'))
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label(__('Banner image'))
                    ->image()
                    ->disk('public')
                    ->directory('promotions')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->getUploadedFileUsing(fn (FileUpload $component, string $file, string|array|null $storedFileNames): ?array => self::sameOriginPreview($component->getUploadedFile($file, $storedFileNames)))
                    ->helperText(__('Optional. JPEG, PNG or WebP up to 2 MB, about twice as wide as it is tall.'))
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->label(__('Starts (EAT)'))
                    ->timezone(config('sportsbook.timezone'))
                    ->seconds(false)
                    ->required()
                    ->default(now()),
                DateTimePicker::make('ends_at')
                    ->label(__('Ends (EAT)'))
                    ->timezone(config('sportsbook.timezone'))
                    ->seconds(false)
                    ->after('starts_at')
                    ->helperText(__('Leave empty to run until unpublished.')),
                Toggle::make('is_published')
                    ->label(__('Published'))
                    ->helperText(__('Players see it once published and started.')),
                TextInput::make('sort_order')
                    ->label(__('Order'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(0)
                    ->required()
                    ->helperText(__('Lower comes first.')),
            ]);
    }

    /**
     * The upload field fetches the saved image to preview it. The public disk's URLs are on the site's domain, which
     * the browser will not fetch from the admin subdomain (CORS); both serve the same /storage, so a root-relative
     * URL loads it from the admin's own origin. URLs elsewhere (a CDN) are left alone.
     *
     * @param  array{name: string, size: int, type: ?string, url: ?string}|null  $uploadedFile
     * @return array{name: string, size: int, type: ?string, url: ?string}|null
     */
    private static function sameOriginPreview(?array $uploadedFile): ?array
    {
        $siteUrl = rtrim((string) config('app.url'), '/');

        if ($uploadedFile !== null && is_string($uploadedFile['url']) && str_starts_with($uploadedFile['url'], $siteUrl.'/')) {
            $uploadedFile['url'] = substr($uploadedFile['url'], strlen($siteUrl));
        }

        return $uploadedFile;
    }
}
