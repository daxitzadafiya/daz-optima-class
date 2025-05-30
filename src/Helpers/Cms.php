<?php

namespace Daxit\OptimaClass\Helpers;

use Daxit\OptimaClass\Components\Routehelper;
use Daxit\OptimaClass\Traits\ConfigTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;

/**
 * Cms Functions to get CMS data
 *
*/
class Cms
{
    use ConfigTrait;

    private static $image_url = 'https://images.optima-crm.com/resize/cms_medias/'; // For resize image URLs
    private static $image_url_users = ' https://images.optima-crm.com/resize/users/'; // For getUsers image URLs
    private static $image_url_without_resize = 'https://images.optima-crm.com/cms_medias/'; // For svg images URLs

    public static function settings()
    {
        self::initialize();

        $file = Functions::directory() . 'settings.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'cms/setting&user=' . self::$user . '&id=' . self::$template;

            $file_data = Functions::getCRMData($url);
            if(json_decode($file_data, true)){
                file_put_contents($file, $file_data);
            }
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    /**
     * For General settings no need to pass params
     * and
     * For Page settings pass page_data['custom_settings'] as param without language
     * @param string $custom_settings
     *
     * @return false|array
    */
    public static function custom_settings($custom_settings = [])
    {
        if (!$custom_settings) {
            $settings = self::settings();
            $custom_settings = $settings['custom_settings'];
        } else {
            $lang = strtoupper(App::getLocale());

            if (isset($custom_settings[$lang])) {
                $custom_settings = $custom_settings[$lang];
            } else {
                return false;
            }
        }
        $func = function ($k, $v) {
            return [isset($v['key']) ? $v['key'] : '',  isset($v['value']) ? $v['value'] : ''];
        };

        return Functions::array_map_assoc($func, $custom_settings);
    }

    public static function getTranslations()
    {
        self::initialize();

        $lang = App::getLocale();
        $file = Functions::directory() . 'translations_' . $lang . '.json';

        $query = isset(self::$user) ? '&user=' . self::$user : '';
        $query .= isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
        $query .= isset(self::$commercial) ? '&commercial=' . self::$commercial : '';
        $query .= '&lang=' . strtoupper($lang);

        $url = self::$apiUrl . 'cms/get-translatons' . $query;

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function menu($name, $getUrlsFromPage = true, $getOtherSettings = true)
    {
        self::initialize();
        $lang = strtoupper(App::getLocale());
        $file = Functions::directory() . 'menu_' . str_replace(' ', '_', strtolower($name)) . '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $site_id = isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
            $url = self::$apiUrl . 'cms/menu-by-name&user=' . self::$user . '&name=' . $name . $site_id;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $dataArr = json_decode($file_data, TRUE);
        $items = (isset($dataArr['menu_items'])) ? $dataArr['menu_items'] : '';
        $finalData = [];
        if ($items) {
            foreach ($items as $data) {
                if (isset($data['item']['id']['oid']) && $getUrlsFromPage) {
                    $pageData = self::pageBySlug(null, 'EN', $data['item']['id']['oid']);
                    $data['item']['slug'] = $pageData['slug_all'];
                    if (isset($data['children'])) {
                        foreach ($data['children'] as $key => $ch) {
                            if (isset($ch['item']['id']['oid'])) {
                                $pageData = self::pageBySlug(null, 'EN', $ch['item']['id']['oid']);
                                $data['children'][$key]['item']['slug'] = $pageData['slug_all'];
                                if ($getOtherSettings) {
                                    $url = isset($pageData['featured_image'][$lang]['name']) ? self::$cms_img . '/' . $pageData['_id'] . '/' . $pageData['featured_image'][$lang]['name'] : '';
                                    $data['children'][$key]['item']['custom_settings'] = ((isset($pageData['custom_settings'][$lang]) && is_array($pageData['custom_settings'][$lang])) ? $pageData['custom_settings'][$lang] : []);
                                    $data['children'][$key]['item']['featured_image'] = isset($pageData['featured_image']) ? $pageData['featured_image'] : '';
                                }
                            }
                        }
                    }
                    $finalData[] = $data;
                } else {
                    $finalData[] = $data;
                }
            }
        }
        $dataArr['menu_items'] = $finalData;
        return $dataArr;
    }

    public static function pageBySlug($slug, $lang_slug = 'EN', $id = null, $type = 'page', $options = [])
    {
        self::initialize();

        if ($id == null) {
            $file = Functions::directory() . str_replace('/', '_', $slug) . '-' . $type . '.json';
        } else {
            $file = Functions::directory() . $id . '.json';
        }

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = isset($options['seoimage']) ? '&seoimage=yes' : '';
            $query .= isset($options['template']) ? '&expand=template' : '';
            $query .= isset($_GET['created_full_name']) ? '&created_full_name=' . $_GET['created_full_name'] : '';
            if ($id == null) {
                $site_id = isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
                $url = self::$apiUrl . 'cms/page-by-slug&user=' . self::$user . '&lang=' . $lang_slug . '&slug=' . $slug . '&type=' . $type . $site_id . $query;

                $file_data = Functions::getCRMData($url);
            } else {
                $url = self::$apiUrl . 'cms/page-view-by-id&user=' . self::$user . '&id=' . $id . $query;
                $file_data = Functions::getCRMData($url);
            }

            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $data = json_decode($file_data, TRUE);
        $lang = strtoupper(App::getLocale());

        $attachment_url = isset($data['featured_image'][$lang]['name']) ? self::$cms_img . '/' . $data['_id'] . '/' . $data['featured_image'][$lang]['name'] : '';

        if (isset($options['seoimage'])) {
            $attachment_url = isset($data['featured_image'][$lang]['file_md5_name']) ? self::$cms_img . '/' . $data['_id'] . '/' . $data['featured_image'][$lang]['file_md5_name'] : $attachment_url;
        }

        return [
            'featured_image' => isset($attachment_url) && !empty($attachment_url) ? Cms::ResizeImage($attachment_url) : '',
            'featured_image_200' => isset($attachment_url) && !empty($attachment_url) ? Cms::ResizeImage($attachment_url, 200) : '',
            'featured_image_seo_alt_desc' => isset($data['featured_image'][$lang]['seo_alt_desc']) ? $data['featured_image'][$lang]['seo_alt_desc'] : '',
            'featured_image_seo_meta_title' => isset($data['featured_image'][$lang]['seo_meta_title']) ? $data['featured_image'][$lang]['seo_meta_title'] : '',
            'content' => isset($data['content'][$lang]) ? $data['content'][$lang] : '',
            'title' => isset($data['title'][$lang]) ? $data['title'][$lang] : '',
            'slug' => isset($data['slug'][$lang]) ? $data['slug'][$lang] : '',
            'slug_all' => isset($data['slug']) ? $data['slug'] : '',
            'meta_title' => isset($data['meta_title'][$lang]) ? $data['meta_title'][$lang] : '',
            'meta_desc' => isset($data['meta_desc'][$lang]) ? $data['meta_desc'][$lang] : '',
            'meta_keywords' => isset($data['meta_keywords'][$lang]) ? $data['meta_keywords'][$lang] : '',
            'custom_settings' => isset($data['custom_settings']) ? $data['custom_settings'] : '',
            'created_at' => isset($data['created_at']) ? $data['created_at'] : '',
            'categories' => isset($data['categories']) ? $data['categories'] : [],
            'created_username' => isset($data['created_username']) ? $data['created_username'] : [],
            'created_by' => isset($data['created_by']) ? $data['created_by'] : [],
        ];
    }

    public static function ResizeImage($url, $size = 1200, $type = 'cms_medias')
    {
        $settings = self::settings();

        $url_array = explode('/', $url);
        $name = end($url_array);

        $img_type = explode('.', $name);
        $img_type = end($img_type);

        if ($type == 'user') {
            return str_replace($name, $size . '/' . $name, $url);
        } else if ($type == 'property') {
            $needle = prev($url_array);
            return str_replace("/{$needle}/", "/{$size}/", $url);
        }
        if ($img_type == 'svg') {
            return self::$image_url_without_resize . $settings['site_id'] . '/' . $name;
        }

        return self::$image_url . $settings['site_id'] . '/' . $size . '/' . $name;
    }

    public static function menuYII($id)
    {
        self::initialize();
        $lang = strtoupper(App::getLocale());
        $file = Functions::directory() . 'menu.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'cms/menu&user=' . self::$user . '&id=' . $id;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $menu = json_decode($file_data, TRUE);

        $itemsArr = [];

        foreach ($menu['menu_items'] as $key => $value) {
            if (isset($value['children']) && count($value['children']) > 0) {
                $childArr = [];
                foreach ($value['children'] as $childkey => $child) {
                    if (isset($child['children']) && count($child['children']) > 0) {
                        $childArrNested = [];
                        foreach ($child['children'] as $childkeyNested => $childNested) {
                            $childArrNested[] = ['label' => (isset($childNested['item']['title'][$lang]) && $childNested['item']['title'][$lang] != '') ? $childNested['item']['title'][$lang] : 'please set menu label', 'url' => (isset($childNested['item']['slug'][$lang]) && $childNested['item']['slug'][$lang] != '') ? '/' . $childNested['item']['slug'][$lang] : 'slug-not-set'];
                        }
                        $childArr[] = ['label' => (isset($child['item']['title'][$lang]) && $child['item']['title'][$lang] != '') ? $child['item']['title'][$lang] : 'please set menu label', 'items' => $childArrNested];
                    } else {
                        $childArr[] = ['label' => (isset($child['item']['title'][$lang]) && $child['item']['title'][$lang] != '') ? $child['item']['title'][$lang] : 'please set menu label', 'url' => (isset($child['item']['slug'][$lang]) && $child['item']['slug'][$lang] != '') ? str_replace('//', '/', ('/' . $child['item']['slug'][$lang])) : 'slug-not-set'];
                    }
                }
                $itemsArr[] = ['label' => (isset($value['item']['title'][$lang]) && $value['item']['title'][$lang] != '') ? $value['item']['title'][$lang] : 'please set menu label', 'items' => $childArr];
            } else {
                $itemsArr[] = ['label' => (isset($value['item']['title'][$lang]) && $value['item']['title'][$lang] != '') ? $value['item']['title'][$lang] : 'please set menu label', 'url' => (isset($value['item']['slug'][$lang]) && $value['item']['slug'][$lang] != '') ? str_replace('//', '/', ('/' . $value['item']['slug'][$lang])) : 'slug-not-set'];
            }
        }
        return $itemsArr;
    }

    public static function languages()
    {
        self::initialize();
        $file = Functions::directory() . 'languages.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $site_id = isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
            $url = self::$apiUrl . 'cms/languages&user=' . self::$user . $site_id;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }
        return json_decode($file_data, TRUE);
    }

    /**
     * siteLanguages() is for Sites Configration and creating related translate components
    */
    public static function siteLanguages()
    {
        self::initialize();
        $languages = self::languages();
        $basePath = resource_path();

        if (!empty($languages) && count($languages)) {
            $exclude_langs = isset(self::$excluded_langs) ? self::$excluded_langs : [];
            $languages = array_reduce($languages, function ($langs, $lang) use ($exclude_langs) {
                if (!in_array($lang['key'], $exclude_langs))
                    $langs[] = strtolower($lang['key']);

                return $langs;
            }, []);
        }

        /* Creating translation Components from languages  */
        foreach ($languages as $language) {
            if (!is_dir($basePath . './lang/' . $language) && is_dir($basePath . './lang/en')) {
                mkdir($basePath . './lang/' . $language);
                file_put_contents($basePath . './lang/' . $language . '/app.php', '');
                copy($basePath . './lang/en/app.php', $basePath . './lang/' . $language . '/app.php');
            }
        }

        return $languages;
    }

    public static function SystemLanguages()
    {
        self::initialize();
        $file = Functions::directory() . 'SystemLanguages.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'cms/system-languages&user=' . self::$user . '&name=gogo';
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function UserLanguages()
    {
        self::initialize();
        $file = Functions::directory() . 'UserLanguages.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'cms/user-languages&user=' . self::$user . '&name=gogo';
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function Categories()
    {
        self::initialize();
        $file = Functions::directory() . 'categories.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = isset(self::$user) ? '&user=' . self::$user : '';
            $query .= isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
            $url = self::$apiUrl . 'cms/categories' . $query;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    /**
     * Get CMS page data by id, page_id or slug
     *
     * @param array $params =   [
     *                              ['id'] => '5eb3ebc9fe107a46744d2346',
     *                              ['page_id'] => '6425',
     *                              ['slug'] => 'home',
     *                              ['lang'] => 'EN',
     *                              ['type'] => 'page',
     *                              ['seoimage'] => true,
     *                              ['template'] => true,
     *                          ]
     *
     * @return array|void
    */
    public static function getPage($params = [])
    {
        self::initialize();
        $slug = isset($params['slug']) ? $params['slug'] : null;
        $id = isset($params['id']) ? $params['id'] : null;
        $page_id = isset($params['page_id']) ? $params['page_id'] : null;

        if ($slug !== null || $id !== null || $page_id !== null) {
            $slug_lang = isset($params['lang']) ? $params['lang'] : 'EN';
            $type = isset($params['type']) ? $params['type'] : 'page';
            $imagesSeo = isset($params['seoimage']) ? true : false;
            $template = isset($params['without_template']) ? false : true;

            if ($id) {
                $file = Functions::directory() . $id;
            } elseif ($page_id) {
                $file = Functions::directory() . $page_id;
            } else {
                $file = Functions::directory() . str_replace('/', '_', $slug) . '-' . $type;
            }

            $file .= $template ? '_with_template' : '';
            $file .= '.json';

            if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
                $query = isset(self::$user) ? '&user=' . self::$user : '';
                $query .= $template ? '&expand=template' : '';

                if ($id !== null) {
                    $query .= '&id=' . $id;

                    $url = self::$apiUrl . 'cms/page-view-by-id' . $query;

                    $file_data = Functions::getCRMData($url);
                } elseif ($page_id !== null) {
                    $query .= '&page_id=' . $page_id;

                    $url = self::$apiUrl . 'cms/page-view-by-page-id' . $query;

                    $file_data = Functions::getCRMData($url);
                } else {
                    $query .= isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
                    $query .= '&lang=' . strtoupper($slug_lang);
                    $query .= '&slug=' . $slug;
                    $query .= '&type=' . $type;
                    $query .= $imagesSeo ? '&seoimage=yes' : '';
                    $query .= '&condition=and';

                    $url = self::$apiUrl . 'cms/page-by-slug' . $query;

                    $file_data = Functions::getCRMData($url);
                }

                if (json_decode($file_data, true)) {
                    file_put_contents($file, $file_data);
                }
            } else {
                $file_data = file_get_contents($file);
            }

            $data = json_decode($file_data, TRUE);
            $lang = strtoupper(App::getLocale());
            $attachment_url = isset($data['featured_image'][$lang]['name']) ? self::$cms_img . '/' . $data['_id'] . '/' . $data['featured_image'][$lang]['name'] : '';

            if ($imagesSeo) {
                $attachment_url = isset($data['featured_image'][$lang]['file_md5_name']) ? self::$cms_img . '/' . $data['_id'] . '/' . $data['featured_image'][$lang]['file_md5_name'] : $attachment_url;
            }

            return [
                'featured_image' => isset($attachment_url) && !empty($attachment_url) ? Cms::ResizeImage($attachment_url) : '',
                'featured_image_200' => isset($attachment_url) && !empty($attachment_url) ? Cms::ResizeImage($attachment_url, 200) : '',
                'featured_image_seo_alt_desc' => isset($data['featured_image'][$lang]['seo_alt_desc']) ? $data['featured_image'][$lang]['seo_alt_desc'] : '',
                'featured_image_seo_meta_title' => isset($data['featured_image'][$lang]['seo_meta_title']) ? $data['featured_image'][$lang]['seo_meta_title'] : '',
                'content' => isset($data['content'][$lang]) ? $data['content'][$lang] : '',
                'title' => isset($data['title'][$lang]) ? $data['title'][$lang] : '',
                'slug' => isset($data['slug'][$lang]) ? $data['slug'][$lang] : '',
                'slug_all' => isset($data['slug']) ? $data['slug'] : '',
                'meta_title' => isset($data['meta_title'][$lang]) ? $data['meta_title'][$lang] : '',
                'meta_desc' => isset($data['meta_desc'][$lang]) ? $data['meta_desc'][$lang] : '',
                'meta_keywords' => isset($data['meta_keywords'][$lang]) ? $data['meta_keywords'][$lang] : '',
                'custom_settings' => isset($data['custom_settings']) ? $data['custom_settings'] : '',
                'created_at' => isset($data['created_at']) ? $data['created_at'] : '',
                'view_path' => isset($data['template']['viewPath']) ? $data['template']['viewPath'] : '',
            ];
        }
    }

    public static function postById($id)
    {
        self::initialize();
        $file = Functions::directory() . $id . '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'cms/page-view-by-id&user=' . self::$user . '&id=' . $id;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $data = json_decode($file_data, TRUE);
        $lang = strtoupper(App::getLocale());
        $url = isset($data['featured_image'][$lang]['name']) ? self::$cms_img . '/' . $data['_id'] . '/' . $data['featured_image'][$lang]['name'] : '';

        return [
            'featured_image' => isset($data['featured_image'][$lang]['name']) ? Cms::ResizeImage($url) : '',
            'featured_image_200' => isset($data['featured_image'][$lang]['name']) ? Cms::ResizeImage($url, 200) : '',
            'content' => isset($data['content'][$lang]) ? $data['content'][$lang] : '',
            'title' => isset($data['title'][$lang]) ? $data['title'][$lang] : '',
            'slug' => isset($data['slug'][$lang]) ? $data['slug'][$lang] : '',
            'slug_all' => isset($data['slug']) ? $data['slug'] : '',
            'meta_title' => isset($data['meta_title'][$lang]) ? $data['meta_title'][$lang] : '',
            'meta_desc' => isset($data['meta_desc'][$lang]) ? $data['meta_desc'][$lang] : '',
            'meta_keywords' => isset($data['meta_keywords'][$lang]) ? $data['meta_keywords'][$lang] : '',
            'custom_settings' => isset($data['custom_settings']) ? $data['custom_settings'] : '',
            'created_at' => isset($data['created_at']) ? $data['created_at'] : '',
        ];
    }

    /**
     * Set some params if needed before params.php get loaded in web
    */
    public static function setParams()
    {
        $locale = Request::has('locale') ? Request::input('locale') : 'en';

        App::setLocale($locale);
    }

    public static function Slugs($name)
    {
        self::initialize();
        $file = Functions::directory() . 'slugs' . str_replace(' ', '_', strtolower($name)) . '.json';
        $site_id = isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
        $url = self::$apiUrl . 'cms/get-slugs&user=' . self::$user . $site_id;

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $file_data = Functions::getCRMData($url);

            if ($file_data) {
                file_put_contents($file, $file_data);
            }
        } else {
            $file_data = file_get_contents($file);
        }

        $dataEach = json_decode($file_data, true);
        $lang = strtoupper(App::getLocale());
        $retdata = [];
        $array = [];

        if (!is_array($dataEach) || count($dataEach) <= 0) {
            die('Error Getting CMS Data');
        }

        foreach ($dataEach as $key => $data) {
            $array['type'] = isset($data['type']) ? $data['type'] : '';
            $array['slug'] = isset($data['slug'][$lang]) ? $data['slug'][$lang] : '';
            $array['slug_all'] = isset($data['slug']) ? $data['slug'] : '';
            $retdata[] = $array;
        }

        return $retdata;
    }

    /**
     * Get slug data for all pages and posts for current site
     * @param array $params =   [
     *                              ['name'] => 'page',
     *                              ['without_templates'] => true,
     *                              ['without_tags'] => true,
     *                          ]
     *
     * @return array
    */
    public static function getSlugs($params = [])
    {
        self::initialize();
        $name = isset($params['name']) ? $params['name'] : 'all';
        $with_templates = isset($params['without_templates']) ? false : true;
        $with_tags = isset($params['without_tags']) ? false : true;
        $file = Functions::directory() . 'slugs_for_';
        $file .= str_replace(' ', '_', strtolower($name));
        $file .= $with_templates ? '_with_templates' : '';
        $file .= $with_tags ? '_with_tags' : '';
        $file .= '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = isset(self::$user) ? '&user=' . self::$user : '';
            $query .= isset(self::$site_id) ? '&site_id=' . self::$site_id : '';
            $query .= $with_templates ? '&expand=template' : '';
            $query .= $with_tags ? '&tags=true' : '';

            $url = self::$apiUrl . 'cms/get-slugs-v2' . $query;

            $file_data = Functions::getCRMData($url);

            if (json_decode($file_data, true)) {
                file_put_contents($file, $file_data);
            }
        } else {
            $file_data = file_get_contents($file);
        }

        $data = json_decode($file_data, true);

        if (!is_array($data) || count($data) <= 0) {
            die('Error Getting CMS Data');
        }

        return $data;
    }

    /**
     * Get page slug by tag assigned to page
     * @param mixed $tag = 'propertyDetails'
     *
     * @return mixed|string
    */
    public static function getSlugByTagName($tag)
    {
        $lang = strtoupper(App::getLocale());
        $file_data = self::getSlugs();

        foreach ($file_data as $data) {
            if (isset($data['tags'][0]) && $data['tags'][0] == $tag) {
                return isset($data['slug'][$lang]) ? $data['slug'][$lang] : $data['slug']['EN'];
            }
        }
        return 'tag-not-found';
    }

    public static function getSlugByTagNameInAllLocales($tag)
    {
        $locales = self::siteLanguages();
        $file_data = self::getSlugs();
        $slugs = [];

        foreach ($file_data as $data) {
            if (isset($data['tags'][0]) && $data['tags'][0] == $tag) {
                foreach($locales as $locale) {
                    $slugs[] = isset($data['slug'][strtoupper($locale)]) ? $data['slug'][strtoupper($locale)] : $data['slug']['EN'];
                }
            }
        }

        return $slugs;
    }

    /**
     * Get Rules for web
     *
     * @return array|mixed
    */
    public static function cmsRules()
    {
        /* to set some config because config not loaded yet in from web */
        self::setParams();
        self::initialize();
        $file = Functions::directory() . 'rules.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $cmsData = self::getSlugs();
            $rules = [];
            foreach ($cmsData as $row) {
                if (isset($row['type']) && $row['type'] == 'page' && isset($row['slug']) && is_array($row['slug'])) {
                    if (isset($row['template']['template_action']) && !empty($row['template']['template_action'])) {
                        if (isset($row['template']['template_pattern']) && !empty($row['template']['template_pattern'])) {
                            foreach ($row['slug'] as $key => $val) {
                                if ($val) {
                                    $rules[] = [
                                        'pattern'  => $val . $row['template']['template_pattern'],
                                        'lang' => strtolower($key),
                                        'route'    => $row['template']['template_action'],
                                        'defaults' => ['slug' => $val],
                                    ];
                                }
                            }
                        }
                        /**
                         * TODO:Remove this code starting here after all template using sites migrate
                         */
                        elseif (
                            strpos($row['template']['template_action'], '/view')
                            || strpos($row['template']['template_action'], '/blog-post') || strpos($row['template']['template_action'], '/recruitment-details')
                        ) {
                            foreach ($row['slug'] as $key => $val) {
                                if ($val) {
                                    $rules[] = [
                                        'pattern'  => $val . '/<title>',
                                        'lang' => strtolower($key),
                                        'route'    => $row['template']['template_action'],
                                        'defaults' => ['slug' => $val],
                                    ];
                                }
                            }
                        }
                        /**
                         * TODO:Remove this code ending here after all template using sites migrate
                         */
                        else {
                            foreach ($row['slug'] as $key => $val) {
                                if ($val) {
                                    $rules[] = [
                                        'pattern'  => $val,
                                        'lang' => strtolower($key),
                                        'route'    => $row['template']['template_action'],
                                        'defaults' => ['slug' => $val],
                                    ];
                                }
                            }
                        }
                    } else {
                        foreach ($row['slug'] as $key => $val) {
                            if ($val) {
                                $rules[] = [
                                    'pattern'  => $val,
                                    'lang' => strtolower($key),
                                    'route'    => 'site/page',
                                    'defaults' => ['slug' => $val],
                                ];
                            }
                        }
                    }
                }else{
                    $post_type_route = config('params.post_type_route', []);
                    if(isset($post_type_route) && !empty($post_type_route)){
                        if (isset($row['type']) && is_array($post_type_route) && in_array($row['type'], array_keys($post_type_route)) && isset($row['slug']) && is_array($row['slug'])) {
                            foreach ($row['slug'] as $key => $val) {
                                if ($val) {
                                    $rules[] = [
                                        'pattern'  => $val,
                                        'lang' => strtolower($key),
                                        'route'    => $post_type_route[$row['type']],
                                        'defaults' => ['slug' => $val],
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            $file_data = json_encode($rules);

            file_put_contents($file, $file_data);

            $groupedRoutes = Routehelper::groupRoutes($rules);

            $routeDefinitions = Routehelper::generateRouteDefinitions($groupedRoutes);

            Routehelper::writeRoutesToFile($routeDefinitions);
        } else {
            $file_data = file_get_contents($file);
        }

        $rules = json_decode($file_data, true);

        return $rules;
    }

    /* Template Code started  */
    public function updateAgencyPageTemplates()
    {
        $post_templates = [];
        $files = (array) self::get_files('php', 1, true);

        foreach ($files as $file => $full_path) {
            if (!preg_match('|Template Name:(.*)$|mi', file_get_contents($full_path), $header)) {
                continue;
            }

            preg_match('|Action Path:(.*)$|mi', file_get_contents($full_path), $action);

            /*  For multiple types like page and post Code start */

            $types = array('page');
            if (preg_match('|Template Post Type:(.*)$|mi', file_get_contents($full_path), $type)) {
                $types = explode(',', self::_cleanup_header_comment($type[1]));
            }

            foreach ($types as $type) {
                $type = self::sanitize_key($type);

                $post_templates[$type][str_replace(".php", "", $file)] = array(
                    "label" => self::_cleanup_header_comment($header[1]),
                    "action" => isset($action[1]) ? self::_cleanup_header_comment($action[1]) : ''
                );
            }

        }

        echo '<pre>';
        print_r($post_templates);
        die;
    }

    public static function get_files($type = null, $depth = 0, $search_parent = false)
    {
        $files = (array) self::scandir(self::getViewPath(), $type, $depth);
        return $files;
    }

    public static function getViewPath()
    {
        return resource_path('views');
    }

    private static function scandir($path, $extensions = null, $depth = 0, $relative_path = '')
    {
        if (!is_dir($path)) {
            return false;
        }

        if ($extensions) {
            $extensions  = (array) $extensions;
            $_extensions = implode('|', $extensions);
        }

        $relative_path = self::trailingslashit($relative_path);
        if ('/' == $relative_path) {
            $relative_path = '';
        }

        $results = scandir($path);
        $files   = array();

        /**
         * Filters the array of excluded directories and files while scanning theme folder.
         *
         * @since 4.7.4
         *
         * @param string[] $exclusions Array of excluded directories and files.
         */
        $exclusions = [];

        foreach ($results as $result) {
            if ('.' == $result[0] || in_array($result, $exclusions, true)) {
                continue;
            }
            if (is_dir($path . '/' . $result)) {
                if (!$depth) {
                    continue;
                }
                $found = self::scandir($path . '/' . $result, $extensions, $depth - 1, $relative_path . $result);
                $files = array_merge_recursive($files, $found);
            } elseif (!$extensions || preg_match('~\.(' . $_extensions . ')$~', $result)) {
                $files[$relative_path . $result] = $path . '/' . $result;
            }
        }

        return $files;
    }

    private static function trailingslashit($string)
    {
        return rtrim($string, '/\\') . '/';
    }

    private static function sanitize_key($key)
    {
        $raw_key = $key;
        $key     = strtolower($key);
        $key     = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return $key;
    }

    private static function _cleanup_header_comment($str)
    {
        return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $str));
    }

    /* Template Code Ended  */
    public static function CacheImage($url, $name, $size = 1200)
    {
        self::initialize();
        $settings = self::settings();
        $filesaved = Functions::directory() . $size . '_' . $name;

        if (isset(self::$ImageFrom) && self::$ImageFrom == 'remote') {
            return self::$image_url . $settings['site_id'] . '/' . $size . '/' . $name;
        } else {
            if (!file_exists($filesaved) || (file_exists($filesaved) && time() - filemtime($filesaved) > 360 * 3600)) {
                $handle = @fopen($url, 'r');
                if (!$handle) {
                    $url = self::$image_url . $settings['site_id'] . '/' . $size . '/' . $name;
                }
                $file_data = Functions::getCRMData($url);
                file_put_contents($filesaved, $file_data);
            }

            return '/uploads/temp/' . $size . '_' . $name;
        }
    }

    public static function iconLogo($name)
    {
        self::initialize();
        $file = Functions::directory() . $name . '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $file_data = self::$rootUrl . 'uploads/cms_settings/' . self::$template . '/' . $name;
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return $file_data;
    }

    public static function postTypes($name, $category = null, $forRoutes = null, $pageSize = 10, $imageseo = false, $options = [])
    {
        self::initialize();

        if(isset($options['user_id']) && !empty($options['user_id'])) {
            $file_name = $name .'UserList'.$options['user_id'];
        } else {
            $file_name = $name;
        }

        if ($name != 'page' && $pageSize == false) {
            $file_name = $name . '-all';
        }

        $file = Functions::directory() . str_replace(' ', '_', strtolower(Functions::clean($file_name))) . str_replace(' ', '_', strtolower(Functions::clean($category))) . '.json';

        if(isset($options['user_id']) && !empty($options['user_id'])) {
            $query = '&user=' . $options['user_id'];
            $query .= isset($options['pagination']) ? '&pagination=' . $options['pagination'] : '';
        } else {
            $query = isset(self::$user) ? '&user=' . self::$user : '';
            $query .= is_numeric($name) ? '&post_type_id=' . $name : '&post_type=' . $name;
        }

        $query .= isset(self::$site_id) ? '&site_id=' . self::$site_id : '';

        if ($name == 'page' || $pageSize == false)
            $query .= '&page-size=false';
        if ($pageSize != false) {
            $query .= '&page-size=' . $pageSize;
            if (isset($options['page'])) {
                $query .= '&page=' . $options['page'];
            }
        }
        if ($category != null)
            $query .= '&category=' . $category;
        if ($imageseo)
            $query .= '&seoimage=yes';

        $cache = isset($options['cache']) ? $options['cache'] : true;

        if(isset($options['user_id']) && !empty($options['user_id'])){
            $url = self::$apiUrl . 'cms/get-all-user-post' . $query;
        }else{
            $url = self::$apiUrl . 'cms/posts' . $query;
        }

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600) || !$cache) {
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $header = get_headers($url, 1);
        $data = json_decode($file_data, TRUE);
        $lang = strtoupper(App::getLocale());
        $ret_data = [];
        $array = [];

        foreach ($data as $key => $data_each) {
            if (isset($header['X-Pagination-Total-Count'])) {
                $array['totalCount'] = $header['X-Pagination-Total-Count'];
            }

            $attachment_url = isset($data_each['featured_image'][$lang]['name']) ? self::$cms_img . '/' . $data_each['_id'] . '/' . $data_each['featured_image'][$lang]['name'] : '';
            if ($imageseo) {
                $attachment_url = isset($data_each['featured_image'][$lang]['file_md5_name']) ? self::$cms_img . '/' . $data_each['_id'] . '/' . $data_each['featured_image'][$lang]['file_md5_name'] : $attachment_url;
            }

            if ($forRoutes == true) {
                $array['featured_image'] = $attachment_url;
            } else {
                $array['featured_image'] = isset($attachment_url) && !empty($attachment_url) ? Cms::ResizeImage($attachment_url) : '';
            }

            $array['featured_image_seo_alt_desc'] = isset($data_each['featured_image'][$lang]['seo_alt_desc']) ? $data_each['featured_image'][$lang]['seo_alt_desc'] : '';
            $array['featured_image_seo_meta_title'] = isset($data_each['featured_image'][$lang]['seo_meta_title']) ? $data_each['featured_image'][$lang]['seo_meta_title'] : '';
            $array['content'] = isset($data_each['content'][$lang]) ? $data_each['content'][$lang] : '';
            $array['created_at'] = isset($data_each['created_at']) ? $data_each['created_at'] : '';
            $array['title'] = isset($data_each['title'][$lang]) ? $data_each['title'][$lang] : '';
            $array['slug'] = isset($data_each['slug'][$lang]) ? $data_each['slug'][$lang] : '';
            $array['slug_all'] = isset($data_each['slug']) ? $data_each['slug'] : '';
            $array['meta_title'] = isset($data_each['meta_title'][$lang]) ? $data_each['meta_title'][$lang] : '';
            $array['meta_desc'] = isset($data_each['meta_desc'][$lang]) ? $data_each['meta_desc'][$lang] : '';
            $array['meta_keywords'] = isset($data_each['meta_keywords'][$lang]) ? $data_each['meta_keywords'][$lang] : '';
            $array['custom_settings'] = isset($data_each['custom_settings']) ? $data_each['custom_settings'] : '';
            $array['categories'] = isset($data_each['categories']) ? $data_each['categories'] : [];
            $array['totalCount'] = isset($data_each['total_count']) ? $data_each['total_count'] : 0;
            $array['post_order'] = isset($data_each['post_order']) ? $data_each['post_order'] : 0;
            $ret_data[] = $array;
        }

        return $ret_data;
    }

    public static function getUsers()
    {
        self::initialize();
        $file = Functions::directory() . 'users_data.json';
        $url = self::$apiUrl . 'cms/users&user=' . self::$user;

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $data = json_decode($file_data, true);
        $users = [];

        foreach ($data['users'] as $user) {
            $users[] = [
                'name' => (isset($user['firstname']) ? $user['firstname'] : '') . ' ' . (isset($user['lastname']) ? $user['lastname'] : ''),
                'dp' => (isset($user['dp']) && $user['dp']) ? Cms::ResizeImage(self::$image_url_users . $user['_id'] . '/' . $user['dp'], 300, 'user') : '',
                'number_of_listing' => (isset($user['number_of_listing']) ? $user['number_of_listing'] : ''),
                'number_of_rent' => (isset($user['number_of_rent']) ? $user['number_of_rent'] : ''),
                'number_of_sales' => (isset($user['number_of_sales']) ? $user['number_of_sales'] : ''),
            ];
        }

        return $users;
    }

    public static function clean($string)
    {
        trigger_error(__METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }
}
