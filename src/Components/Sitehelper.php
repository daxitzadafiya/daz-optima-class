<?php

namespace Daz\OptimaClass\Components;

use Daz\OptimaClass\Helpers\Cms;
use Daz\OptimaClass\Helpers\Dropdowns;
use Daz\OptimaClass\Helpers\Functions;
use Daz\OptimaClass\Traits\ConfigTrait;
use DOMDocument;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class Sitehelper
{
    use ConfigTrait;

    // for no page size pass $pageSize = false
    public static function get_posts_by_type($post_type = 'post', $category = null, $forRoutes = null, $pageSize = 10, $imageseo = false, $options = [])
    {
        return Cms::postTypes($post_type, $category, $forRoutes, $pageSize, $imageseo, $options);
    }

    public static function blog()
    {
        return self::get_posts_by_type();
    }

    public static function blogWithPagination($page, $category, $page_size)
    {
        return self::get_posts_by_type('post', $category, null, $page_size, false, ['cache' => false, 'page' => $page]);
    }

    public static function get_theme_url()
    {
        return URL::home(true) . 'themes/optima_theme';
    }

    /**
     * get site logo link from CMS
     * 
     * @param mixed $object = $this
     * 
     * @return url
     */
    public static function get_site_logo($object)
    {
        self::initialize();
        $settings = self::get_settings($object);

        return isset($settings['header']['logo']['name']) ? ('https://images.optima-crm.com/cms_settings/' . self::$template . '/' . $settings['header']['logo']['name']) : '';
    }

    /**
     * set favicon from CMS for site
     * 
     * @param mixed $object = $this
     * 
     * @return mixed
     */
    public static function set_favicon($object)
    {
        self::initialize();
        $settings = self::get_settings($object);

        isset($settings['header']['favicon']['name']) ? $object->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'href' => 'https://images.optima-crm.com/cms_settings/' . self::$template . '/' . $settings['header']['favicon']['name']]) : '';
    }

    /**
     * get CMS menu
     * 
     * @param string $menuID = 'MainMenu'
     * 
     * @return array
     */
    public static function get_menu($menuID = 'MainMenu')
    {
        return Cms::menu($menuID, $getUrlsFromPage = false, $getOtherSettings = true);
    }

    /**
     * get all CMS languages
     * 
     * @return mixed
     */
    public static function get_languages()
    {
        return Cms::languages();
    }

    /**
     * get CMS Site Settings
     * 
     * @param mixed $object = $this
     * 
     * @return mixed
     */
    public static function get_settings($object)
    {
        if (isset($object->params['settings'])) {
            return $object->params['settings'];
        } elseif (!empty($object)) {
            return $object->params['settings'] = Cms::settings();
        }

        return Cms::settings();
    }

    /**
     * get CMS Custom Settings
     * 
     * @param mixed $object = $this
     * 
     * @return mixed
     */
    public static function get_custom_settings($object)
    {
        if (isset($object->params['custom_settings'])) {
            return $object->params['custom_settings'];
        } elseif (!empty($object)) {
            return $object->params['custom_settings'] = Cms::custom_settings();
        }

        return Cms::custom_settings();
    }

    /**
     * get page data from $this, registered in controller
     * 
     * @param mixed $object = $this
     * 
     * @return mixed|array
     */
    public static function get_page_data($object)
    {
        if (isset($object->params['page_data'])) {
            return $object->params['page_data'];
        }

        return [];
    }

    /**
     * get page data Custom Settings from page_data
     * 
     * @param mixed $object = $this
     * 
     * @return false|array|mixed
     */
    public static function get_page_custom_settings($object)
    {
        if (isset($object->params['page_custom_settings'])) {
            return $object->params['page_custom_settings'];
        } elseif (isset($object->params['page_data'])) {
            $page_data = self::get_page_data($object);
            return $object->params['page_custom_settings'] = Cms::custom_settings($page_data['custom_settings']);
        }

        return [];
    }

    /**
     * get post from $this, registered in controller
     * 
     * @param mixed $object = $this
     * 
     * @return mixed|array
     */
    public static function get_post($object)
    {
        if (isset($object->params['post'])) {
            return $object->params['post'];
        }

        return [];
    }

    /**
     * get page data Custom Settings from page_data
     * 
     * @param mixed $object = $this
     * 
     * @return false|array|mixed
     */
    public static function get_post_custom_settings($object)
    {
        if (isset($object->params['post_custom_settings'])) {
            return $object->params['post_custom_settings'];
        } elseif (isset($object->params['post'])) {
            $post = self::get_post($object);
            return $object->params['post_custom_settings'] = Cms::custom_settings($post['custom_settings']);
        }

        return [];
    }

    /**
     * set Canonical Link for page for SEO
     * 
     * @param mixed $object = $this
     * @param string $link = ''
     * 
     * @return void
     */
    public static function registerCanonicalTag($object, $link = '')
    {
        if (!empty($link)) {
            $object->registerLinkTag(['rel' => 'canonical', 'href' => $link]);
        } else {
            $path = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            if (isset($path)) {
                $object->registerLinkTag(['rel' => 'canonical', 'href' => "https://" . $path]);
            }
        }
    }

    /**
     * get resized image link
     * @param mixed $url = 'https://images.optima-crm.com/resize/cms_medias/155/1200/Summer.jpg'
     * @param int $size = 1200
     * 
     * @return string|string[]|mixed
     */
    public static function cmsImgResize($url, $size = 1200, $type = 'cms_medias')
    {
        return Cms::ResizeImage($url, $size, $type);
    }

    /**
     * get resized property attachment link
     * @param mixed $url = 'https://images.optima-crm.com/resize/cms_medias/155/1200/Summer.jpg'
     * @param int $size = 1200
     * 
     * @return string|string[]|mixed
     */
    public static function propImgResize($url, $size = 1200, $type = 'property')
    {
        return Cms::ResizeImage($url, $size, $type);
    }

    /**
     * get Window_Card pdf link for property
     * @param mixed $property_id = "5eab0625798b93762d1dfb82"
     * @param string $WC_template = "6"
     * 
     * @return url
     */
    public static function Window_Card($property_id, $WC_template = "379")
    {
        self::initialize();
        $query = '&template_id=' . $WC_template;
        $query .= isset(self::$user) ? '&user=' . self::$user : '';
        $query .= App::getLocale() == 'cat' ? '&lang=CA' : '&lang=' . strtoupper(App::getLocale());
        $query .= isset($property_id) ? '&modelId=' . $property_id : '';
        $query .= '&model_name=commercial_properties';

        return self::$apiUrl . 'pdf' . $query;
    }

    // Check for device id mobile or not
    public static function check_mbl()
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'];

        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) {
            return true;
        }

        return false;
    }

    /**
     * register meta tags for page according to type
     * 
     * @param mixed $object = $this
     * 
     * @return void
     */
    public static function register_meta_tags($object)
    {
        $custom_settings = Sitehelper::get_custom_settings($object);
        $page_custom_settings = Sitehelper::get_page_custom_settings($object);
        if ($property = isset($object->params['property']) ? $object->params['property'] : []) {
            // $object->title = isset($property['meta_title']) ? $property['meta_title'] : Yii::$app->translate->t('Real Estate Agency');
            $meta_title = (isset($property['meta_title']) && !empty($property['meta_title'])) ? $property['meta_title'] : ((isset($property['rental_meta_title']) && !empty($property['rental_meta_title'])) ? $property['rental_meta_title'] : Translate::t('Real Estate Agency'));
            $meta_desc = (isset($property['meta_desc']) && !empty($property['meta_desc'])) ? $property['meta_desc'] : ((isset($property['rental_meta_desc']) && !empty($property['rental_meta_desc'])) ? $property['rental_meta_desc'] : '');
            $object->title = $meta_title;

            $object->registerMetaTag([
                'name' => 'description',
                // 'content' => isset($property['meta_desc']) ? $property['meta_desc'] : '',
                'content' => $meta_desc,
            ]);

            $object->registerMetaTag([
                'name' => 'keywords',
                'content' => isset($property['meta_keywords']) ? $property['meta_keywords'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'og:url',
                'content' => isset($page_custom_settings['canonical_link']) ? $page_custom_settings['canonical_link'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            ]);

            $object->registerMetaTag([
                'property' => 'og:image',
                'content' => isset($property['attachments'][0]) ? $property['attachments'][0] : Sitehelper::get_site_logo($object),
            ]);

            $object->registerMetaTag([
                'property' => 'og:type',
                'content' => 'property',
            ]);

            $object->registerMetaTag([
                'property' => 'og:title',
                // 'content' => isset($property['meta_title']) ? $property['meta_title'] : '',
                'content' => $meta_title,
            ]);

            $object->registerMetaTag([
                'property' => 'og:description',
                // 'content' => isset($property['meta_desc']) ? $property['meta_desc'] : '',
                'content' => $meta_desc,
            ]);

            $object->registerMetaTag([
                'property' => 'fb:app_id',
                'content' => isset($custom_settings['fb_app_id']) ? $custom_settings['fb_app_id'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'theme-color',
                'content' => isset($property['meta_theme_color']) ? $property['meta_theme_color'] : '#1e1e54',
            ]);
        } elseif ($development = isset($object->params['development']) ? $object->params['development'] : []) {
            $object->title = isset($development['meta_title']) ? $development['meta_title'] : Translate::t('Real Estate Agency');

            $object->registerMetaTag([
                'name' => 'description',
                'content' => isset($development['meta_desc']) ? $development['meta_desc'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'keywords',
                'content' => isset($development['meta_keywords']) ? $development['meta_keywords'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'og:url',
                'content' => isset($page_custom_settings['canonical_link']) ? $page_custom_settings['canonical_link'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            ]);

            $object->registerMetaTag([
                'property' => 'og:image',
                'content' => isset($development['attachments'][0]) ? $development['attachments'][0] : Sitehelper::get_site_logo($object),
            ]);

            $object->registerMetaTag([
                'property' => 'og:type',
                'content' => 'property in development',
            ]);

            $object->registerMetaTag([
                'property' => 'og:title',
                'content' => isset($development['meta_title']) ? $development['meta_title'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'og:description',
                'content' => isset($development['meta_desc']) ? $development['meta_desc'] : '',
            ]);
            $object->registerMetaTag([
                'property' => 'fb:app_id',
                'content' => isset($custom_settings['fb_app_id']) ? $custom_settings['fb_app_id'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'keywords',
                'content' => isset($development['meta_keywords']) ? $development['meta_keywords'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'theme-color',
                'content' => isset($property['meta_theme_color']) ? $property['meta_theme_color'] : '#1e1e54',
            ]);
        } elseif ($post = isset($object->params['post']) ? $object->params['post'] : []) {
            $object->title = isset($post['meta_title']) ? $post['meta_title'] : Translate::t('Real Estate Agency');

            $object->registerMetaTag([
                'name' => 'description',
                'content' => isset($post['meta_desc']) ? $post['meta_desc'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'keywords',
                'content' => isset($post['meta_keywords']) ? $post['meta_keywords'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'og:url',
                'content' => isset($page_custom_settings['canonical_link']) ? $page_custom_settings['canonical_link'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            ]);

            $object->registerMetaTag([
                'property' => 'og:image',
                'content' => isset($post['attachments'][0]) ? $post['attachments'][0] : Sitehelper::get_site_logo($object),
            ]);

            $object->registerMetaTag([
                'property' => 'og:type',
                'content' => 'post',
            ]);

            $object->registerMetaTag([
                'property' => 'og:title',
                'content' => isset($post['meta_title']) ? $post['meta_title'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'og:description',
                'content' => isset($post['meta_desc']) ? $post['meta_desc'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'fb:app_id',
                'content' => isset($custom_settings['fb_app_id']) ? $custom_settings['fb_app_id'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'keywords',
                'content' => isset($post['meta_keywords']) ? $post['meta_keywords'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'theme-color',
                'content' => isset($property['meta_theme_color']) ? $property['meta_theme_color'] : '#1e1e54',
            ]);
        } else {
            $object->title = isset(Sitehelper::get_page_data($object)['meta_title']) ? self::get_page_data($object)['meta_title'] : Translate::t('Real Estate Agency');
            
            $object->registerMetaTag([
                'name' => 'description',
                'content' => isset(Sitehelper::get_page_data($object)['meta_desc']) ? Sitehelper::get_page_data($object)['meta_desc'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'keywords',
                'content' => isset(Sitehelper::get_page_data($object)['meta_keywords']) ? Sitehelper::get_page_data($object)['meta_keywords'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'og:url',
                'content' => isset($page_custom_settings['canonical_link']) ? $page_custom_settings['canonical_link'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            ]);

            $object->registerMetaTag([
                'property' => 'og:image',
                'content' => isset($page_custom_settings['og_img']) ? Cms::ResizeImage($page_custom_settings['og_img'], 400) : Sitehelper::get_site_logo($object),
            ]);

            $object->registerMetaTag([
                'property' => 'og:type',
                'content' => 'website',
            ]);

            $object->registerMetaTag([
                'property' => 'og:title',
                'content' => isset(Sitehelper::get_page_data($object)['meta_title']) ? self::get_page_data($object)['meta_title'] : Translate::t('Real Estate Agency'),
            ]);

            $object->registerMetaTag([
                'property' => 'og:description',
                'content' => isset(Sitehelper::get_page_data($object)['meta_desc']) ? Sitehelper::get_page_data($object)['meta_desc'] : '',
            ]);

            $object->registerMetaTag([
                'property' => 'fb:app_id',
                'content' => isset($custom_settings['fb_app_id']) ? $custom_settings['fb_app_id'] : '',
            ]);

            $object->registerMetaTag([
                'name' => 'theme-color',
                'content' => isset($property['meta_theme_color']) ? $property['meta_theme_color'] : '#1e1e54',
            ]);
        }
    }

    public static function get_locations_properties_count($country = '1')
    {
        $locationGroups = Dropdowns::locations([], false, [], $country);

        return $locationGroups;
    }

    public static function get_lg_by_key()
    {
        $locationGroups = Dropdowns::locationGroups();
        $locationGroups = Dropdowns::prepare_select_data($locationGroups, 'key_system', 'value');

        return $locationGroups;
    }

    public static function locations_properties_count($lg_key = "", $sale = '')
    {
        self::initialize();
        $file = Functions::directory() . 'locations_props_count_' . $lg_key . '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {

            if ($sale)
                $sale = '&sale=1';

            $url = self::$apiUrl . 'properties&user_apikey=' . self::$api_key . "&count=true&lg_by_key[]=" . $lg_key . $sale;

            $file_data = Functions::getCRMData($url);

            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function ResizeImage($url, $size = 1200, $type = 'cms_medias')
    {
        self::initialize();
        $settings = Cms::settings();

        $url_array = explode('/', $url);
        $name = end($url_array);

        $img_type = explode('.', $name);
        $img_type = end($img_type);

        if ($type == 'property') {
            $needle = prev($url_array);
            $url = str_replace($url_array[0] . '//' . $url_array[2] . '/' . $url_array[3], self::$property_img_resize_link, $url);
            return str_replace("/{$needle}/", "/{$needle}/{$size}/", $url);
        }

        return $url;
    }

    public static function get_location_group_category($key_system = 'key_system',$lg_value = 'value',$top_level_category = 'top_level_category', $sequence = 'sequence')
    {
        $locationGroups = Dropdowns::locationGroups();
        $lang = App::getLocale() == "es" ? 'es_AR' : App::getLocale();
        $finalFormatedSelectArray = array();

        foreach ($locationGroups as $key => $value) {
            $finalFormatedSelectArray[$key]['option_key'] = $value[$key_system];
            if (isset($value[$key_system])) {
                $finalFormatedSelectArray[$key]['option_value'] = (is_array($value[$lg_value]) ? (isset($value[$lg_value][$lang]) && !empty($value[$lg_value][$lang]) ? $value[$lg_value][$lang] : Translate::t($value[$lg_value]['en'])) : $value[$lg_value]);
                $finalFormatedSelectArray[$key]['top_level_category'] = (is_array($value[$top_level_category]) ? (isset($value[$top_level_category][$lang]) && !empty($value[$top_level_category][$lang]) ? $value[$top_level_category][$lang] : Translate::t($value[$top_level_category]['en'])) : $value[$top_level_category]);
                $finalFormatedSelectArray[$key]['sequence'] = (is_array($value[$sequence]) ? (isset($value[$sequence][$lang]) && !empty($value[$sequence][$lang]) ? $value[$sequence][$lang] : Translate::t($value[$sequence]['en'])) : $value[$sequence]);
            } else {
                $finalFormatedSelectArray[$key]['option_value'] = isset($value[$lang]) ? (isset($value[$lang]) && !empty($value[$lang]) ? $value[$lang] : Translate::t($value['en'])) : '';
            }
        }

       return $finalFormatedSelectArray;
    }

    public static function get_all_type($active = false)
    {
        self::initialize();
        $file = Functions::directory() . 'property_types_' . $active . '.json';

        $query = [
            "non_excluded" => 1
        ];

        $options = [
            "page" => 1,
            "limit" => 1000,
        ];

        //$post_data = $active  ? ["status" => "active","options"=>$options] : [];
        $post_data = ["query" => $query, "options" => $options];

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Content-Length' => strlen(json_encode($post_data))
                ])->withBody(json_encode($post_data), 'application/json')
            ->post(self::$node_url . 'commercial_types?user_apikey=' . self::$api_key);

            file_put_contents($file, $response);
        } else {
            $response = file_get_contents($file);
        }

        return json_decode($response, TRUE);
    }

    public static function checkMobileAccess()
    {
        $ua = $_SERVER['HTTP_USER_AGENT'];

        $is_mobile = (

            (strpos($ua, 'iPhone') !== false) // iPhone

            || ((strpos($ua, 'Android') !== false) && (strpos($ua, 'Mobile') !== false)) // Android Mobile

            || (strpos($ua, 'Windows Phone') !== false) // Windows Phone

            || (strpos($ua, 'BlackBerry') !== false) // BlackBerry

        );

        return isset($is_mobile) && $is_mobile ? $is_mobile : false;
    }

    public static function closetags($html)
    {
        // preg_match_all('#<(?!meta|img|br|hr|input\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        // $openedtags = $result[1];
        // preg_match_all('#</([a-z]+)>#iU', $html, $result);
        // $closedtags = $result[1];
        // $len_opened = count($openedtags);
        // if (count($closedtags) == $len_opened) {
        //     return $html;
        // }
        // $openedtags = array_reverse($openedtags);
        // for ($i=0; $i < $len_opened; $i++) {
        //     if (!in_array($openedtags[$i], $closedtags)) {
        //         $html .= '</'.$openedtags[$i].'>';
        //     } else {
        //         unset($closedtags[array_search($openedtags[$i], $closedtags)]);
        //     }
        // }
        // $html = preg_replace("/<img[^>]+\>/i", " ", $html); 

        $doc = new DOMDocument();

        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $html = $doc->saveHTML();
        $html = preg_replace("/<img[^>]+\>/i", " ", $html);

        return $html;
    }

    public static function html_select($data, $options = [])
    {
        return Dropdowns::html_select($data, $options = []);
    }

    public static function html_select_2($data, $options = [])
    {
        return Dropdowns::html_select_2($data, $options = []);
    }

    public static function make_dropdown_array($raw_array = [])
    {
        $finalArray = [];
        $count = 0;
        if (isset($raw_array) && !empty($raw_array)) {
            foreach ($raw_array as $key => $value) {
                $finalArray[$count]['option_value'] = $value;
                $finalArray[$count]['option_key'] = $key;
                $count = $count + 1;
            }
        }

        return $finalArray;
    }

    public static function getCategories()
    {
        self::initialize();
        $url = self::$apiUrl . 'cms/post-count&user=' . self::$user . '&site_id=' . self::$site_id;

        $response = Http::get($url);

        return json_decode($response);
    }

    public static function prepare_without_count_select_data($dataArray, $option_key_index = 'key', $option_value_index = 'value')
    {
        $finalFormatedSelectArray = array();

        foreach ($dataArray as $key => $value) {
            $finalFormatedSelectArray[$key]['option_key'] = $value[$option_key_index];
            if (isset($value[$option_value_index])) {
                $finalFormatedSelectArray[$key]['option_value'] = (is_array($value[$option_value_index]) ? $value[$option_value_index]['en'] : $value[$option_value_index]);
            } else {
                $finalFormatedSelectArray[$key]['option_value'] = isset($value['en']) ? $value['en'] : '';
            }
        }

        return $finalFormatedSelectArray;
    }

    public static function cities_html($selected_locationGroups, $options = array('name' => 'location[]'))
    {
        $locationGroups = Dropdowns::locationGroups();
        $cities = [];
        $city = [];
        $lang = strtolower(App::getLocale()) == 'es' ? 'es_AR' : strtolower(App::getLocale());

        foreach ($selected_locationGroups as $selected_locationGroup) {
            foreach ($locationGroups as $locationGroup) {
                if ($selected_locationGroup == $locationGroup['key_system']) {
                    $lGroups[] = $locationGroup;
                    if (isset($locationGroup['cities_value'])) {
                        $cities = self::prepare_without_count_select_data($locationGroup['cities_value'], 'key', strtolower(App::getLocale()) == 'es' ? 'es_AR' : strtolower(App::getLocale()));
                    }
                }
            }

            foreach ($cities as $value) {
                $city[] = $value;
            }
        }

        $city = array_unique($city, SORT_REGULAR);
        usort($city, "self::sortedLocation");
        
        echo self::html_select_2($city, $options);

        exit;
    }

    public static function sortedLocation($a, $b)
    {
        return strcmp($a["option_value"], $b["option_value"]);
    }

    public static function cities_available_html($post, $options = array('name' => 'location[]'))
    {
        $lang = App::getLocale() == 'es' ? 'es_AR' : App::getLocale();
        $selected_location_groups = isset($post["location_groups"]) && !empty($post["location_groups"]) ? $post["location_groups"] : [];

        $locationGroups = self::get_location_groups_with_properties("allow_cities", $selected_location_groups);
        $cities = [];
        $lang = strtolower(App::getLocale()) == 'es' ? 'es_AR' : strtolower(App::getLocale());

        if (isset($locationGroups["docs"]) && !empty($locationGroups["docs"])) {
            foreach ($locationGroups['docs'] as $locationGroup) {
                if (isset($locationGroup) && !empty($locationGroup)) {
                    $cities[$locationGroup['key']]['option_key'] = isset($locationGroup['key']) && !empty($locationGroup['key']) ? $locationGroup['key'] : '';
                    $cities[$locationGroup['key']]['option_value'] = isset($locationGroup["value"][$lang]) && !empty($locationGroup["value"][$lang]) ? $locationGroup["value"][$lang] : (isset($locationGroup["value"]["en"]) && !empty($locationGroup["value"]["en"]) ? $locationGroup["value"]["en"] : "");
                }
            }
        }

        $city = array_unique($cities, SORT_REGULAR);
        usort($city, "self::sortedLocation");
        echo self::html_select_2($city, $options);

        exit;
    }

    public static function get_location_groups_with_properties($types = "", $selected_groups = [], $country = [], $provinces = [], $city = [])
    {
        self::initialize();
        $lang = App::getLocale() == 'es' ? 'es_AR' : App::getLocale();
        $file = Functions::directory() . 'location_groups_with_properties_' . implode('-', $selected_groups) . "_" . implode('-', $country) . "_" . implode('-', $provinces) . "_" . implode('-', $city) . "_" . $lang . '.json';

        $query = [
            "sort" => $lang,
            "order" => "DESC", // DESC , ASC
            "prop_status" => isset(self::$status) && !empty(self::$status) ? self::$status : ['Available', 'Under Offer']
        ];

        if (isset($types) && !empty($types)) {
            $query[$types] = true;
        }

        if (isset($selected_groups) && !empty($selected_groups)) {
            $query["location_group"] = array_map('intval', $selected_groups);
        }

        if (isset($country) && !empty($country)) {
            $query["country"] = ['$in' => array_map('intval', $country)];
        }

        if (isset($provinces) && !empty($provinces)) {
            $query["provinces"] = ['$in' => array_map('intval', $provinces)];
        }

        if (isset($city) && !empty($city)) {
            $query["city"] = ['$in' => array_map('intval', $city)];
        }

        $post_data = ["query" => $query];
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$node_url . 'locationgroups/get-location-groups-with-properties?user=' . self::$user;
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data))
            ])->post($url, $post_data);

            file_put_contents($file, $response);
        } else {
            $response = file_get_contents($file);
        }

        return json_decode($response, TRUE);
    }

    public static function get_locations_key_by_value_at_least_one_property($post = [])
    {
        $lang = App::getLocale() == 'es' ? 'es_AR' : App::getLocale();
        $selected_location_groups = isset($post["location_groups"]) && !empty($post["location_groups"]) ? $post["location_groups"] : [];
        $selected_countries = isset($post["countries"]) && !empty($post["countries"]) ? $post["countries"] : [];
        $selected_provinces = isset($post["provinces"]) && !empty($post["provinces"]) ? $post["provinces"] : [];
        $selected_cities = isset($post["cities"]) && !empty($post["cities"]) ? $post["cities"] : [];

        $location_groups = self::locations_key_by_value($selected_location_groups, $selected_countries, $selected_provinces, $selected_cities);
        $finalFormatedSelectArray = [];
        if (isset($location_groups) && !empty($location_groups)) {
            if (isset($location_groups["docs"]) && !empty($location_groups["docs"])) {
                foreach ($location_groups['docs'] as $location) {
                    $finalFormatedSelectArray[$location['key']]['option_key'] = isset($location['key']) && !empty($location['key']) ? $location['key'] : '';
                    $finalFormatedSelectArray[$location['key']]['option_value'] = isset($location["value"][$lang]) && !empty($location["value"][$lang]) ? $location["value"][$lang] : (isset($location["value"]["en"]) && !empty($location["value"]["en"]) ? $location["value"]["en"] : '');
                }
            }
        }

        return $finalFormatedSelectArray;
    }

    public static function locations_key_by_value($selected_groups = [], $country = [], $provinces = [], $city = [])
    {
        self::initialize();
        $lang = App::getLocale() == 'es' ? 'es_AR' : App::getLocale();
        $file = Functions::directory() . 'object_locations_' . implode('-', $selected_groups) . "_" . implode('-', $country) . "_" . implode('-', $provinces) . "_" . implode('-', $city) . "_" . $lang . '.json';

        $query = [
            "sort" => $lang,
            "order" => "DESC", // DESC , ASC
            "frontend_api" => 1,
            "prop_status" => isset(self::$status) && !empty(self::$status) ? self::$status : ['Available', 'Under Offer']
        ];

        if (isset($selected_groups) && !empty($selected_groups)) {
            $query["location_group"] = array_map('intval', $selected_groups);
        }

        if (isset($country) && !empty($country)) {
            $query["country"] = ['$in' => array_map('intval', $country)];
        }

        if (isset($provinces) && !empty($provinces)) {
            $query["provinces"] = ['$in' => array_map('intval', $provinces)];
        }

        if (isset($city) && !empty($city)) {
            $query["city"] = ['$in' => array_map('intval', $city)];
        }

        $post_data = ["query" => $query];
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$node_url . 'locations?user=' . self::$user;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data))
            ])->post($url, $post_data);

            file_put_contents($file, $response);
        } else {
            $response = file_get_contents($file);
        }

        return json_decode($response, TRUE);
    }

    public static function previousUrlModify($url = "", $options = [])
    {
        $referrerUrl = !empty($url) ? $url : URL::previous();
        
        if ($referrerUrl) {
            // Parse URL and extract query parameters
            $parsedUrl = parse_url($referrerUrl);
            $params = isset($parsedUrl['query']) ? $parsedUrl['query'] : "";
            parse_str($params, $queryParams);            
            
            // Update or add query parameter
            if(!empty($options)){
                foreach ($options as $key => $value) {                    
                    $queryParams[$key] = $value;
                }
            }

            // Build updated query string
            $newQueryString = http_build_query($queryParams);
            // Rebuild URL
            $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            if (isset($parsedUrl['port'])) $newUrl .= ':' . $parsedUrl['port'];
            if (isset($parsedUrl['path'])) $newUrl .= $parsedUrl['path'];
            if ($newQueryString) $newUrl .= '?' . $newQueryString;
            if (isset($parsedUrl['fragment'])) $newUrl .= '#' . $parsedUrl['fragment'];

            // Output updated URL
            return $newUrl;
        } else {
            return $referrerUrl;
        }
    }
}