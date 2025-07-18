<?php

namespace Daxit\OptimaClass\Helpers;

use DateTime;
use DateTimeZone;
use Daxit\OptimaClass\Components\Translate;
use Daxit\OptimaClass\Traits\ConfigTrait;
use Daxit\OptimaClass\Helpers\Functions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;

class CommercialProperties
{
    use ConfigTrait;

    public static function findAll($page = 1, $page_size = 10, $query = '', $sort = ['current_price' => '-1'], $set_options = [])
    {
        self::initialize();
        $query_array = [];
        $options = ["page" => $page, "limit" => $page_size];
        $options['populate'] = [
            [
                'path' => 'property_attachments',
                'match' => ['document' => ['$ne' => true], 'publish_status' => ['$ne' => false]],
            ]
        ];

        $get = Functions::mergeRequest( $_GET ?? []);

        if (Request::has('orderby') && is_array(Request::get('orderby')) && count(Request::get('orderby')) == 2) {
            $sort = [Request::get('orderby')[0] => Request::get('orderby')[1]];
        }

        $options['sort'] = $sort;

        if (isset($get['favorite_ids']) && !empty($get['favorite_ids'])) {
            $query_array["archived"] = [
                '$ne' => true
            ];
            $query_array["reference"] =
                [
                    '$in' => $get['favorite_ids'],
                ];
        }

        if (isset($query) && $query != '' && is_array($query)) {
            if (!count($query)) {
                $query = self::setQuery();
            }
            if (count($query)) {
                $query_array = $query;
                $query_array['status'] = ['$in' => (isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : (isset(self::$status) && !empty(self::$status) ? self::$status : ['Available', 'Under Offer']))];
            }
        }

        $post_data = ["options" => $options];
        if (!empty($query_array)) {
            $post_data["query"] =  $query_array;
        }

        if ((isset($get['rental_price_from']) && !empty($get['rental_price_from'])) || (isset($get['rental_price_to']) && !empty($get['rental_price_to']))) {
            $post_data['selectRecords'] = false;
        }

        $random_query = isset($get['random']) && !empty($get['random']) ? '&random=' . $get['random'] : '';
        $node_url = self::$node_url . 'commercial_properties?user=' . self::$user . $random_query;

        if (isset($set_options['cache']) && $set_options['cache'] == true) {
            $response = self::DoCache($post_data, $node_url);
        } else {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data)),
                'Cache-Control' => 'no-cache'
            ])->post($node_url, $post_data);
        }

        $response = $response->json();

        $properties = [];

        if (isset($response) && isset($response['docs'])) {
            foreach ($response['docs'] as $property) {
                $properties[] = self::formateProperty($property, $set_options);
            }
            $response['docs'] = $properties;
        }

        return $response;
    }

    public static function findOne($id, $set_options = [])
    {
        self::initialize();
        $options = [];
        $options['populate'] = [
            [
                'path' => 'property_attachments',
                'match' => ['document' => ['$ne' => true], 'publish_status' => ['$ne' => false]]
            ]
        ];

        $post_data = ['options' => $options];

        $headers = [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ];

        if (!isset($headers['x-forwarded-for']) && ($clientIp = Request::ip())) {
            $headers['x-forwarded-for'] = $clientIp;
        }

        $response = Http::withHeaders($headers)->post(self::$node_url . 'commercial_properties/view/' . $id . '?user=' . self::$user, $post_data);

        $response = $response->json();

        $property = self::formateProperty($response, $set_options);

        return $property;
    }

    public static function setQuery()
    {
        $get = Functions::mergeRequest( $_GET ?? []);

        $query = [];
        if (isset($get['auction_price_from']) && !empty($get['auction_price_from']) || isset($get['auction_price_to']) && !empty($get['auction_price_to'])) {
            $query['starting_price'] = ['$gte' => (int) $get['auction_price_from'], '$lte' => isset($get['auction_price_to']) && !empty($get['auction_price_to']) ?  (int) $get['auction_price_to'] : ''];
        } elseif (isset($get['price_from']) && !empty($get['price_from']) || isset($get['price_to']) && !empty($get['price_to'])) {
            $current_price = [];

            if(isset($get['price_from']) && !empty($get['price_from'])){
                $current_price['$gte'] = (int) $get['price_from'];
            }

            if(isset($get['price_to']) && !empty($get['price_to'])){
                $current_price['$lte'] = (int) $get['price_to'];
            }

            $query['current_price'] = $current_price;
        }

        if (isset($get['reference']) && !empty($get['reference'])) {
            $query['$or'] = [
                ["reference" => (int) $get['reference']],
                ["other_reference" => ['$regex' => ".*" . $get['reference'] . ".*", '$options' => "i"]],
                ["external_reference" => ['$regex' => ".*" . $get['reference'] . ".*", '$options' => "i"]]
            ];
        } else {
            // only_similar (only similar/with their units), exclude_similar (one per group + all not part of group), include_similar (all properties)
            if(isset($get['similar_commercials']) && !empty($get['similar_commercials'])) {
                $query['similar_commercials'] = $get['similar_commercials'];
            } else {
                $query['similar_commercials'] = config('params.similar_commercials', 'include_similar');
            }
        }

        if(isset($get['project']) && !empty($get['project']) && isset($get['resale']) && !empty($get['resale'])){
            $query['$and'] = [['$or' => [['project' => true], ['categories.new_construction' => true], ['categories.resale' => true]]]];
        }else{
            if (isset($get['project']) && !empty($get['project'])) {
                // $query['$and'] = [['project' => ['$exists' => true]], ["project" => (bool) $get['project']]]; //change --25-01-27
                $query['$and'] = [['$or' => [['project' => true], ['categories.new_construction' => true]]]];
            }

            if (isset($get['resale']) && !empty($get['resale'])) {
                // $query['$and'] = array_merge($query['$and'] ?? [], [['project' => ['$ne' => true]]]); //change --25-01-27
                $query['$and'] = array_merge($query['$and'] ?? [], [['$or' => [['$and' => [['project' => ['$ne' => true]],['categories.new_construction' => false]]],['categories.resale' => true]]]]);
            }
        }

        if (isset($get['project_on']) && !empty($get['project_on'])) {
            $query['$or'] = array_merge($query['$or'] ?? [], [["own" => false], ['own' => ['$exists' => false]]]);
        }

        if (isset($get['own']) && !empty($get['own']) && $get['own']) {
            $query['own'] = $get['own'];
        }

        if (isset($get['prop_ids']) && !empty($get['prop_ids'])) {
            $prop_ids = $get['prop_ids'] != '' ? $get['prop_ids'] : [];
            $prop_ids = explode(',', $prop_ids);
            $query['_id'] = ['$in' =>  $prop_ids];
        }

        if (isset($get['type']) && !empty($get['type']) && is_array($get['type']) && count($get['type']) > 0 && $get['type'][0] != 0 && $get['type'][0] != '' && $get['type'][0] != '0') {
            $intArray = array();
            foreach ($get['type'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['type_one'] = ['$in' => $intArray];
        }

        if (isset($get['sub_type']) && !empty($get['sub_type']) && is_array($get['sub_type']) && count($get['sub_type']) > 0 && $get['sub_type'][0] != 0 && $get['sub_type'][0] != '' && $get['sub_type'][0] != '0') {
            $intArray = array();
            foreach ($get['sub_type'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['type_two'] = ['$in' => $intArray];
        }

        if (isset($get['set_types_for_or_query']) && !empty($get['set_types_for_or_query'])) {
            $query['set_types_for_or_query'] = true;
        }

        if (isset($get['price_on_demand']) && !empty($get['price_on_demand'])) {
            $query['$or'][]['price_on_demand'] = ['$exists' => (int) 1];
            $query['$or'][]['price_on_demand'] = ['$exists' => (int) 0];
        }

        if ((isset($get['rental_price_from']) && !empty($get['rental_price_from'])) || (isset($get['rental_price_to']) && !empty($get['rental_price_to']))) {
            $query['rental_seasons_price'] = true;
        }

        if (isset($get['rental_price_from']) && $get['rental_price_from'] != '') {
            $query['rental_seasons_price_from'] = (int) $get['rental_price_from'];
        }

        if (isset($get['rental_price_to']) && $get['rental_price_to'] != '') {
            $query['rental_seasons_price_to'] = (int) $get['rental_price_to'];
        }

        if ((isset($get['period_seasons_price_from']) && !empty($get['period_seasons_price_from'])) || (isset($get['period_seasons_price_to']) && !empty($get['period_seasons_price_to']))) {
            $query['period_seasons_price'] = true;
        }
        if (isset($get['period_seasons_price_from']) && $get['period_seasons_price_from'] != '') {
            $query['period_seasons_price_from'] = (int) $get['period_seasons_price_from'];
        }
        if (isset($get['period_seasons_price_to']) && $get['period_seasons_price_to'] != '') {
            $query['period_seasons_price_to'] = (int) $get['period_seasons_price_to'];
        }

        if (isset($get['auction']) && !empty($get['auction'])) {
            $query['auction_tab'] = true;
        }

        if (isset($get['show_on']) && !empty($get['show_on']) && !isset($get['latLang'])) {
            $query['show_on'] = ['$in' => $get['show_on']];
        }

        if (isset($get['show_on']) && !empty($get['show_on']) && isset($get['latLang']) && !empty($get['latLang'])) {
            $query['basic_info_object.' . self::$agency . '.show_on'] = ['$in' => $get['show_on']];
        }

        if (isset($get['status']) && !empty($get['status'])) {
            $query['status'] = ['$in' => $get['status']];
        }

        if (isset($get['auction_featured']) && !empty($get['auction_featured'])) {
            $query['auction_featured'] = 1;
        }

        if (isset($get['auction_latlng']) && !empty($get['auction_latlng'])) {
            $query['auction_tab'] = true;
        }

        if (isset($get['auction_office']) && !empty($get['auction_office'])) {
            $query['auction_office'] = true;
        }

        if (isset($get['auction_end_date']) && !empty($get['auction_end_date'])) {
            $query['auction_end_date'] = ['$gte' => $get['auction_end_date']];
        }

        if (isset($get['booking_created_to']) && !empty($get['booking_created_to'])) {
            $query['booking_created_to'] = $get['booking_created_to'];
        }

        if (isset($get['booking_created_from']) && !empty($get['booking_created_from'])) {
            $query['booking_created_from'] = $get['booking_created_from'];
        }

        if (isset($get['sleeps']) && !empty($get['sleeps'])) {
            $query['sleeps'] = $get['sleeps'];
        }

        if (isset($get['min_sleeps']) && !empty($get['min_sleeps'])) {
            $query['sleeps'] = ['$lte' => (int)$get['min_sleeps']];
        } elseif (isset($get['max_sleeps']) && !empty($get['max_sleeps'])) {
            $query['sleeps'] = ['$gte' => (int)$get['max_sleeps']];
        }

        if (isset($get['search_by_property_name']) && !empty($get['search_by_property_name'])) {
            $query['search_by_property_name'] = $get['search_by_property_name'];
        }

        if (isset($get['lang']) && !empty($get['lang'])) {
            $query['current_lang'] = $get['lang'];
        }

        if (isset($get['min_built']) && !empty($get['min_built'])) {
            $query['built'] = ['$lte' => (int)$get['min_built']];
        } elseif (isset($get['max_built']) && !empty($get['max_built'])) {
            $query['built'] = ['$gte' => (int)$get['max_built']];
        }

        if (isset($get['min_plot']) && !empty($get['min_plot'])) {
            $query['plot'] = ['$lte' => (int)$get['min_plot']];
        } elseif (isset($get['max_plot']) && !empty($get['max_plot'])) {
            $query['plot'] = ['$gte' => (int)$get['max_plot']];
        }

        if (isset($get['categories']) && !empty(array_filter($get['categories']))) {
            $intArray = array();
            foreach ($get['categories'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['shared_categories'] = ['$in' => $intArray];
        }

        if (isset($get['custom_categories']) && !empty(array_filter($get['custom_categories']))) {
            $intArray = array();
            foreach ($get['custom_categories'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['custom_categories'] = ['$nin' => $intArray];
        }

        if (isset($get['country']) && !empty($get['country'])) {
            $query['country'] = (int) $get['country'];
        }

        if (isset($get['city']) && !empty(array_filter($get['city']))) {
            $intArray = array();
            foreach ($get['city'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['city'] = ['$in' => $intArray];
        }

        if (isset($get['location']) && !empty(array_filter($get['location']))) {
            $intArray = array();
            foreach ($get['location'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['location'] = ['$in' => $intArray];
        }

        if (isset($get['lg_by_key']) && !empty(array_filter($get['lg_by_key']))) {
            $intArray = array();
            foreach ($get['lg_by_key'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['lg_by_key'] = ['$in' => $intArray];
        }

        if (isset($get['province']) && !empty(array_filter($get['province']))) {
            $intArray = array();
            foreach ($get['province'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $query['province'] = ['$in' => $intArray];
        }

        if (isset($get['cp_features']) && !empty(array_filter($get['cp_features']))) {
            foreach ($_GET['cp_features'] as $features) {
                if (isset($features) && count($features) > 1) {
                    $group_feature['$or'] = $features;
                    $search_feature[] = $group_feature;
                } else {
                    if (!empty($features)) {
                        foreach ($features as $feature) {
                            $search_feature[] = $feature;
                        }
                    }
                }
            }
            $query['$and'] = array_merge($query['$and'] ?? [], $search_feature);
        }

        if (isset($get['sale']) && !empty($get['sale'])) {
            $query['sale'] = true;
        }

        if (isset($get['rent']) && !empty($get['rent'])) {
            $query['rent'] = true;
        }

        if (isset($get['lt_rental']) && !empty($get['lt_rental'])) {
            $query['lt_rental'] = true;
        }

        if (isset($get['st_rental']) && !empty($get['st_rental'])) {
            $query['st_rental'] = true;
        }

        if (isset($get['bedrooms']) && !empty($get['bedrooms'])) {
            $query['bedrooms'] = $get['bedrooms'];
        }

        if (isset($get['min_bed']) && !empty($get['min_bed'])) {
            $query['bedrooms'] = ['$lte' => (int)$get['min_bed']];
        } elseif (isset($get['max_bed']) && !empty($get['max_bed'])) {
            $query['bedrooms'] = ['$gte' => (int)$get['max_bed']];
        }

        if (isset($get['bathrooms']) && $get['bathrooms']) {
            $query['bathrooms'] = $get['bathrooms'];
        }

        if (isset($get['min_bath']) && !empty($get['min_bath'])) {
            $query['bathrooms'] = ['$lte' => (int)$get['min_bath']];
        } elseif (isset($get['max_bath']) && !empty($get['max_bath'])) {
            $query['bathrooms'] = ['$gte' => (int)$get['max_bath']];
        }

        if (isset($get['new_built']) && !empty($get['new_built'])) {
            $query['project'] = true;
        }

        if (isset($get['hairdryer']) && !empty($get['hairdryer'])) {
            $query['hairdryer'] = true;
        }

        if (isset($get['region']) && !empty($get['region'])) {
            $query['region'] = (int) $get['region'];
        }

        $query['archived']['$ne'] = true;

        if (isset($get['featured']) && !empty($get['featured'])) {
            $query['featured'] = true;
        }

        if (isset($get['office']) && !empty($get['office'])) {
            $query['offices'] = ['$in' => $get['office']];
        }
        if (isset($get['remove_count']) && $get['remove_count']) {
            $query['remove_count'] = $get['remove_count'];
        }
        if (isset($get["listing_agent"]) && !empty(array_filter($get["listing_agent"]))) {
            $intArray = array();
            foreach ($get['listing_agent'] as $int_val) {
                $intArray[] = $int_val;
            }
            $query['listing_agent'] = ['$in' => $intArray];
        }

        // only_similar (only similar/with their units), exclude_similar (one per group + all not part of group), include_similar (all properties)
        // if(isset($get['similar_commercials']) && !empty($get['similar_commercials'])) {
        //     $query['similar_commercials'] = $get['similar_commercials'];
        // } else {
        //     $query['similar_commercials'] = config('params.similar_commercials', 'include_similar');
        // }

        return $query;
    }

    public static function formateProperty($property, $set_options = [])
    {
        self::initialize();
        $agency_data = self::getAgency();
        $settings = Cms::settings();
        $lang = strtoupper(App::getLocale());
        $get = Functions::mergeRequest( $_GET ?? []);
        $contentLang = strtolower(App::getLocale());
        $cmsLang = self::$replace_iso_code;
        $image_label = array (0 => "unknown",1 => "bathroom",2 => "kitchen",3 => "details",4 => "bedroom",5 => "facade",6 => "garage",7 => "garden",8 => "plan",9 => "living",10 => "terrace",11 => "views",12 => "pool",13 => "waitingroom",14 => "hall",15 => "entrance/exit",16 => "room",17 => "communalareas",18 => "reception",19 => "storage",20 => "archive",21 => "warehouse",22 => "mates",23 => "mooring",24 => "land",25 => "parking");

        if (strtolower(App::getLocale()) == 'es') {
            $contentLang = 'es_AR';
        }

        if (array_key_exists(strtolower($lang), $cmsLang)) {
            $contentLang = $cmsLang[strtolower($lang)] ?? '';
            $lang = strtoupper($contentLang);
        }

        if (strtolower(App::getLocale()) == 'po') {
            $contentLang = 'pl';
            $lang = strtoupper("pl");
        }

        $f_property = [];

        if (isset($property['external_reference']) && !empty($property['external_reference'])) {
            $f_property['other_reference'] = $property['external_reference']; // this is due to a historical change
        }

        if (isset($settings['general_settings']['reference']) && $settings['general_settings']['reference'] != 'reference') {
            $ref = $settings['general_settings']['reference'];
            $reference = isset($property['reference']) && !empty($property['reference']) ? $property['reference'] : '';
            
            if(isset($property[$ref]) && !empty($property[$ref]) ) {
                if($ref == "external_reference"){
                    $f_property['reference'] = isset($property['other_reference']) && !empty($property['other_reference']) ? $property['other_reference'] : $reference; // this is due to a historical change
                } else if($ref == 'agency_reference') {
                    $f_property['reference'] = isset($property['external_reference']) && !empty($property['external_reference']) ? $property['external_reference'] : $reference; // this is due to a historical change
                } else {
                    $f_property['reference'] = isset($property[$ref]) && !empty($property[$ref]) ? $property[$ref] : $reference;
                }
            } else if($ref == 'agency_reference') {
                $f_property['reference'] = isset($property['external_reference']) && !empty($property['external_reference']) ? $property['external_reference'] : $reference; // this is due to a historical change
            } else if($ref == "external_reference") {
                $f_property['reference'] = isset($property['other_reference']) && !empty($property['other_reference']) ? $property['other_reference'] : $reference; // this is due to a historical change
            } else {
                $f_property['reference'] = $reference;
            }
        } else {
            $f_property['reference'] = isset($property['reference']) && !empty($property['reference']) ? $property['reference'] : '';
        }

        if (isset($property['other_reference']) && !empty($property['other_reference'])) {
            $f_property['external_reference'] = $property['other_reference']; // this is due to a historical change
        }

        $agency = '';
        if (isset($property['agency']) && !empty($property['agency'])) {
            $agency = $property['agency'];
            $f_property['agency'] = $property['agency'];
        }

        if (isset($property['from_residential']) && !empty($property['from_residential'])) {
            $f_property['from_residential'] = $property['from_residential'];
        }

        if (isset($property['shared_categories']) && !empty($property['shared_categories'])) {
            $f_property['shared_categories'] = $property['shared_categories'];
        }

        if (isset($property['property_name']) && !empty($property['property_name'])) {
            $f_property['property_name'] = $property['property_name'];
        }

        if (isset($property['own']) && !empty($property['own'])) {
            $f_property['own'] = $property['own'];
        }

        if (isset($property['price_on_demand'])) {
            $f_property['price_on_demand'] = $property['price_on_demand'];
        }

        if (isset($property['agency_data']['commercial_name']) && !empty($property['agency_data']['commercial_name'])) {
            $f_property['agency_name'] = $property['agency_data']['commercial_name'];
        }

        if (isset($property['listing_agency_data']['commercial_name']) && !empty($property['listing_agency_data']['commercial_name'])) {
            $f_property['agency_name'] = $property['listing_agency_data']['commercial_name'];
        }

        if (isset($property['agency_data']['agency_email']) && !empty($property['agency_data']['agency_email'])) {
            $f_property['agency_email'] = $property['agency_data']['agency_email'];
        }

        if (isset($property['listing_agency_data']['agency_email']) && !empty($property['listing_agency_data']['agency_email'])) {
            $f_property['agency_email'] = $property['listing_agency_data']['agency_email'];
        }

        if (isset($property['private_info_object'][$agency]['cadastral_numbers'][0]['cadastral_number']) && !empty($property['private_info_object'][$agency]['cadastral_numbers'][0]['cadastral_number'])) {
            $f_property['cadastral_number'] = $property['private_info_object'][$agency]['cadastral_numbers'][0]['cadastral_number'];
        }

        if (isset($property['_id'])) {
            $f_property['_id'] = $property['_id'];
        }

        if (isset($property['reference'])) {
            $f_property['id'] = $property['reference'];
        }

        if (isset($property['title'][$lang]) && $property['title'][$lang] != '') {
            $f_property['sale_title'] = $property['title'][$lang];
        } elseif (isset($property['shared_data']['title'][$lang]) && $property['shared_data']['title'][$lang] != '') {
            $f_property['sale_title'] = $property['shared_data']['title'][$lang];
        } else {
            $f_property['sale_title'] = (isset($property['property_type_one']['value'][$contentLang]) ? Translate::t($property['property_type_one']['value'][$contentLang]) : '') . ' ' . (isset($property['property_location']['value'][$contentLang]) ? Translate::t('in') . ' ' . Translate::t($property['property_location']['value'][$contentLang]) : '');
        }

        if (isset($property['description'][$lang]) && $property['description'][$lang] != '') {
            $f_property['sale_description'] = $property['description'][$lang];
        } elseif (isset($property['shared_data']['description'][$lang]) && $property['shared_data']['description'][$lang] != ''  && (isset($property['agency']) && $property['agency'] != self::$agency) && (isset($property['mls']) && $property['mls'] == 1)) {
            $f_property['sale_description'] = $property['shared_data']['description'][$lang];
        }

        if (self::$agency == '6110fa9b8334050aac21e779') { // For ImmoMarket
            if (isset($property['rental_title'][$lang]) && $property['rental_title'][$lang] != '') {
                $f_property['rent_title'] = $property['rental_title'][$lang];
            } elseif (isset($property['shared_data']['rental_external_title'][$lang]) && $property['shared_data']['rental_external_title'][$lang] != '' && isset($property['agency']) && $property['agency'] != self::$agency) {
                $f_property['rent_title'] = $property['shared_data']['rental_external_title'][$lang];
            } else {
                $f_property['rent_title'] = (isset($property['property_type_one']['value'][$contentLang]) ? Translate::t($property['property_type_one']['value'][$contentLang]) : '') . ' ' . (isset($property['property_location']['value'][$contentLang]) ? Translate::t('in') . ' ' . Translate::t($property['property_location']['value'][$contentLang]) : '');
            }
            if (isset($property['rental_description'][$lang]) && $property['rental_description'][$lang] != '') {
                $f_property['rent_description'] = $property['rental_description'][$lang];
            } elseif (isset($property['shared_data']['rental_external_description'][$lang]) && $property['shared_data']['rental_external_description'][$lang] != '' && isset($property['agency']) && $property['agency'] != self::$agency) {
                $f_property['rent_description'] = $property['shared_data']['rental_external_description'][$lang];
            }
        } else { // For Other Sites
            if (isset($property['rental_title'][$lang]) && $property['rental_title'][$lang] != '') {
                $f_property['rent_title'] = $property['rental_title'][$lang];
            } elseif (isset($property['shared_data']['rental_external_title'][$lang]) && $property['shared_data']['rental_external_title'][$lang] != '' && (isset($property['agency']) && $property['agency'] != self::$agency) && (isset($property['mls']) && $property['mls'] == 1)) {
                $f_property['rent_title'] = $property['shared_data']['rental_external_title'][$lang];
            } else {
                $f_property['rent_title'] = (isset($property['property_type_one']['value'][$contentLang]) ? Translate::t($property['property_type_one']['value'][$contentLang]) : '') . ' ' . (isset($property['property_location']['value'][$contentLang]) ? Translate::t('in') . ' ' . Translate::t($property['property_location']['value'][$contentLang]) : '');
            }
            if (isset($property['rental_description'][$lang]) && $property['rental_description'][$lang] != '') {
                $f_property['rent_description'] = $property['rental_description'][$lang];
            } elseif (isset($property['shared_data']['rental_external_description'][$lang]) && $property['shared_data']['rental_external_description'][$lang] != '' && (isset($property['agency']) && $property['agency'] != self::$agency) && (isset($property['mls']) && $property['mls'] == 1)) {
                $f_property['rent_description'] = $property['shared_data']['rental_external_description'][$lang];
            }
        }

        if (isset($property['status'])) {
            $f_property['status'] = Translate::t($property['status']);
        }
        if (isset($property['agency_data']['logo']['name']) && !empty($property['agency_data']['logo']['name'])) {
            $f_property['agency_logo'] = 'https://images.optima-crm.com/agencies/' . (isset($property['agency_data']['_id']) ? $property['agency_data']['_id'] : '') . '/' . (isset($property['agency_data']['logo']['name']) ? $property['agency_data']['logo']['name'] : '');
        }

        if (isset($property['listing_agency_data']['logo']['name']) && !empty($property['listing_agency_data']['logo']['name'])) {
            $f_property['agency_logo'] = 'https://images.optima-crm.com/companies/' . (isset($property['listing_agency_data']['_id']) ? $property['listing_agency_data']['_id'] : '') . '/' . (isset($property['listing_agency_data']['logo']['name']) ? $property['listing_agency_data']['logo']['name'] : '');
            $f_property['compnay_id'] = isset($property['listing_agency_data']['_id']) ? $property['listing_agency_data']['_id'] : '';
        }

        if (isset($property['seo_title'][$lang]) && $property['seo_title'][$lang] != '') {
            $f_property['meta_title'] = $property['seo_title'][$lang];
        }

        if (isset($property['seo_description'][$lang]) && $property['seo_description'][$lang] != '') {
            $f_property['meta_desc'] = $property['seo_description'][$lang];
        }

        if (isset($property['rental_seo_title'][$lang]) && $property['rental_seo_title'][$lang] != '') {
            $f_property['rental_meta_title'] = $property['rental_seo_title'][$lang];
        }

        if (isset($property['rental_seo_description'][$lang]) && $property['rental_seo_description'][$lang] != '') {
            $f_property['rental_meta_desc'] = $property['rental_seo_description'][$lang];
        }

        if (isset($property['rental_keywords'][$lang]) && $property['rental_keywords'][$lang] != '') {
            $f_property['rental_meta_keywords'] = $property['rental_keywords'][$lang];
        }

        if (isset($property['keywords'][$lang]) && $property['keywords'][$lang] != '') {
            $f_property['meta_keywords'] = $property['keywords'][$lang];
        }

        if (isset($property['property_urls']) && !empty($property['property_urls'])) {
            $f_property['urls'] =  $property['property_urls'];
        }

        if (isset($property['urls_without_domain']) && !empty($property['urls_without_domain'])) {
            $f_property['property_url'] = $property['urls_without_domain'];
        }

        if (isset($property['videos']) && !empty($property['videos'])) {
            $videos = [];
            $virtual_tours = [];
            $floor_plan = [];
            $link_to_auction = [];

            foreach ($property['videos'] as $video) {
                if (isset($video['type']) && $video['type'] == 'Video' && isset($video['status']) && $video['status'] == 1) {
                    $videos[] = (isset($video['url'][strtoupper(App::getLocale())]) ? $video['url'][strtoupper(App::getLocale())] : '');
                }
            }

            $f_property['videos'] = $videos;
            foreach ($property['videos'] as $vt) {
                if (isset($vt['type']) && $vt['type'] == '2' && isset($vt['status']) && $vt['status'] == 1) {
                    $virtual_tours['url'] = (isset($vt['url'][strtoupper(App::getLocale())]) ? $vt['url'][strtoupper(App::getLocale())] : '');
                    $virtual_tours['description'] = (isset($vt['description'][strtoupper(App::getLocale())]) ? $vt['description'][strtoupper(App::getLocale())] : '');
                } elseif (isset($vt['type']) && $vt['type'] == '112' && isset($vt['status']) && $vt['status'] == 1) {
                    $link_to_auction['link'] = (isset($vt['url'][strtoupper(App::getLocale())]) ? $vt['url'][strtoupper(App::getLocale())] : '');
                    $link_to_auction['status'] = (isset($vt['status']) ? $vt['status'] : '');
                }
                if (isset($vt['type']) && $vt['type'] == 'FP' && isset($vt['status']) && $vt['status'] == 1) {
                    $floor_plan[] = (isset($vt['url'][strtoupper(App::getLocale())]) ? $vt['url'][strtoupper(App::getLocale())] : '');
                }
            }

            $f_property['vt'] = $virtual_tours;
            $f_property['fp'] = $floor_plan;
            $f_property['link_to_auction'] = $link_to_auction;
        }

        if (isset($property['created_at']) && !empty($property['created_at'])) {
            $f_property['created_at'] = is_numeric($property['created_at']) ? $property['created_at'] : strtotime($property['created_at']);
        }

        if (isset($property['updated_at']) && !empty($property['updated_at'])) {
            $f_property['updated_at'] = is_numeric($property['updated_at']) ? $property['updated_at'] : strtotime($property['updated_at']);
        }

        if (isset($property['featured'])) {
            $f_property['featured'] = $property['featured'];
        }

        if (isset($property['type_one'])) {
            $f_property['type'] = Translate::t($property['type_one']);
        }

        if (isset($property['type_one_value'][$contentLang])) {
            $f_property['type_one'] = Translate::t($property['type_one_value'][$contentLang]);
        }

        if (isset($property['type_two'])) {
            $f_property['type_two_key'] = Translate::t($property['type_two']);
        }

        if (isset($property['type_two_value'][$contentLang])) {
            $f_property['type_two'] = Translate::t($property['type_two_value'][$contentLang]);
        }

        if (isset($property['address']['formatted_address'])) {
            $f_property['address'] = $property['address']['formatted_address'];
        }

        if (isset($property['street'])) {
            $f_property['street'] = $property['street'];
        }

        if (isset($property['street_number'])) {
            $f_property['street_number'] = $property['street_number'];
        }

        if (isset($property['postal_code'])) {
            $f_property['postal_code'] = $property['postal_code'];
        }

        if (isset($property['cadastral_numbers'])) {
            $f_property['cadastral_numbers'] = $property['cadastral_numbers'];
        }

        if (isset($property['project'])) {
            $f_property['project'] = $property['project'];
        }

        if (isset($property['country'])) {
            $f_property['country'] = $property['country'];
        }

        if (isset($property['latitude_alt']) && isset($property['longitude_alt']) && $property['latitude_alt'] != '' && $property['longitude_alt'] != '') {
            $f_property['lat'] = $property['latitude_alt'];
            $f_property['lng'] = $property['longitude_alt'];
        } elseif (isset($property['latitude']) && isset($property['longitude']) && $property['latitude'] != '' && $property['longitude'] != '') {
            $f_property['lat'] = $property['latitude'];
            $f_property['lng'] = $property['longitude'];
        } elseif (isset($property['address']['lat']) && isset($property['address']['lng']) && $property['address']['lat'] != '' && $property['address']['lng'] != '') {
            $f_property['lat'] = $property['address']['lat'];
            $f_property['lng'] = $property['address']['lng'];
        } elseif (isset($property['private_info_object'][self::$agency]['latitude']) && !empty($property['private_info_object'][self::$agency]['latitude'])) {
            $f_property['lat'] = isset($property['private_info_object'][self::$agency]['latitude']) ? $property['private_info_object'][self::$agency]['latitude'] : '';
            $f_property['lng'] = isset($property['private_info_object'][self::$agency]['longitude']) ? $property['private_info_object'][self::$agency]['longitude'] : '';
        } elseif (isset($property['property_location']['latitude']) && isset($property['property_location']['longitude']) && $property['property_location']['latitude'] != '' && $property['property_location']['longitude'] != '') {
            $f_property['lat'] = $property['property_location']['latitude'];
            $f_property['lng'] = $property['property_location']['longitude'];
        }

        if (isset($property['sale']) && $property['sale']) {
            $f_property['sale'] = TRUE;
        }

        if (isset($property['rent']) && $property['rent']) {
            $f_property['rent'] = TRUE;
        }

        if (isset($property['auction_tab']) && $property['auction_tab']) {
            $f_property['auction'] = TRUE;
        }

        if (isset($property['transfer']) && $property['transfer']) {
            $f_property['transfer'] = TRUE;
        }

        if (isset($property['leasehold']) && $property['leasehold']) {
            $f_property['leasehold'] = $property['leasehold'];
        }

        if (isset($property['leasehold_rental_price']) && $property['leasehold_rental_price']) {
            $f_property['leasehold_rental_price'] = $property['leasehold_rental_price'];
        }

        if (isset($property['leasehold_rental_unit']) && $property['leasehold_rental_unit']) {
            $f_property['leasehold_rental_unit'] = $property['leasehold_rental_unit'];
        }

        if (isset($property['period_seasons']) && !empty($property['period_seasons']) && count($property['period_seasons']) > 0) {
            $f_property['rental_season_data'] = $property['period_seasons'];
        }

        if (isset($property['rental_seasons']) && !empty($property['rental_seasons']) && count($property['rental_seasons']) > 0) {
            $f_property['rental_seasons'] = $property['rental_seasons'];
        }

        if (isset($property['leasehold_unit']) && $property['leasehold_unit']) {
            $f_property['leasehold_unit'] = $property['leasehold_unit'];
        }

        if (isset($property['bedrooms']) && $property['bedrooms'] > 0) {
            $f_property['bedrooms'] = $property['bedrooms'];
        }

        if (isset($property['bathrooms']) && $property['bathrooms'] > 0) {
            $f_property['bathrooms'] = $property['bathrooms'];
        }

        if (isset($property['city'])) {
            $f_property['city_key'] = $property['city'];
        }

        if (isset($property['property_city']['value'][$contentLang])) {
            $f_property['city'] = $property['property_city']['value'][$contentLang];
        } elseif (isset($property['property_city']['value']['en'])) {
            $f_property['city'] = $property['property_city']['value']['en'];
        }

        if (isset($property['province_value'][$contentLang])) {
            $f_property['province'] = $property['province_value'][$contentLang];
        }

        if (isset($property['location'])) {
            $f_property['location_key'] = $property['location'];
        }

        if (isset($property['property_location']['value'][$contentLang])) {
            $f_property['location'] = $property['property_location']['value'][$contentLang];
        } elseif (isset($property['property_location']['value']['en'])) {
            $f_property['location'] = $property['property_location']['value']['en'];
        }

        if (isset($property['type_one_key'])) {
            $f_property['type_key'] = $property['type_one_key'];
        }

        if (isset($property['current_price'])) {
            $f_property['price'] = $property['current_price'];
        }

        if (isset($property['old_price'])) {
            $f_property['old_price'] = $property['old_price'];
        }

        if (isset($property['starting_price']) && !empty($property['starting_price'])) {
            $f_property['auction_price'] = $property['starting_price'];
        }

        if (isset($property['auction_start_date']) && !empty($property['auction_start_date'])) {
            $f_property['auction_start_date'] = $property['auction_start_date'];
        }

        if (isset($property['auction_end_date']) && !empty($property['auction_end_date'])) {
            $f_property['auction_end_date'] = $property['auction_end_date'];
        }

        if (isset($property['start_time']) && !empty($property['start_time'])) {
            $f_property['start_time'] = $property['start_time'];
        }

        if (isset($property['end_time']) && !empty($property['end_time'])) {
            $f_property['end_time'] = $property['end_time'];
        }

        if (isset($property['currency'])) {
            $f_property['currency'] = $property['currency'];
        }

        if (isset($property['property_attachments']) && count($property['property_attachments']) > 0 && !isset($property['from_residential'])) {
            $attachments = [];
            $attachments_alt = [];
            $attachments_label = [];
            foreach ($property['property_attachments'] as $pic) {
                if (isset($pic["publish_status"]) && !empty($pic["publish_status"])) {
                    if(isset($pic['document']) && $pic['document'] != 1 && isset($agency_data['watermark_image']['show_onweb']) && $agency_data['watermark_image']['show_onweb'] == 1) {
                        $image_size = isset($set_options['image_size']) && !empty($set_options['image_size']) ? $set_options['image_size'] : 1200;
                        $wm_size = isset($set_options['wm_size']) && !empty($set_options['wm_size']) ? $set_options['wm_size'] : 100;
                        $attachments[] = self::$com_img . '/' . $agency . '/' . $wm_size . '/' . $pic['model_id'] . '/' . $image_size . '/' . $pic['file_md5_name'];
                    }elseif (isset($pic['document']) && $pic['document'] != 1 && isset($set_options['image_size']) && !empty($set_options['image_size'])) {
                        $attachments[] = self::$property_img_resize_link . '/' . $pic['model_id'] . '/' . $set_options['image_size'] . '/' .  urldecode($pic['file_md5_name']);
                    } elseif (isset($pic['document']) && $pic['document'] != 1) {
                        $attachments[] = self::$com_img . '/' . $pic['model_id'] . '/' .  urldecode($pic['file_md5_name']);
                    }
                    if (isset($pic['document']) && $pic['document'] != 1 && isset($pic['alt_description'][$lang]) && !empty($pic['alt_description'][$lang])) {
                        $attachments_alt[] = $pic['alt_description'][$lang];
                    }

                    if (isset($pic['document']) && $pic['document'] != 1) {
                        $attachments_label[] = isset($pic['image_label']) && !empty($pic['image_label']) && isset($image_label[$pic['image_label']]) && !empty($image_label[$pic['image_label']]) ? $image_label[$pic['image_label']] : '';
                    }

                }
            }
            $f_property['attachments'] = $attachments;
            $f_property['attachments_alt'] = $attachments_alt;
            $f_property['attachments_label'] = $attachments_label;
        } elseif (isset($property['attachments']) && count($property['attachments']) > 0 && isset($property['from_residential']) && $property['from_residential'] == 1) {
            $attachments = [];
            $attachments_alt = [];
            $attachments_label = [];
            foreach ($property['attachments'] as $pic) {
                if (isset($pic["publish_status"]) && !empty($pic["publish_status"])) {
                    if(isset($pic['document']) && $pic['document'] != 1 && isset($agency_data['watermark_image']['show_onweb']) && $agency_data['watermark_image']['show_onweb'] == 1) {
                        $image_size = isset($set_options['image_size']) && !empty($set_options['image_size']) ? $set_options['image_size'] : 1200;
                        $attachments[] = self::$img_url . '/' . $pic['model_id'] . '/' . $image_size . '/' . $pic['file_md5_name'];
                    }elseif (isset($pic['document']) && $pic['document'] != 1 && isset($set_options['image_size']) && !empty($set_options['image_size'])) {
                        // $attachments[] = self::$mls_img_url'] . (isset($property['agency']) ? $property['agency'] : '') . '/' . $pic['model_id'] . '/' . $set_options['image_size'] . '/' .  urldecode($pic['file_md5_name']);
                        $attachments[] = self::$img_url_without_wm . '/' . $pic['model_id'] . '/' . $set_options['image_size'] . '/' .  urldecode($pic['file_md5_name']);
                    } elseif (isset($pic['document']) && $pic['document'] != 1) {
                        $attachments[] = self::$img_url_without_wm . '/' . $pic['model_id'] . '/1200/' .  urldecode($pic['file_md5_name']);
                    }
                    if (isset($pic['document']) && $pic['document'] != 1 && isset($pic['alt_description'][$lang]) && !empty($pic['alt_description'][$lang])) {
                        $attachments_alt[] = $pic['alt_description'][$lang];
                    }

                    if (isset($pic['document']) && $pic['document'] != 1) {
                        $attachments_label[] = isset($pic['image_label']) && !empty($pic['image_label']) && isset($image_label[$pic['image_label']]) && !empty($image_label[$pic['image_label']]) ? $image_label[$pic['image_label']] : '';
                    }
                }
            }
            $f_property['attachments'] = $attachments;
            $f_property['attachments_alt'] = $attachments_alt;
            $f_property['attachments_label'] = $attachments_label;
        }

        if (isset($property['property_attachments']) && count($property['property_attachments']) > 0 && !isset($property['from_residential'])) {
            $attachments_document = [];
            foreach ($property['property_attachments'] as $pic) {
                if (isset($pic["publish_status"]) && !empty($pic["publish_status"])) {
                    $document = [];
                    if (isset($pic['document']) && $pic['document'] == 1 && isset($set_options['image_size']) && !empty($set_options['image_size'])) {
                        $document["link"] = self::$property_img_resize_link . '/' . $pic['model_id'] . '/' . $set_options['image_size'] . '/' .  urldecode($pic['file_md5_name']);
                        $document["type"] = isset($pic["identification_type"]) && !empty($pic["identification_type"]) ? $pic["identification_type"] : 'document';
                    } elseif (isset($pic['document']) && $pic['document'] == 1) {
                        $document["link"] = self::$com_img . '/' . $pic['model_id'] . '/' .  urldecode($pic['file_md5_name']);
                        $document["type"] = isset($pic["identification_type"]) && !empty($pic["identification_type"]) ? $pic["identification_type"] : 'document';
                    }
                    $attachments_document[] = $document;
                }
            }
            $f_property['attachments_document'] = array_filter($attachments_document);
        } elseif (isset($property['attachments']) && count($property['attachments']) > 0 && isset($property['from_residential']) && $property['from_residential'] == 1) {
            $attachments_document = [];
            foreach ($property['attachments'] as $pic) {
                if (isset($pic["publish_status"]) && !empty($pic["publish_status"])) {
                    $document = [];
                    if (isset($pic['document']) && $pic['document'] == 1 && isset($set_options['image_size']) && !empty($set_options['image_size'])) {
                        $document["link"] = self::$img_url_without_wm . '/' . $pic['model_id'] . '/' . $set_options['image_size'] . '/' .  urldecode($pic['file_md5_name']);
                        $document["type"] = isset($pic["identification_type"]) && !empty($pic["identification_type"]) ? $pic["identification_type"] : 'document';
                    } elseif (isset($pic['document']) && $pic['document'] == 1) {
                        $document["link"] = self::$img_url_without_wm . '/' . $pic['model_id'] . '/1200/' .  urldecode($pic['file_md5_name']);
                        $attachments_document[]["type"] = isset($pic["identification_type"]) && !empty($pic["identification_type"]) ? $pic["identification_type"] : 'document';
                    }
                    $attachments_document[] = $document;
                }
            }
            $f_property['attachments_document'] = array_filter($attachments_document);
        }
        if (isset($property['title']) && $property['title'] != '') {
            $f_property['title'] = isset($property['title'][$lang]) && !empty($property['title'][$lang]) ? $property['title'][$lang] : (isset($property['title']["EN"]) ? $property['title']["EN"] : "");
        }
        if (isset($property['description']) && !empty($property['description'])) {
            $f_property['description'] = isset($property['description'][$lang]) && !empty($property['description'][$lang]) ? $property['description'][$lang] : (isset($property['description']["EN"]) ? $property['description']["EN"] : "");
        }

        if (isset($property['buildings']) && $property['buildings'] != '') {
            $f_property['buildings'] = $property['buildings'];
        }

        if (isset($property['sleeps']) && $property['sleeps'] != '') {
            $f_property['sleeps'] = $property['sleeps'];
        }

        if (isset($property['bedrooms']) && $property['bedrooms'] != '') {
            $f_property['bedrooms'] = $property['bedrooms'];
        }

        if (isset($property['bathrooms']) && $property['bathrooms'] != '') {
            $f_property['bathrooms'] = $property['bathrooms'];
        }

        if (isset($property['toilets']) && $property['toilets'] != '') {
            $f_property['toilets'] = $property['toilets'];
        }

        if (isset($property['living_rooms']) && $property['living_rooms'] != '') {
            $f_property['living_rooms'] = $property['living_rooms'];
        }

        if (isset($property['energy_certificate_one']) && $property['energy_certificate_one'] != '') {
            $f_property['energy_certificate_one'] = $property['energy_certificate_one'];
        }

        if (isset($property['energy_certificate_two']) && $property['energy_certificate_two'] != '') {
            $f_property['energy_certificate_two'] = $property['energy_certificate_two'];
        }

        if (isset($property['kilowatt']) && $property['kilowatt'] != '') {
            $f_property['kilowatt'] = $property['kilowatt'];
        }

        if (isset($property['co2']) && $property['co2'] != '') {
            $f_property['co2'] = $property['co2'];
        }

        if (isset($property['miscellaneous_tax']) && $property['miscellaneous_tax'] != '') {
            $f_property['miscellaneous_tax'] = $property['miscellaneous_tax'];
        }

        if (isset($property['rubbish']) && $property['rubbish'] != '') {
            $f_property['rubbish'] = $property['rubbish'];
        }

        if (isset($property['parking_license']) && $property['parking_license'] != '') {
            $f_property['parking_license'] = $property['parking_license'];
        }

        if (isset($property['community_fees']) && $property['community_fees'] != '') {
            $f_property['community_fees'] = $property['community_fees'];
        }

        if (isset($property['real_estate_tax']) && $property['real_estate_tax'] != '') {
            $f_property['real_estate_tax '] = $property['real_estate_tax'];
        }

        if (isset($property['show_on']) && $property['show_on'] != '') {
            $f_property['show_on '] = $property['show_on'];
        }

        if (isset($property['dimensions']) && $property['dimensions'] != '') {
            $f_property['dimensions'] = $property['dimensions'];
        }

        if (isset($property['plot']) && $property['plot'] != '') {
            $f_property['plot'] = $property['plot'];
        }

        if (isset($property['built']) && $property['built'] != '') {
            $f_property['built'] = $property['built'];
        }
        if (isset($property['usefull_area']) && $property['usefull_area'] != '') {
            $f_property['usefull_area'] = $property['usefull_area'];
        }

        if (isset($property['terrace']) && $property['terrace'] != '') {
            $f_property['terrace'] = $property['terrace'];
        }

        if (isset($property['terraces']) && $property['terraces'] != '') {
            $f_property['terraces'] = $property['terraces'];
        }

        if (isset($property['cee']) && $property['cee'] != '') {
            $f_property['cee'] = $property['cee'];
        }

        if (isset($property['facade_size']) && $property['facade_size'] != '') {
            $f_property['facade_size'] = $property['facade_size'];
        }

        if (isset($property['display_window']) && $property['display_window'] != '') {
            $f_property['display_window'] = $property['display_window'];
        }

        if (isset($property['office_size']) && $property['office_size'] != '') {
            $f_property['office_size'] = $property['office_size'];
        }

        if (isset($property['ground_floor']) && $property['ground_floor'] != '') {
            $f_property['ground_floor'] = $property['ground_floor'];
        }

        if (isset($property['stories_total']) && $property['stories_total'] != '') {
            $f_property['stories_total'] = $property['stories_total'];
        }

        if (isset($property['height']) && $property['height'] != '') {
            $f_property['height'] = $property['height'];
        }

        if (isset($property['storage_size']) && $property['storage_size'] != '') {
            $f_property['storage_size'] = $property['storage_size'];
        }

        if (isset($property['bath_tubs']) && $property['bath_tubs'] != '') {
            $f_property['bath_tubs'] = $property['bath_tubs'];
        }

        if (isset($property['bidet']) && $property['bidet'] != '') {
            $f_property['bidet'] = $property['bidet'];
        }

        if (isset($property['jaccuzi_bath']) && $property['jaccuzi_bath'] != '') {
            $f_property['jaccuzi_bath'] = $property['jaccuzi_bath'];
        }

        if (isset($property['corner_shower']) && $property['corner_shower'] != '') {
            $f_property['corner_shower'] = $property['corner_shower'];
        }

        if (isset($property['sink']) && $property['sink'] != '') {
            $f_property['sink'] = $property['sink'];
        }

        if (isset($property['double_sink']) && $property['double_sink'] != '') {
            $f_property['double_sink'] = $property['double_sink'];
        }

        if (isset($property['walk_in_shower']) && $property['walk_in_shower'] != '') {
            $f_property['walk_in_shower'] = $property['walk_in_shower'];
        }

        if (isset($property['en_suite']) && $property['en_suite'] != '') {
            $f_property['en_suite'] = $property['en_suite'];
        }

        if (isset($property['wheelchair_accesible_shower']) && $property['wheelchair_accesible_shower'] != '') {
            $f_property['wheelchair_accesible_shower'] = $property['wheelchair_accesible_shower'];
        }

        if (isset($property['hairdryer']) && $property['hairdryer'] != '') {
            $f_property['hairdryer'] = $property['hairdryer'];
        }

        if (isset($property['furniture_optional']) && $property['furniture_optional'] != '') {
            $f_property['furniture_optional'] = $property['furniture_optional'];
        }

        // if (isset($property['feet_moorings']) && $property['feet_moorings'] != '') {
        //     $f_property['feet_moorings'] = $property['feet_moorings'];
        // }

        if (isset($property['double_bed']) && $property['double_bed'] != '') {
            $f_property['beds']['double_bed'] = $property['double_bed'];
        }

        if (isset($property['single_bed']) && $property['single_bed'] != '') {
            $f_property['beds']['single_bed'] = $property['single_bed'];
        }

        if (isset($property['sofa_bed']) && $property['sofa_bed'] != '') {
            $f_property['beds']['sofa_bed'] = $property['sofa_bed'];
        }

        if (isset($property['bunk_beds']) && $property['bunk_beds'] != '') {
            $f_property['beds']['bunk_beds'] = $property['bunk_beds'];
        }

        $categories = [];
        $setting = [];
        $distances = [];
        $orientation = [];
        $views = [];
        $condition = [];
        $offices = [];
        $custom_fields = [];
        if (isset($property['categories']) && count($property['categories']) > 0) {
            foreach ($property['categories'] as $key => $value) {
                if ($value == true) {
                    $categories[] = $key;
                }
            }
        }

        if (isset($property['settings']) && count($property['settings']) > 0) {
            foreach ($property['settings'] as $key => $value) {
                if ($value == true) {
                    $setting[] = $key;
                }
            }
        }

        if (isset($property['orientations']) && count($property['orientations']) > 0) {
            foreach ($property['orientations'] as $key => $value) {
                if ($value == true) {
                    $orientation[] = $key;
                }
            }
        }

        if (isset($property['views']) && count($property['views']) > 0) {
            foreach ($property['views'] as $key => $value) {
                if ($value == true) {
                    $views[] = $key;
                }
            }
        }

        if (isset($property['conditions']) && count($property['conditions']) > 0) {
            foreach ($property['conditions'] as $key => $value) {
                if ($value == true) {
                    $condition[] = $key;
                }
            }
        }

        if (isset($property['distances']) && count($property['distances']) > 0) {
            foreach ($property['distances'] as $key => $value) {
                if ($value == true && isset($value['value']) && !empty($value['value'])) {
                    $distances[$key] = $value;
                }
            }
        }

        if (isset($property['custom_fields']) && count($property['custom_fields']) > 0) {
            foreach ($property['custom_fields'] as $key => $value) {
                if ($value == true) {
                    $custom_fields[$key] = $value;
                }
            }
        }

        if (isset($property['offices']) && count($property['offices']) > 0) {
            foreach ($property['offices'] as $key => $value) {
                if ($value) {
                    $offices[] = $value;
                }
            }
        }

        $f_property['property_features'] = [];
        $f_property['property_features']['categories'] = $categories;
        $f_property['property_features']['setting'] = $setting;
        $f_property['property_features']['orientation'] = $orientation;
        $f_property['property_features']['views'] = $views;
        $f_property['property_features']['condition'] = $condition;
        $f_property['property_features']['distances'] = $distances;
        $f_property['property_features']['custom_fields'] = $custom_fields;
        $f_property['property_features']['kitchen'] = (isset($property['kitchen'])) ? $property['kitchen'] : '';
        $f_property['property_features']['living_room'] = (isset($property['living_room'])) ? $property['living_room'] : '';
        $f_property['property_features']['security'] = (isset($property['security'])) ? $property['security'] : '';
        $f_property['property_features']['utility'] = (isset($property['utility'])) ? $property['utility'] : '';
        $f_property['property_features']['furniture'] = (isset($property['furniture'])) ? $property['furniture'] : '';
        $f_property['property_features']['climate_control'] = (isset($property['climate_control'])) ? $property['climate_control'] : '';
        $f_property['property_features']['parking'] = (isset($property['parking'])) ? $property['parking'] : '';
        $f_property['property_features']['garden'] = (isset($property['garden'])) ? $property['garden'] : '';
        $f_property['property_features']['pool'] = (isset($property['pool'])) ? $property['pool'] : '';
        $f_property['property_features']['leisure'] = (isset($property['leisures'])) ? $property['leisures'] : '';
        $f_property['property_features']['features'] = (isset($property['features'])) ? $property['features'] : '';
        $f_property['property_features']['rooms'] = (isset($property['rooms'])) ? $property['rooms'] : '';
        $f_property['offices'] = $offices;

        if (isset($property['created_by_name']) && count($property['created_by_name']) > 0) {
            $f_property['created_by_name'] = $property['created_by_name'];
        }

        if (isset($property['lt_rental']) && $property['lt_rental']) {
            $f_property['lt_rental'] = $property['lt_rental'];
        }

        if (isset($property['st_rental']) && $property['st_rental']) {
            $f_property['st_rental'] = $property['st_rental'];
        }

        if (isset($property['license_number']) && $property['license_number']) {
            $f_property['license_number'] = $property['license_number'];
        }

        if (isset($property['date_stamp']) && $property['date_stamp']) {
            $f_property['date_stamp'] = $property['date_stamp'];
        }

        if (isset($property['year_built']) && $property['year_built']) {
            $year_built_date = new DateTime($property['year_built'], new DateTimeZone('UTC'));
            $f_property['year_built'] = $year_built_date->format('d-m-Y');
        }

        if (isset($property['similar_commercials']) && !empty($property['similar_commercials'])) {
            $f_property['similar_commercials'] = $property['similar_commercials'];
        }

        return $f_property;
    }

    public static function DoCache($query, $url)
    {
        $webroot = public_path() . '/uploads/';
        $file_name = 'cached_properties_';

        // Ensure the "uploads" directory exists
        if (!File::exists($webroot)) {
            File::makeDirectory($webroot, 0755, true); // Creates the directory with proper permissions
        }

        // Ensure the "uploads/temp" directory exists
        $tempDirectory = $webroot . 'temp/';
        if (!File::exists($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true); // Creates the directory with proper permissions
        }

        if (isset($_GET) && !empty($_GET)) {
            foreach ($_GET as $key => $value) {
                $file_name .= $key . '_';
            }
        }

        $file = $tempDirectory . sha1($file_name) . '.json';
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {

            $file_data = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($query)),
                'Cache-Control' => 'no-cache'
            ])->post($url, $query)->json();

            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }
        return $file_data;
    }

    public static function findAllWithLatLang($qry = 'true', $map_query = [], $cache = false, $selectedFields = "")
    {
        self::initialize();
        $webroot = public_path() . '/uploads/';
        $node_url = self::$node_url . 'commercial_properties/find-all?user=' . self::$user . (isset($qry) && $qry == 'true' ? '&latLang=1' : '');
        $node_url = isset($selectedFields) && !empty($selectedFields) ? ($node_url . $selectedFields) : $node_url;
        $query = [];
        $sort = ['current_price' => '-1'];
        $query_array = [];
        $options = ["page" => 1, "limit" => 10];
        $options['populate'] = [
            [
                'path' => 'property_attachments',
                'match' => ['document' => ['$ne' => true], 'publish_status' => ['$ne' => false]],
            ]
        ];

        Functions::mergeRequest( $_GET ?? []);

        if (Request::has('orderby') && is_array(Request::get('orderby')) && count(Request::get('orderby')) == 2) {
            $sort = [Request::get('orderby')[0] => Request::get('orderby')[1]];
        }

        $options['sort'] = $sort;


        if (isset($query) && $query != '' && !is_array($query)) {
            $vars = explode('&', $query);
            foreach ($vars as $var) {
                $k = explode('=', $var);
                if (isset($k[0]) && isset($k[1])) {
                    if ($k[0] == 'favourite_ids') {
                        $query_array['favourite_ids'] = explode(',', $k[1]);
                        $query_array['archived']['$ne'] = true;
                    } else {
                        $post_data[$k[0]] = $k[1];
                        $post_data['archived']['$ne'] = true;
                    }
                }
            }
        }
        if (isset($query) && $query != '' && is_array($query)) {
            if (!count($query)) {
                // if(Request::has('prop_ids') && Request::input('prop_ids') == "false"){
                    unset($_GET["prop_ids"]);
                    Request::replace(Request::except('prop_ids'));
                // }
                $query = self::setQuery();
            }
            if (count($query)) {
                $query_array = $query;
                $query_array['status'] = ['$in' => (isset(self::$status) && !empty(self::$status) ? self::$status : ['Available', 'Under Offer'])];
            }
        }
        $post_data = ["options" => $options];
        if (!empty($query_array)) {
            $post_data["query"] =  $query_array;
        }

        if(isset($post_data["query"]["remove_count"]) && !empty($post_data["query"]["remove_count"])){
            unset($post_data["query"]["remove_count"]);
        }

        $post_data["query"] = isset($map_query['ids']) && !empty($map_query['ids']) ? array_merge($post_data["query"], ["id"  => $map_query['ids']]) : $post_data["query"];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $post_data)->json();

        if (!File::exists($webroot)) {
            File::makeDirectory($webroot, 0755, true); // Creates the directory with proper permissions
        }

        // Ensure the "uploads/temp" directory exists
        $tempDirectory = $webroot . 'temp/';
        if (!File::exists($tempDirectory)) {
            File::makeDirectory($tempDirectory, 0755, true); // Creates the directory with proper permissions
        }
        if (!$cache) {
            return $response;
        }

        $file = $tempDirectory . 'commercial_properties-latlang.json';
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            file_put_contents($file, $response);
            $file_data = file_put_contents($file, $response);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, true);
    }

    public static function getAgencyProperties($transaction_type = 'sale', $id, $options = ['page' => 1, 'limit' => 10])
    {
        self::initialize();

        $post_data['options'] = [
            'page' => $options['page'],
            'limit' => $options['limit'],
            'populate' => ['property_attachments', 'agency_data', 'listing_agency_data']
        ];

        $post_data['query'] = [
            'id' => $id
        ];

        $node_url = self::$node_url . 'commercial_properties/get-properties-with-transaction-types/' . $transaction_type . '?user=' . self::$user;
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $post_data)->json();

        $properties = [];
        if (isset($response) && isset($response['docs']))
            foreach ($response['docs'] as $property) {
                $properties[] = self::formateProperty($property);
            }
        $response['docs'] = $properties;

        return $response;
    }

    public static function getAgencies($query = [], $options = [])
    {
        self::initialize();
        $post_data['option'] = [
            'skipLimit' => (isset($options['skipLimit']) ? (int)$options['skipLimit'] : 0),
            'endLimit' => (isset($options['endLimit']) ? (int)$options['endLimit'] : 10),
        ];

        $post_data['query']['type'] = 'Agency';

        if (isset($query['country']) && !empty($query['country'])) {
            $post_data['query']['country'] = (int)$query['country'];
        }

        if (isset($query['cities']) && !empty($query['cities'])) {
            $intArray = array();
            foreach ($query['cities'] as $int_val) {
                $intArray[] = (int) $int_val;
            }
            $post_data['query']['city'] = ['$in' => $intArray];
        }

        if (isset($query['languages']) && !empty($query['languages'])) {
            $intArray = array();
            foreach ($query['languages'] as $int_val) {
                $intArray[] = (string)$int_val;
            }
            $post_data['query']['$or'][]['communication_language'] = ['$in' => $intArray];
            $post_data['query']['$or'][]['spoken_language'] = ['$in' => $intArray];
        }

        if (isset($query['transaction_type']) && !empty($query['transaction_type'])) {
            foreach ($query['transaction_type'] as $int_val) {
                $post_data['query'][$int_val] = (bool)'true';
            }
        }

        if (isset($query['property_valuation']) && !empty($query['property_valuation'])) {
            $post_data['query']['property_valuation'] = $query['property_valuation'];
        }

        $node_url = self::$node_url . 'companies/search-company?user=' . self::$user;


        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $post_data)->json();
    }

    public static function findListingAgency($id)
    {
        self::initialize();
        $post_data['option'] = [
            "skipLimit" => 0,
            "endLimit" => 3
        ];
        $post_data['query'] = [
            "type" => "Agency"
        ];

        $node_url = self::$node_url . 'companies/company-type-of-agency/' . $id;

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $post_data)->json();
    }

    public static function findAnAgency($id)
    {
        self::initialize();
        $post_data['option'] = [
            "skipLimit" => 0,
            "endLimit" => 3
        ];

        $post_data['query'] = [
            "type" => "Agency"
        ];

        $node_url = self::$node_url . 'companies/get-agency-data/' . $id;

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $post_data)->json();
    }

    public static function createProperty($data)
    {
        self::initialize();
        $languages = Cms::siteLanguages();
        $fields = [
            'sale' => (isset($data['transaction_type']) && $data['transaction_type'] == 'sale' ? (bool)'1' : (bool)'0'),
            'rent' => (isset($data['transaction_type']) && $data['transaction_type'] == 'rent' ? (bool)'1' : (bool)'0'),
            'auction_tab' => (isset($data['transaction_type']) && $data['transaction_type'] == 'auction' ? (bool)'1' : (bool)'0'),
            'starting_price' => (isset($data['starting_price']) && !empty($data['starting_price']) ? (int)$data['starting_price'] : ''),
            'minimum_price' => (isset($data['minimum_price']) && !empty($data['minimum_price']) ? (int)$data['minimum_price'] : ''),
            'auction_start_date' => (isset($data['auction_start_date']) && !empty($data['auction_start_date']) ? $data['auction_start_date'] : ''),
            'auction_end_date' => (isset($data['auction_end_date']) && !empty($data['auction_end_date']) ? $data['auction_end_date'] : ''),
            'lt_rental' => (isset($data['transaction_type']) && $data['transaction_type'] == 'rent' ? (bool)'1' : (bool)'0'),
            'type_one' => (isset($data['type_one']) && !empty($data['type_one']) ? (int)$data['type_one'] : ''),
            'type_two' => (isset($data['type_two']) && !empty($data['type_two']) ? (int)$data['type_two'] : ''),
            'bedrooms' => (isset($data['bedrooms']) && !empty($data['bedrooms']) ? (int)$data['bedrooms'] : ''),
            'bathrooms' => (isset($data['bathrooms']) && !empty($data['bathrooms']) ? (int)$data['bathrooms'] : ''),
            'built'  => (isset($data['built']) && !empty($data['built']) ? (int)$data['built'] : ''),
            'plot'  => (isset($data['plot']) && !empty($data['plot']) ? (int)$data['plot'] : ''),
            'energy_certificate_one' => (isset($data['energy_certificate_one']) && !empty($data['energy_certificate_one']) ? (string)$data['energy_certificate_one'] : ''),
            'private_info_object' => [self::$agency => ['cadastral_numbers' => [0 => ['cadastral_number' => (isset($data['cadastral_numbers']) && !empty($data['cadastral_numbers']) ? (int)$data['cadastral_numbers'] : '')]]]],
            'address' => ['formatted_address' => (isset($data['formatted_address']) && !empty($data['formatted_address']) ? (string)$data['formatted_address'] : '')],
            'country' => (isset($data['country']) && !empty($data['country']) ? (int)$data['country'] : ''),
            'region'  => (isset($data['region']) && !empty($data['region']) ? (int)$data['region'] : ''),
            'province'  => (isset($data['province']) && !empty($data['province']) ? (int)$data['province'] : ''),
            'city'  => (isset($data['city']) && !empty($data['city']) ? (int)$data['city'] : ''),
            'location' => (isset($data['location']) && !empty($data['location']) ? (int)$data['location'] : ''),
            'street' => (isset($data['street']) && !empty($data['street']) ? (string)$data['street'] : ''),
            'street_number' => (isset($data['street_number']) && !empty($data['street_number']) ? (string)$data['street_number'] : ''),
            'postal_code'  => (isset($data['postal_code']) && !empty($data['postal_code']) ? (string)$data['postal_code'] : ''),
            'currency' => (isset($data['currency']) && !empty($data['currency']) ? (string)$data['currency'] : ''),
            'latitude_alt' => (isset($data['lat']) && !empty($data['lat']) ? $data['lat'] : ''),
            'longitude_alt' => (isset($data['lng']) && !empty($data['lng']) ? $data['lng'] : ''),
            'status' => (isset($data['status']) && !empty($data['status']) ? $data['status'] : 'Valuation'),
            'owner' => (isset($data['owner_id']) ? $data['owner_id'] : ''),
            'property_name' => (isset($data['property_name']) ? $data['property_name'] : ''),
            'ltr' => (isset($data['ltr']) ? $data['ltr'] : null),
            'period_seasons' => (isset($data['period_seasons']) ? $data['period_seasons'] : null),
            'shared_categories' => (isset($data['shared_categories']) ? $data['shared_categories'] : null),
        ];
        if (isset($data['transaction_type']) && $data['transaction_type'] == 'sale') {
            $fields['current_price'] = (isset($data['current_price']) && !empty($data['current_price']) ? (int)$data['current_price'] : '');
        } elseif (isset($data['transaction_type']) && $data['transaction_type'] == 'rent') {
            $fields['period_seasons'][] = ['seasons' => (isset($data['seasons']) && !empty($data['seasons']) ? $data['seasons'] : 'All year'), 'new_price' => (isset($data['current_price']) && !empty($data['current_price']) ? ((int)$data['current_price'] * 12) : ''), 'total_per_month' => (isset($data['current_price']) && !empty($data['current_price']) ? (int)$data['current_price'] : '')];
        }
        $fields['project'] = false;
        $fields['features'] = ['lift_elevator' => false];
        $fields['security'] = ['gated_complex' => false];
        $fields['categories']['freehold'] = false;
        $fields['categories']['leasehold'] = false;
        $fields['parking']['private'] = false;
        $fields['parking']['parking_communal'] = false;
        $fields['garden']['garden_private'] = false;
        $fields['garden']['garden_communal'] = false;
        $fields['pool']['pool_private'] = false;
        $fields['pool']['pool_communal'] = false;
        if (isset($data['parking']) && !empty($data['parking'])) {
            foreach ($data['parking'] as $parking) {
                $fields['parking'][$parking] = true;
            }
        }
        if (isset($data['garden']) && !empty($data['garden'])) {
            foreach ($data['garden'] as $garden) {
                $fields['garden'][$garden] = true;
            }
        }
        if (isset($data['pool']) && !empty($data['pool'])) {
            foreach ($data['pool'] as $pool) {
                $fields['pool'][$pool] = true;
            }
        }
        if (isset($data['features']) && !empty($data['features'])) {
            foreach ($data['features'] as $feature) {
                if ($feature == 'project') {
                    $fields[$feature] = true;
                } elseif ($feature == 'lift_elevator') {
                    $fields['features'] = [$feature => true];
                } elseif ($feature == 'gated_complex') {
                    $fields['security'] = [$feature => true];
                } else {
                    $fields['categories'][$feature] = true;
                }
            }
        }
        if (isset($languages) && !empty($languages)) {
            foreach ($languages as $lang) {
                if (isset($data['transaction_type']) && $data['transaction_type'] == 'sale') {
                    $fields['title'][strtoupper($lang)] = (isset($data['title'][strtoupper($lang)]) && !empty($data['title'][strtoupper($lang)]) ? $data['title'][strtoupper($lang)] : $data['title']['EN']);
                    $fields['description'][strtoupper($lang)] = (isset($data['description'][strtoupper($lang)]) && !empty($data['description'][strtoupper($lang)]) ? $data['description'][strtoupper($lang)] : $data['description']['EN']);
                } elseif (isset($data['transaction_type']) && $data['transaction_type'] == 'auction') {
                    $fields['title'][strtoupper($lang)] = (isset($data['title'][strtoupper($lang)]) && !empty($data['title'][strtoupper($lang)]) ? $data['title'][strtoupper($lang)] : $data['title']['EN']);
                    $fields['description'][strtoupper($lang)] = (isset($data['description'][strtoupper($lang)]) && !empty($data['description'][strtoupper($lang)]) ? $data['description'][strtoupper($lang)] : $data['description']['EN']);
                } else {
                    $fields['rental_title'][strtoupper($lang)] = (isset($data['title'][strtoupper($lang)]) && !empty($data['title'][strtoupper($lang)]) ? $data['title'][strtoupper($lang)] : $data['title']['EN']);
                    $fields['rental_description'][strtoupper($lang)] = (isset($data['description'][strtoupper($lang)]) && !empty($data['description'][strtoupper($lang)]) ? $data['description'][strtoupper($lang)] : $data['description']['EN']);
                }
            }
        }

        if (isset($data['prop_id']) && !empty($data['prop_id'])) {
            $node_url = self::$node_url . 'commercial_properties/update/' . $data['prop_id'] . '?user=' . $data['user_id'];

            return Http::withHeaders([
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache'
            ])->put($node_url, $fields)->json();
        } else {
            $node_url = self::$node_url . 'commercial_properties/create?user=' . $data['user_id'];

            return Http::withHeaders([
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache'
            ])->post($node_url, $fields)->json();
        }
    }

    public static function savePropertyAttachments($id, $images)
    {
        self::initialize();
        $node_url = self::$apiUrl . 'commercial-properties/upload-images&user_apikey=' . self::$api_key;

        $fields = [
            'id' => $id,
            'modelName' => "commercial_images",   // model name should never be changed               // depend on you to send or send empty value
            'files' => $images,
        ];

        return  Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $fields)->json();
    }

    public static function saveCompanyAttachments($company_id, $images, $type)
    {
        self::initialize();
        $node_url = self::$apiUrl . 'users/upload-images&user_apikey=' . self::$api_key;

        $fields = [
            'id' => $company_id,
            'model' => "companies",   // model name should never be changed        // depend on you to send or send empty value
            'type' => $type,
            'files' => $images,
        ];

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $fields)->json();
    }

    public static function savePropertyOfInterest($data)
    {
        self::initialize();
        $node_url = self::$node_url . 'accounts/update-with-email/?user_apikey=' . self::$api_key;

        $fields['query'] = [
            'email' => $data['email'],
            'data' => [
                'commercials_interested' => [(int)$data['id']],
                'communication_language' => strtoupper(App::getLocale()),
                'language' => [strtoupper(App::getLocale())],
                'title' => 'update account',
            ],
        ];

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $fields)->json();
    }

    public static function getAllUserProperties($query, $options = ['page' => 1, 'limit' => 10], $sort = ['current_price' => '-1'])
    {
        self::initialize();
        $node_url = self::$node_url . 'commercial_properties/get-all-properties-of-user/?user=' . $query['_id'];
        $post_data['options'] = [
            'page' => isset($options['page']) ? (int)$options['page'] : 1,
            'limit' => isset($options['limit']) ? (int)$options['limit'] : 10,
            "populate" => ["property_attachments", "property_type_one", "property_type_two", "listing_agency_data", "agency_data"],
            'sort' => $sort,
        ];
        $post_data['query'] = [
            "userId" => $query['_id'],
            "type" => $query['property_type'],
        ];

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($node_url, $post_data)->json();
    }

    public static function getCadastralData()
    {
        self::initialize();
        $file = Functions::directory() . 'cadastral-data.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $node_url = self::$node_url . 'commercial_properties/get-all-agencies-of-same-cadastral-number/?user=' . self::$user;
            $file_data = Http::post($node_url)->body();

            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function getCadastralProperties($same_cadastral_prop_ids)
    {
        self::initialize();
        $url = self::$node_url . '/commercial_properties/get-same-properties-of-cadastral-number/?user=' . self::$user;
        $query['query'] = [
            'ids' => $same_cadastral_prop_ids,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->post($url, $query)->json();

        $properties = [];

        if (isset($response) && isset($response['docs'])) {
            foreach ($response['docs'] as $property) {
                $properties[] = self::formateProperty($property);
            }

            $response['docs'] = $properties;
        }

        return $response;
    }

    public static function getAgency()
    {
        $file = Functions::directory() . 'agency' . '.json';
        $url = self::$apiUrl . 'properties/agency&user_apikey=' . self::$api_key;
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $file_data = Functions::getCRMData($url);
            if ($file_data) {
                file_put_contents($file, $file_data);
            }
        } else {
            $file_data = file_get_contents($file);
        }
        return json_decode($file_data, true);
    }
}
