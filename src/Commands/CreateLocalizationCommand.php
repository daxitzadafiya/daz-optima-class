<?php

namespace Daz\OptimaClass\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

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
            default: 'en',
            required: true
        );

        // Parse and sanitize input
        $locales = array_filter(array_map('trim', explode(',', $input)));
        $langBasePath = resource_path('lang');

        // Ensure the base lang directory exists
        $this->createDirectory($langBasePath);

        // Create locale directories and files
        foreach ($locales as $locale) {
            $localePath = sprintf('%s/%s', $langBasePath, $locale);
            $this->createDirectory($localePath);

            $filePath = sprintf('%s/app.php', $localePath);
            $this->createFile($filePath);
        }

        info('Locale files created successfully.');
    }

    /**
     * Create a directory if it doesn't exist.
     *
     * @param string $path
     * @return void
     */
    private function createDirectory(string $path)
    {
        if (!File::exists($path)) {
            if (!File::makeDirectory($path, 0755, true)) {
                error("Failed to create directory: $path");
            }
        }
    }

    /**
     * Create a file with default content if it doesn't exist.
     *
     * @param string $filePath
     * @return void
     */
    private function createFile(string $filePath)
    {
        if (!File::exists($filePath)) {
            if (file_put_contents($filePath, $this->defaultFileContent()) === false) {
                error("Failed to create file: $filePath");
            }
        }
    }

    /**
     * Get the default content for the locale files.
     *
     * @return string
     */
    private function defaultFileContent(): string
    {
        return <<<EOT
            <?php

            use Daz\OptimaClass\Helpers\Cms;

            \$translations = Cms::getTranslations();
            \$returnData = [];

            foreach (\$translations as \$translation) {
                \$returnData[strtolower(\$translation['key'])] = \$translation['value'];
            }

            return \$returnData;
        EOT;
    }
}
