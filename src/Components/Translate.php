<?php

namespace Daxit\OptimaClass\Components;

use Illuminate\Support\Facades\File;

class Translate
{ 
    public static function t($str)
    {  
        $str = strtolower($str);

        $translate = __('app.' . $str) === 'app.' . $str ? $str : __('app.' . $str);

        return nl2br($translate);
    }

    public static function createDefaultLocale(array $locales = ["en", "es"])
    {
        $langBasePath = resource_path('lang');

        // Ensure the base lang directory exists
        Translate::createDirectory($langBasePath);

        // Create locale directories and files
        foreach ($locales as $locale) {
            $localePath = sprintf('%s/%s', $langBasePath, $locale);
            Translate::createDirectory($localePath);

            $filePath = sprintf('%s/app.php', $localePath);
            Translate::createFile($filePath);
        }
    }

    /**
     * Create a directory if it doesn't exist.
     *
     * @param string $path
     * @return void
    */
    public static function createDirectory(string $path)
    {
        if (!File::exists($path)) {
            if (!File::makeDirectory($path, 0755, true)) {
                return "Failed to create directory: $path";
            }
        }
    }

    /**
     * Create a file with default content if it doesn't exist.
     *
     * @param string $filePath
     * @return void
    */
    public static function createFile(string $filePath)
    {
        if (!File::exists($filePath)) {
            if (file_put_contents($filePath, self::defaultFileContent()) === false) {
                return "Failed to create file: $filePath";
            }
        }
    }

    /**
     * Get the default content for the locale files.
     *
     * @return string
    */
    public static function defaultFileContent(): string
    {
        return <<<EOT
            <?php

            use Daxit\OptimaClass\Helpers\Cms;

            \$translations = Cms::getTranslations();
            \$returnData = [];

            foreach (\$translations as \$translation) {
                \$returnData[strtolower(\$translation['key'])] = \$translation['value'];
            }

            return \$returnData;
        EOT;
    }
}