<?php

namespace Daxit\OptimaClass\Traits;

trait ConfigTrait
{
    protected static $rootUrl;
    protected static $apiUrl;
    protected static $site_id;
    protected static $user;
    protected static $commercial;
    protected static $cms_img;
    protected static $excluded_langs;
    protected static $ImageFrom;
    protected static $template;
    protected static $status;
    protected static $node_url;
    protected static $agency;
    protected static $property_img_resize_link;
    protected static $img_url_without_wm;
    protected static $com_img;
    protected static $api_key;
    protected static $dev_img;
    protected static $constructions_doc_url;
    protected static $floor_plans_url;
    protected static $img_url;
    protected static $from_email;
    protected static $replace_iso_code;
    protected static $send_error_mails_to;
    protected static $recaptcha_secret_site_key;
    protected static $mooring_img_url;
    protected static $rental_logic;
    protected static $rental_logic_week;
    protected static $rental_logic_day;
    protected static $exclude_per_stay_extras;
    protected static $img_url_wm;
    protected static $default_title;
    protected static $date_fromate;

    /**
     * Initialize configuration values.
    */
    protected static function initialize()
    {
        self::$rootUrl = config('params.rootUrl');
        self::$apiUrl = config('params.apiUrl');
        self::$site_id = config('params.site_id');
        self::$user = config('params.user');
        self::$commercial = config('params.commercial');
        self::$cms_img = config('params.cms_img');
        self::$excluded_langs = config('params.excluded_langs');
        self::$ImageFrom = config('params.ImageFrom');
        self::$template = config('params.template');
        self::$status = config('params.status',[]);
        self::$node_url = config('params.node_url');
        self::$agency = config('params.agency');
        self::$property_img_resize_link = config('params.property_img_resize_link');
        self::$img_url_without_wm = config('params.img_url_without_wm');
        self::$com_img = config('params.com_img');
        self::$api_key = config('params.api_key');
        self::$dev_img = config('params.dev_img');
        self::$constructions_doc_url = config('params.constructions_doc_url');
        self::$floor_plans_url = config('params.floor_plans_url');
        self::$img_url = config('params.img_url');
        self::$from_email = config('params.from_email');
        self::$replace_iso_code = config('params.replace_iso_code', []);
        self::$send_error_mails_to = config('params.send_error_mails_to');
        self::$recaptcha_secret_site_key = config('params.recaptcha_secret_site_key');
        self::$mooring_img_url = config('params.mooring_img_url');
        self::$rental_logic = config('params.rental_logic');
        self::$rental_logic_week = config('params.rental_logic_week');
        self::$rental_logic_day = config('params.rental_logic_day');
        self::$exclude_per_stay_extras = config('params.exclude_per_stay_extras');
        self::$img_url_wm = config('params.img_url_wm');
        self::$default_title = config('params.default_title');
        self::$date_fromate = config('params.date_fromate');
    }
}