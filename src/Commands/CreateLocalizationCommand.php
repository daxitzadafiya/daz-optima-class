<?php

namespace Daxit\OptimaClass\Commands;

use Daxit\OptimaClass\Components\Translate;
use Illuminate\Console\Command;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;

class CreateLocalizationCommand extends Command
{
    protected $signature = 'optima:create-locale-files';
    protected $description = 'Create Locale Files (e.g., en, es, fr)';

    public function handle()
    {
        // Prompt the user for input
        $input = text(
            label: 'Which locale files do you want to create?',
            placeholder: 'E.g., en,es,fr',
            default: 'en, es',
            required: true
        );

        // Parse and sanitize input
        $locales = array_filter(array_map('trim', explode(',', $input)));

        Translate::createDefaultLocale($locales);

        info('Locale files created successfully.');
    }
}
