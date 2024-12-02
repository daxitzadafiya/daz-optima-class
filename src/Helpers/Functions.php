<?php

namespace Daz\OptimaClass\Helpers;

use Daz\ReCaptcha\Facades\ReCaptcha;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class Functions
{
    public static function directory()
    {
        $webroot = public_path() . '/uploads/';

        // Ensure the "uploads" directory exists
        if (!File::exists($webroot)) {
            File::makeDirectory($webroot, 0755, true); // Creates the directory with proper permissions
        }

        // Ensure the "uploads/temp" directory exists
        $tempDirectory = $webroot . 'temp/';
        if (!File::exists($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true); // Creates the directory with proper permissions
        }

        return $tempDirectory;
    }

    public static function deleteDirectory($dirname)
    {
        if (!File::exists($dirname)) {
            return false; // If directory does not exist, return false
        }

        // Get all files and directories within the given directory
        $files = File::allFiles($dirname);

        foreach ($files as $file) {
            // Delete files
            File::delete($file);
        }

        // Now delete all subdirectories (recursively)
        $directories = File::directories($dirname);
        foreach ($directories as $directory) {
            self::deleteDirectory($directory); // Recursive call to delete subdirectory
        }

        // Finally, delete the root directory
        return File::rmdir($dirname);
    }

    public static function getCRMData($url, $cache = true, $fields = array(), $auth = false)
    {
        return self::getCurlData($url, $cache);
    }

    public static function getCurlData($url, $cache = true, $fields = array(), $auth = false)
    {
        $url = str_replace(" ", "%20", $url);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        if ($auth) {
            curl_setopt($curl, CURLOPT_USERPWD, "$auth");
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($fields) {
            $fields_string = http_build_query($fields);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        }

        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header_string = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $header_rows = explode(PHP_EOL, $header_string);
        //$header_rows = array_filter($header_rows, trim);
        $i = 0;
        foreach ((array) $header_rows as $hr) {
            $colonpos = strpos($hr, ':');
            $key = $colonpos !== false ? substr($hr, 0, $colonpos) : (int) $i++;
            $headers[$key] = $colonpos !== false ? trim(substr($hr, $colonpos + 1)) : $hr;
        }
        $j = 0;
        foreach ((array) $headers as $key => $val) {
            $vals = explode(';', $val);
            if (count($vals) >= 2) {
                unset($headers[$key]);
                foreach ($vals as $vk => $vv) {
                    $equalpos = strpos($vv, '=');
                    $vkey = $equalpos !== false ? trim(substr($vv, 0, $equalpos)) : (int) $j++;
                    $headers[$key][$vkey] = $equalpos !== false ? trim(substr($vv, $equalpos + 1)) : $vv;
                }
            }
        }
        //print_rr($headers);
        curl_close($curl);
        // echo $body;
        // die;
        return $body;
    }

    public static function array_map_assoc(callable $f, array $a)
    {
        return array_column(array_map($f, array_keys($a), $a), 1, 0);
    }

    public static function clean($string)
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

    public static function renderReCaptchaJs($callback = false, $onLoadClass = 'onloadCallBack')
    {
        $currentAppLanguage = strtolower(App::getLocale());
        $cmsLang = config("params.replace_iso_code", []);

        // Simplify the language exception handling
        if (array_key_exists($currentAppLanguage, $cmsLang)) {
            $currentAppLanguage = $cmsLang[$currentAppLanguage] ?? '';
        }

        return ReCaptcha::renderJs($currentAppLanguage, $callback, $onLoadClass);
    }
    
    public static function recaptcha($name = 'reCaptcha', $id = '', $options = [])
    {
        $siteKey = config('services.recaptcha.site_key', env('RECAPTCHA_SITE_KEY', '6Le9fqsUAAAAAN2KL4FQEogpmHZ_GpdJ9TGmYMrT'));

        $defaultOptions = [
            "class" => "g-recaptcha",
            "name" => $name,
            "data-sitekey" => $siteKey,
            "data-id" => $id
        ];

        $mergedOptions = self::mergeOptions($defaultOptions, $options);

        return '<div ' . implode(' ', array_map(fn($key, $value) => $key . '="' . e($value) . '"', array_keys($mergedOptions), $mergedOptions)) . ' ></div>';
    }

    private static function mergeOptions(array $defaultOptions, array $options): array
    {
        foreach ($options as $key => $value) {
            if (isset($defaultOptions[$key]) && is_string($defaultOptions[$key])) {
                // If the key exists and is a string, append the new value with a space
                $defaultOptions[$key] .= ' ' . $value;
            } else {
                // Otherwise, just set the new value
                $defaultOptions[$key] = $value;
            }
        }

        return $defaultOptions;
    }
}
