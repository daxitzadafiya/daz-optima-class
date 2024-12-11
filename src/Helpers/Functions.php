<?php

namespace Daz\OptimaClass\Helpers;

use Daz\OptimaClass\Components\Translate;
use Daz\OptimaClass\Requests\ContactUsRequest;
use Daz\OptimaClass\Service\ParamsContainer;
use Daz\OptimaClass\Traits\ConfigTrait;
use Daz\ReCaptcha\Facades\ReCaptcha;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\View\ViewException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class Functions
{
    use ConfigTrait;

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
        return File::deleteDirectory($dirname);
    }

    public static function renderReCaptchaJs($callback = false, $onLoadClass = 'onloadCallBack')
    {
        self::initialize();
        $currentAppLanguage = strtolower(App::getLocale());
        $cmsLang = self::$replace_iso_code;

        // Simplify the language exception handling
        if (array_key_exists($currentAppLanguage, $cmsLang)) {
            $currentAppLanguage = $cmsLang[$currentAppLanguage] ?? '';
        }

        return ReCaptcha::renderJs($currentAppLanguage, $callback, $onLoadClass);
    }

    public static function prepareCustomFields($id, $name)
    {
        echo '<input type="hidden" id="'. $id .'" name="'. $name .'">';
    }

    public static function recaptcha($name, $id = '', $options = [])
    {
        $siteKey = config('services.recaptcha.site_key', env('RECAPTCHA_SITE_KEY', '6Le9fqsUAAAAAN2KL4FQEogpmHZ_GpdJ9TGmYMrT'));

        $defaultOptions = [
            "class" => "g-recaptcha",
            "data-sitekey" => $siteKey,
            "data-input-id" => $id,
            "id" => $id ."-". ($name ?? 'reCaptcha'),
            "data-form-id" => "",
        ];

        self::prepareCustomFields($id, $name ?? 'reCaptcha');

        $mergedOptions = self::mergeOptions($defaultOptions, $options);

        return '<div ' . implode(' ', array_map(fn($key, $value) => $key . '="' . e($value) . '"', array_keys($mergedOptions), $mergedOptions)) . ' ></div>';
    }

    public static function reCaptcha3($name = 'recaptcha_token', $id = 'recaptchaToken', $options = [])
    {
        $siteKey = config('services.recaptcha.site_key', env('RECAPTCHA_SITE_KEY', '6Le9fqsUAAAAAN2KL4FQEogpmHZ_GpdJ9TGmYMrT'));

        return '<input type="hidden" name="' . $name . '" id="' . $id . '" value="">';
    }

    public static function siteSendEmail($object, $redirect_url = null)
    {
        self::initialize();
        $model = new ContactUs();
        $model->fill(request()->all());
        $model->verifyCode = true;
        $model->reCaptcha = request()->input('reCaptcha');

        if ($model->reCaptcha3 = request()->input('reCaptcha3')) {
            $model->scenario = ContactUsRequest::SCENARIO_V3;
        }

        if (isset($_GET['owner'])) {
            $model->owner = 1;
        }

        if (isset($_GET['friend_name']) && isset($_GET['friend_ser_name']) && isset($_GET['friend_email'])) {

            $message = '';
            $message .= 'Message: ' . $model->message;

            $model->message = "Friend's Name = " . $_GET['friend_name'] . "\r\n Friend's Ser Name = " . $_GET['friend_ser_name'] . "\r\n Friend's Email = " . $_GET['friend_email'] . "\r\n" . $message;
        }

        if (isset($_GET['morning_call']) || isset($_GET['afternoon_call'])) {

            if (isset($_GET['morning_call']) && !isset($_GET['afternoon_call'])) {
                $scedual_msg = 'Call me back in the morning';
            } elseif (isset($_GET['afternoon_call']) && !isset(($_GET['morning_call']))) {
                $scedual_msg = 'Call me back in the afternoon';
            } else {
                $scedual_msg = 'Call me back in the morning.<br>Call me back in the afternoon.';
            }
            $message = '';

            $message .= 'Message: ' . $model->message;

            $model->message = "Preferred time = " . $scedual_msg . "\r\n" . $message;
        }

        try {
            if (!$model->sendMail()) {
                $errors = 'Message not sent!';
                if (isset($model->errors) && count($model->errors) > 0) {
                    $errors = implode(',', array_map(function ($error) {
                        return $error[0];
                    }, $model->errors));
                }

                session()->put(['success' => false, 'message' => $errors]);

                if (self::$send_error_mails_to) {
                    self::sendErrorMail($errors, self::$send_error_mails_to);
                }

            } else {
                session()->put(['success' => true, 'message' => Translate::t('thank you for your message!')]);

                if ($redirect_url) {
                    return redirect($redirect_url);
                }
            }

        } catch (\Exception $e) {
            // Handle unexpected errors
            session()->put(['success' => false, 'message' => "An error occurred: " . $e->getMessage()]);

            if (self::$send_error_mails_to) {
                self::sendErrorMail($e->getMessage(), self::$send_error_mails_to);
            }
        }

        return redirect()->back();
    }

    public static function sendErrorMail($model, $to = ['support@optimasys.es'], $bcc = [])
    {
        $errors = 'Message not sent!';

        if (isset($model->errors) && count($model->errors) > 0) {
            $errors = implode(',', array_map(function ($error) {
                return $error[0];
            }, $model->errors));
        }

        $message = "";
        $message .= 'Name : ' . $model->first_name . ' ' . $model->last_name . '<br>';
        $message .= 'Email : ' . $model->email . '<br>';
        $message .= 'Phone : ' . $model->phone . '<br>';
        $message .= 'Message : ' . $model->message . '<br><br>';
        $message .= 'Site : ' . url()->current() . '<br>';
        $message .= 'Url : ' . url()->previous() . '<br>';
        $message .= 'Errors : ' . $errors . '<br>';

        Mail::send([], [], function ($mail) use ($to, $bcc, $message) {
            $mail->to($to)
                ->bcc($bcc)
                ->subject('Leads Error');
            $mail->html($message);
        });
    }

    public static function loadPageDynamically($object)
    {
        $slug = request()->input('slug', '');
        if ($slug) {
            $page_data = Cms::getPage(['slug' => $slug, 'lang' => App::getLocale()]);
            App::instance('params', new ParamsContainer(['page_data' => $page_data]));
        }

        // redirect if there is no page_data is available
        if (!isset($page_data) || empty(array_filter($page_data))) {
            return redirect(to: '/404');
        }

        if (!empty($page_data['view_path'])) {
            try {
                return $object->render($page_data['view_path'], [
                    'page_data' => $page_data
                ]);
            } catch (ViewException $error) {
                throw $error;
            }
        } elseif ($slug == '404') {
            return $object->render($slug, [
                'page_data' => $page_data
            ]);
        } else {
            return $object->render('page', [
                'page_data' => $page_data
            ]);
        }
    }

    public static function dynamicPage($object)
    {
        $cmsModel = Cms::Slugs('page');
        $url = explode('/', request()->path());
        $this_page = urldecode(end($url));
        $page_data = Cms::pageBySlug(request()->input('title'));
        App::instance('params', new ParamsContainer(['page_data' => $page_data]));
        if (isset($cmsModel) && count($cmsModel) > 0) {
            foreach ($cmsModel as $row) {
                if (isset($row['slug_all'][strtoupper(App::getLocale())]) and $row['slug_all'][strtoupper(App::getLocale())] == $this_page) {
                    $page_data = Cms::pageBySlug($this_page);
                    if (isset($page_data['custom_settings'][strtoupper(App::getLocale())]) and count($page_data['custom_settings'][strtoupper(App::getLocale())]) > 0) {
                        foreach ($page_data['custom_settings'][strtoupper(App::getLocale())] as $custom_keys) {
                            if ($custom_keys['key'] == 'page_template') {
                                $page_template = $custom_keys['value'];
                            }
                            if ($custom_keys['key'] == 'custom_post_id') {
                                $custom_post_id = $custom_keys['value'];
                            }
                        }
                    }
                }
            }
        }
        if (isset($page_template)) {
            try {
                if (isset($custom_post_id)) {
                    $custom_post_id = Cms::postTypes($custom_post_id);
                } else {
                    $custom_post_id = '';
                }
                App::instance('params', new ParamsContainer(['page_data' => $page_data]));
                return $object->render($page_template, [
                    'page_data' => $page_data,
                    'custom_post_id' => $custom_post_id
                ]);
            } catch (ViewException $e) {
                //die;
            }
        } elseif (isset($this_page) && is_file($this_page)) {
            return $object->render($this_page, [
                'page_data' => isset($page_data) ? $page_data : ''
            ]);
        } else {
            if (!array_filter($page_data)) {
                $page_data_404 = Cms::pageBySlug('404');
                if (!isset($page_data_404) || !isset($page_data_404['slug_all']['EN'])) {
                    die('Please create 404 page with slug "404" in CMS');
                }
                $page_data = Cms::pageBySlug('404');
                App::instance('params', new ParamsContainer(['page_data' => $page_data]));
                return $object->render('404', [
                    'page_data' => isset($page_data) ? $page_data : ''
                ]);
            }
            App::instance('params', new ParamsContainer(['page_data' => $page_data]));
            return $object->render('page', [
                'page_data' => isset($page_data) ? $page_data : ''
            ]);
        }
    }

    public static function getAgentsList($agent_name = '')
    {
        self::initialize();
        $url = self::$apiUrl . 'properties/get-assigned-to-listing-agent' . http_build_query([
            'user_apikey' => self::$api_key,
            'search_word' => $agent_name,
        ]);
        return Http::get($url)->json();
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
