<?php

namespace Daz\OptimaClass\Helpers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;

class Dropdowns extends BaseHelper
{
    public static function countries($model_type = '' , $prop_types = [])
    {
        self::initialize();
        $query = '';

        if(isset($prop_types['type']) && !empty($prop_types['type'])){
            foreach($prop_types['type'] as $p_type){
                $query .='&'.$p_type.'=1';
            }
        }

        $query .= isset($prop_types['system_lang']) && !empty($prop_types['system_lang']) ? '&system_lang='.$prop_types['system_lang'] : '';
        $query .= isset($prop_types['transaction_types']) && !empty($prop_types['transaction_types']) ? '&transaction_types='.$prop_types['transaction_types'] : '';
        $query .= isset($prop_types['prop_status']) && !empty($prop_types['prop_status']) ? '&prop_status='.implode(',', $prop_types['prop_status']) : '';
        $query .= isset($model_type) && !empty($model_type) ? '&model_type=' . $model_type : '';
        $file = Functions::directory() . 'countries'.(!empty($model_type) ? $model_type : '').(!empty($prop_types['type']) ?  '_'.implode('-', $prop_types['type']) : '').'.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/countries&user_apikey=' . self::$api_key .(!empty($query) ? $query : '');
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function getNationalities($nation_word = '')
    {
        self::initialize();
        $file = Functions::directory().'nationalities'.'.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/nationalities&user_apikey='. self::$api_key .'&lang=en&search_word='.(isset($nation_word) ? $nation_word : '').'&page=1&per-page=1000';
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function getRegions($params = [])
    {
        self::initialize();
        $countries = isset($params['countries']) ? (is_array($params['countries']) ? $params['countries'] : explode(',', $params['countries'])) : [];
        $return_data = [];

        $file = Functions::directory() . 'regions' . implode(',', $countries) . '.json';
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = count($countries) ? array('country' => ['$in' => $countries]) : [];
            $options = [
                "page" => 1,
                "limit" => 1000,
                "sort" => ["accent_value.en" => 1]
            ];

            $post_data = ["query" => (object) $query, "options" => $options];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data, JSON_NUMERIC_CHECK))
            ])->post(self::$node_url . 'regions?user='. self::$user, $post_data);

            $data = $response->json();
            $return_data = isset($data['docs']) ? $data['docs'] : [];

            file_put_contents($file, json_encode($return_data));
        } else {
            $return_data = json_decode(file_get_contents($file), TRUE);
        }

        return $return_data;
    }

    // use Dropdowns::getProvinces() as it will provide more options to handle data in controller and works with countries and regions search too
    public static function provinces($country = '')
    {
        self::initialize();
        $country_query = $country == 'all' ? '&country=all' : '';
        $file = Functions::directory() . 'provinces.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/provinces&user_apikey=' . self::$api_key . $country_query;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function getProvinces($params = [])
    {
        self::initialize();
        $countries = isset($params['countries']) ? (is_array($params['countries']) ? $params['countries'] : explode(',', $params['countries'])) : [];
        $regions = isset($params['regions']) ? (is_array($params['regions']) ? $params['regions'] : explode(',', $params['regions'])) : [];
        $transaction_types = isset($params['transaction_types']) ? $params['transaction_types'] : [];
        $types = isset($params['type']) ? $params['type'] : [];
        $return_data = [];
        $file = Functions::directory() . 'provinces_' . implode(',', $regions) . implode(',', $countries).'_'.implode('-', $types).'_'.implode('-', $transaction_types). '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = count($countries) ? array('country' => ['$in' => $countries]) : [];
            $query = count($regions) ? array_merge($query, array('region' => ['$in' => $regions])) : $query;
            $query = isset($transaction_types) && !empty($transaction_types) ? array_merge($query, $transaction_types) : $query;
            $query['prop_status'] = isset(self::$status) && !empty(self::$status) ? self::$status : ['Available','Under Offer','Sold'];

            if(isset($types) && !empty($types))
            {
                foreach($types as $p_type){
                    $query = isset($p_type) && !empty($p_type) ? array_merge($query, array( $p_type => 1)) : $query;
                }
            }

            $options = [
                "page" => 1,
                "limit" => 50,
                "sort" => ["accent_value.en" => 1]
            ];

            $post_data = ["query" => (object) $query, "options" => $options];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data, JSON_NUMERIC_CHECK))
            ])->post(self::$node_url . 'provinces?user=' . self::$user, $post_data);

            $data = $response->json();
            $return_data = isset($data['docs']) ? $data['docs'] : [];

            file_put_contents($file, json_encode($return_data));
        } else {
            $return_data = json_decode(file_get_contents($file), TRUE);
        }

        return $return_data;
    }

    // use Dropdowns::getCities() as it will provide more options to handle data in controller and works with countries search too
    public static function cities($country = '', $provinces = [], $to_json = false, $prop_count = 1)
    {
        self::initialize();
        $country_query = $country == 'all' ? '&country=all' : '&country='.$country;
        $file = Functions::directory() . 'cities_' .(isset($provinces) && !empty($provinces) ? implode(',', $provinces) : '') . '.json';
        
        if (is_array($provinces) && count($provinces) && !file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $p_q = '';
            foreach ($provinces as $province) {
                $p_q .= '&province[]=' . $province;
            }
            $url = self::$apiUrl . 'properties/all-cities' . $p_q . '&user_apikey=' . self::$api_key . $country_query.'&check_prop_count='.$prop_count;
            $file_data = Functions::getCRMData($url);
            $file_data = json_decode($file_data);
            usort($file_data, function ($item1, $item2) {
                return $item1->value <=> $item2->value;
            });
            $file_data = json_encode($file_data);
            file_put_contents($file, $file_data);
        } elseif (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/all-cities&user_apikey=' . self::$api_key . $country_query.'&check_prop_count='.$prop_count;
            $file_data = Functions::getCRMData($url);
            $file_data = json_decode($file_data);
            usort($file_data, function ($item1, $item2) {
                return $item1->value <=> $item2->value;
            });
            $file_data = json_encode($file_data);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return $to_json ? json_encode(json_decode($file_data, TRUE)) : json_decode($file_data, TRUE);
    }

    public static function getCities($params = [])
    {
        self::initialize();
        $countries = isset($params['countries']) ? (is_array($params['countries']) ? $params['countries'] : explode(',', $params['countries'])) : [];
        $provinces = isset($params['provinces']) ? (is_array($params['provinces']) ? $params['provinces'] : explode(',', $params['provinces'])) : [];
        $transaction_types = isset($params['transaction_types']) ? $params['transaction_types'] : [];
        $types = isset($params['type']) ? $params['type'] : [];
        $return_data = [];
        $file = Functions::directory() . 'cities_' . implode(',', $provinces).'_'.implode('-', $types).'_'.implode('-', $transaction_types). '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = count($countries) ? array('country' => ['$in' => $countries]) : [];
            $query = count($provinces) ? array_merge($query, array('province' => ['$in' => $provinces])) : $query;
            $query = isset($transaction_types) && !empty($transaction_types) ? array_merge($query, $transaction_types) : $query;
            $query['prop_status'] = isset(self::$status) && !empty(self::$status) ? self::$status : ['Available','Under Offer','Sold'];

            if(isset($types) && !empty($types))
            {
                foreach($types as $p_type){
                    $query = isset($p_type) && !empty($p_type) ? array_merge($query, array( $p_type => 1)) : $query;
                }
            }

            $options = [
                "page" => 1,
                "limit" => 1000,
                "sort" => ["accent_value.en" => 1]
            ];

            $post_data = ["query" => (object) $query, "options" => $options];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data, JSON_NUMERIC_CHECK))
            ])->post(self::$node_url . 'cities?user=' . self::$user, $post_data);

            $data = $response->json();

            $return_data = isset($data['docs']) ? $data['docs'] : [];
            file_put_contents($file, json_encode($return_data));
        } else {
            $return_data = json_decode(file_get_contents($file), TRUE);
        }

        return $return_data;
    }

    public static function locationGroups($provinces = [])
    {
        self::initialize();
        $file = Functions::directory() . 'locationGroups_' . implode(',', $provinces) . '.json';

        if (is_array($provinces) && count($provinces) && !file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $p_q = '';
            foreach ($provinces as $province) {
                $p_q .= '&province[]=' . $province;
            }
            $url = self::$apiUrl . 'properties/location-groups-key-value' . $p_q . '&user_apikey=' . self::$api_key;
            $file_data = Functions::getCRMData($url);

            file_put_contents($file, $file_data);
        } elseif (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/location-groups-key-value&user_apikey=' . self::$api_key;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, true);
    }

    // use Dropdowns::getLocations() as it will provide more options to handle data in controller
    public static function locations($provinces = [], $to_json = false, $cities = [], $country = '', $count = 'true')
    {
        self::initialize();
        $lang = App::getLocale();
        $file = Functions::directory() . 'locations_' . implode(',', $provinces) . implode(',', $cities) . '.json';
        
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $p_q = '';
            $c_q = '';
            if (is_array($provinces) && count($provinces)) {
                foreach ($provinces as $province) {
                    $p_q .= '&province[]=' . $province;
                }
            }

            if (is_array($cities) && count($cities)) {
                foreach ($cities as $city) {
                    $c_q .= '&city[]=' . $city;
                }
            }

            $country_check = '';
            if ($country) {
                $country_check = '&country=' . $country;
            }

            $url = self::$apiUrl . 'properties/locations'.($count == 'true' ? '&count=true' : ''). $p_q . $c_q . '&user_apikey=' . self::$api_key . '&lang=' . ((isset($lang) && strtolower($lang) == 'es') ? 'es_AR' : 'en') . $country_check;
            
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return $to_json ? json_encode(json_decode($file_data, TRUE)) : json_decode($file_data, TRUE);
    }

    public static function getLocations($params = [])
    {
        self::initialize();
        $countries = isset($params['countries']) ? (is_array($params['countries']) ? $params['countries'] : explode(',', $params['countries'])) : [];
        $cities = isset($params['cities']) ? (is_array($params['cities']) ? $params['cities'] : explode(',', $params['cities'])) : [];
        $return_data = [];
        $file = Functions::directory() . 'locations_' . implode(',', $cities) . '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = count($countries) ? array('country' => ['$in' => $countries]) : [];
            $query = count($cities) ? array_merge($query, array('city' => ['$in' => $cities])) : $query;

            $options = [
                "page" => 1,
                "limit" => 1000,
                "sort" => ["accent_value.en" => 1]
            ];

            $post_data = ["query" => (object) $query, "options" => $options];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data, JSON_NUMERIC_CHECK))
            ])->post(self::$node_url . 'locations?user=' . self::$user, $post_data);

            $data = $response->json();

            $return_data = isset($data['docs']) ? $data['docs'] : [];
            file_put_contents($file, json_encode($return_data));
        } else {
            $return_data = json_decode(file_get_contents($file), TRUE);
        }

        return $return_data;
    }

    // use Dropdowns::getUrbanisations() as it will provide more options to handle data in controller
    public static function urbanisations()
    {
        self::initialize();
        $return_data = [];
        $file = Functions::directory() . 'urbanisations.json';
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $post_data = ["query" => (object) [], "options" => ["page" => 1, "limit" => 1000, "sort" => ["value" => 1], "select" => "_id key value agency basic_info." . self::$agency]];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data))
            ])->post(self::$node_url . 'urbanisations/dropdown?user=' . self::$user, $post_data);

            $data = $response->json();

            if (isset($data['docs']) && count($data['docs']) > 0) {
                foreach ($data['docs'] as $doc) {
                    if (isset($doc['basic_info'][self::$agency]['status']) && $doc['basic_info'][self::$agency]['status'] == 'Active' && isset($doc['key']))
                        $return_data[$doc['key']] = isset($doc['value']) ? $doc['value'] : '';
                }
            }
            file_put_contents($file, json_encode($return_data));
        } else {
            $return_data = json_decode(file_get_contents($file), TRUE);
        }

        return $return_data;
    }

    public static function getUrbanisations($params = [])
    {
        self::initialize();
        $return_data = [];
        $file = Functions::directory() . 'urbanisation.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $query = [];
            $options = [
                "page" => 1,
                "limit" => 1000,
                "sort" => ["value" => 1],
                "select" => "_id key value agency basic_info." . self::$agency
            ];

            $post_data = ["query" => (object) $query, "options" => $options];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data, JSON_NUMERIC_CHECK))
            ])->post(self::$node_url . 'urbanisations/dropdown?user=' . self::$user, $post_data);

            $data = $response->json();
            $return_data = isset($data['docs']) ? $data['docs'] : [];
            file_put_contents($file, json_encode($return_data));
        } else {
            $return_data = json_decode(file_get_contents($file), TRUE);
        }

        return $return_data;
    }

    public static function getCustomCategories($params = [])
    {
        self::initialize();
        $file = Functions::directory() . 'custom_categories.json';
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/categories&user_apikey=' . self::$api_key;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function mooringTypes($params = [])
    {
        self::initialize();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Content-Length' => strlen(json_encode([]))
        ])->post(self::$node_url . 'mooring_types/all?user_apikey=' . self::$api_key, []);

        $return_data = $response->json();

        if (isset($params['allData']))
            return $return_data;

        foreach ($return_data as $mooring_type) {
            $value = isset($mooring_type['value'][strtolower(App::getLocale()) == 'es' ? 'es_AR' : strtolower(App::getLocale())]) ? $mooring_type['value'][strtolower(App::getLocale()) == 'es' ? 'es_AR' : strtolower(App::getLocale())] : $mooring_type['value']['en'];
            $mooring_types[$mooring_type['key']] = $value;
        }

        return $mooring_types;
    }

    public static function types()
    {
        self::initialize();
        $file = Functions::directory() . 'types.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/types&user_apikey=' . self::$api_key;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function CommercialType($params = [])
    {
        self::initialize();
        $query = [];
        $types = isset($params['type']) ? $params['type'] : [];
        $countries = isset($params['countries']) && is_array($params['countries']) ? $params['countries'] : [];
        $query = count($countries) ? array('country' => ['$in' => $countries]) : [];
        $transaction_types = isset($params['transaction_types']) ? $params['transaction_types'] : [];
        $query = isset($transaction_types) && !empty($transaction_types) ? array_merge($query, $transaction_types) : $query;
        $query['prop_status'] = isset(self::$status) && !empty(self::$status) ? self::$status : ['Available','Under Offer','Sold'];

        if(isset($types) && !empty($types)){
            foreach($types as $p_type){
                $query = isset($p_type) && !empty($p_type) ? array_merge($query, array( $p_type => 1)) : $query;
            }
        }

        $options = [
            "page" => 1,
            "limit" => 200,
        ];

        $file = Functions::directory() . 'Commercial_types_'.implode('_', $types).implode('_', $countries).implode('_', $transaction_types).'.json';
        $post_data = ["query" => (object) $query, "options" => $options];

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($post_data))
            ])->post(self::$node_url . 'commercial_types?user_apikey=' . self::$api_key, $post_data);

            file_put_contents($file, $response->json());
        }else{
            $response = file_get_contents($file);
        }

        return json_decode($response, TRUE);
    }

    public static function typesByLanguage()
    {
        self::initialize();
        $types = [];

        $file = Functions::directory() . 'types.json';
        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/types&user_apikey=' . self::$api_key;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        $fdata = json_decode($file_data);

        foreach ($fdata as $file) {
            $sub_types = [];
            if (isset($file->sub_type) && count($file->sub_type) > 0) {
                foreach ($file->sub_type as $subtype) {
                    $sub_types[] = ['key' => $subtype->key, 'value' => __('app.'. strtolower($subtype->value->en))];
                }
                usort($sub_types, function ($item1, $item2) {
                    return $item1['value'] <=> $item2['value'];
                });
            }
            $types[] = ['key' => $file->key, 'value' => __('app.'. strtolower($file->value->en)), 'sub_types' => $sub_types];
        }

        usort($types, function ($item1, $item2) {
            return $item1['value'] <=> $item2['value'];
        });

        return $types;
    }

    public static function buildingStyles()
    {
        self::initialize();
        $file = Functions::directory() . 'building-style.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/building-style&user_apikey=' . self::$api_key;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function offices()
    {
        self::initialize();
        $file = Functions::directory() . 'offices.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $url = self::$apiUrl . 'properties/get-offices&user_apikey=' . self::$api_key . '&agency_id=' . self::$agency;
            $file_data = Functions::getCRMData($url);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return json_decode($file_data, TRUE);
    }

    public static function numbers($limit)
    {
        return range(1, $limit);
    }

    public static function prices($from, $to, $to_json = false)
    {
        $range = range($from, $to);
        $data = [];
        foreach ($range as $value) {
            if ($value <= 2000 && $value % 200 == 0) {
                $data[] = [
                    'key' => $value,
                    'value' => str_replace(',', '.', (number_format((int) $value))) . ' €'
                ];
            }
            if ($value > 25000 && $value % 25000 == 0) {
                $data[] = [
                    'key' => $value,
                    'value' => str_replace(',', '.', (number_format((int) $value))) . ' €'
                ];
            }
        }
        return $to_json ? json_encode($data) : $data;
    }

    public static function settings()
    {
        return [
            ['key' => "beachfront", 'value' => __('app.beachfront')],
            ['key' => "beachside", 'value' => __('app.beachside')],
            ['key' => "close_to_airport", 'value' => __('app.close_to_airport')],
            ['key' => "close_to_busstop", 'value' => __('app.close_to_busstop')],
            ['key' => "close_to_church", 'value' => __('app.close_to_church')],
            ['key' => "close_to_forest", 'value' => __('app.close_to_forest')],
            ['key' => "close_to_golf", 'value' => __('app.close_to_golf')],
            ['key' => "close_to_hotel", 'value' => __('app.close_to_hotel')],
            ['key' => "close_to_marina", 'value' => __('app.close_to_marina')],
            ['key' => "close_to_mosque", 'value' => __('app.close_to_mosque')],
            ['key' => "close_to_port", 'value' => __('app.close_to_port')],
            ['key' => "close_to_restaurant", 'value' => __('app.close_to_restaurant')],
            ['key' => "close_to_schools", 'value' => __('app.close_to_schools')],
            ['key' => "close_to_sea", 'value' => __('app.close_to_sea')],
            ['key' => "close_to_shops", 'value' => __('app.close_to_shops')],
            ['key' => "close_to_skiing", 'value' => __('app.close_to_skiing')],
            ['key' => "close_to_supermarkets", 'value' => __('app.close_to_supermarkets')],
            ['key' => "close_to_taxistand", 'value' => __('app.close_to_taxistand')],
            ['key' => "close_to_town", 'value' => __('app.close_to_town')],
            ['key' => "close_to_train", 'value' => __('app.close_to_train')],
            ['key' => "commercial_area", 'value' => __('app.commercial_area')],
            ['key' => "countryside", 'value' => __('app.countryside')],
            ['key' => "easy_access", 'value' => __('app.easy_access')],
            ['key' => "frontline_golf", 'value' => __('app.frontline_golf')],
            ['key' => "marina", 'value' => __('app.marina')],
            ['key' => "mountain_pueblo", 'value' => __('app.mountain_pueblo')],
            ['key' => "no_nearby_neighbours", 'value' => __('app.no_nearby_neighbours')],
            ['key' => "not_isolated", 'value' => __('app.not_isolated')],
            ['key' => "port", 'value' => __('app.port')],
            ['key' => "private", 'value' => __('app.private')],
            ['key' => "suburban", 'value' => __('app.suburban')],
            ['key' => "town", 'value' => __('app.town')],
            ['key' => "tranquil", 'value' => __('app.tranquil')],
            ['key' => "urbanisation", 'value' => __('app.urbanisation')],
            ['key' => "village", 'value' => __('app.village')],
        ];
    }

    public static function orientations()
    {
        return [
            ['key' => "north", 'value' => __('app.north')],
            ['key' => "north_east", 'value' => __('app.north_east')],
            ['key' => "east", 'value' => __('app.east')],
            ['key' => "south_east", 'value' => __('app.south_east')],
            ['key' => "south", 'value' => __('app.south')],
            ['key' => "south_west", 'value' => __('app.south_west')],
            ['key' => "west", 'value' => __('app.west')],
            ['key' => "north_west", 'value' => __('app.north_west')],
        ];
    }

    public static function views()
    {
        return [
            ['key' => "beach", 'value' => __('app.beach')],
            ['key' => "countryside", 'value' => __('app.countryside')],
            ['key' => "forest", 'value' => __('app.forest')],
            ['key' => "garden", 'value' => __('app.garden')],
            ['key' => "golf", 'value' => __('app.golf')],
            ['key' => "lake", 'value' => __('app.lake')],
            ['key' => "mountain", 'value' => __('app.mountain')],
            ['key' => "panoramic", 'value' => __('app.panoramic')],
            ['key' => "partial_seaviews", 'value' => __('app.partial_seaviews')],
            ['key' => "pool", 'value' => __('app.pool')],
            ['key' => "port", 'value' => __('app.port')],
            ['key' => "sea", 'value' => __('app.sea')],
            ['key' => "ski", 'value' => __('app.ski')],
            ['key' => "street", 'value' => __('app.street')],
            ['key' => "urban", 'value' => __('app.urban')],
        ];
    }

    public static function conditions()
    {
        return [
            ['key' => "excellent", 'value' => __('app.excellent')],
            ['key' => "fair", 'value' => __('app.fair')],
            ['key' => "minor_updates_required", 'value' => __('app.minor_updates_required')],
            ['key' => "good", 'value' => __('app.good')],
            ['key' => "never_lived", 'value' => __('app.never_lived')],
            ['key' => "renovation_required", 'value' => __('app.renovation_required')],
            ['key' => "recently_renovated", 'value' => __('app.recently_renovated')],
            ['key' => "recently_refurbished", 'value' => __('app.recently_refurbished')],
            ['key' => "finishing_habitable_required", 'value' => __('app.finishing_habitable_required')],
            ['key' => "basically_habitable", 'value' => __('app.basically_habitable')],
        ];
    }

    public static function parkings()
    {
        return [
            ['key' => "communal_garage", 'value' => __('app.communal_garage')],
            ['key' => "parking_communal", 'value' => __('app.parking_communal')],
            ['key' => "covered", 'value' => __('app.covered')],
            ['key' => "private", 'value' => __('app.private')],
            ['key' => "more_than_one", 'value' => __('app.more_than_one')],
        ];
        // $propertyParkings = [
        //     'garage' => __('app.'. strtolower('garage')),
        //     'open' => __('app.'. strtolower('open')),
        //     'parking_optional' => __('app.'. strtolower('parking_optional')),
        //     'private' => __('app.'. strtolower('private')),
        //     'public_parking_nearby_against_a_fee' => __('app.'. strtolower('public_parking_nearby_against_a_fee')),
        //     'parking_street' => __('app.'. strtolower('parking_street')),
        //     'underground' => __('app.'. strtolower('underground'))
        // ];
    }

    public static function pools()
    {
        return [
            ['key' => "pool_communal", 'value' => __('app.pool_communal')],
            ['key' => "pool_indoor", 'value' => __('app.pool_indoor')],
            ['key' => "pool_private", 'value' => __('app.pool_private')],
        ];
        // $propertyPools = [
        //     'childrens_pool' => __('app.'. strtolower('childrens_pool')),
        //     'covfenced_poolered' => __('app.'. strtolower('fenced_pool')),
        //     'freshwater' => __('app.'. strtolower('freshwater')),
        //     'pool_heated' => __('app.'. strtolower('pool_heated')),
        //     'ladder_access' => __('app.'. strtolower('ladder_access')),
        //     'outside_shower' => __('app.'. strtolower('outside_shower')),
        //     'outside_toilets' => __('app.'. strtolower('outside_toilets')),
        //     'roman_steps_into_pool' => __('app.'. strtolower('roman_steps_into_pool')),
        //     'soler_heated_pool' => __('app.'. strtolower('soler_heated_pool')),
        //     'room_for_pool' => __('app.'. strtolower('room_for_pool')),
        //     'sun_beds' => __('app.'. strtolower('sun_beds')),
        //     'whirlpool' => __('app.'. strtolower('whirlpool'))
        // ];
    }

    public static function gardens()
    {
        return [
            ['key' => "almond_grove", 'value' => __('app.almond_grove')],
            ['key' => "garden_communal", 'value' => __('app.garden_communal')],
            ['key' => "easy_maintenance", 'value' => __('app.easy_maintenance')],
            ['key' => "fenced", 'value' => __('app.fenced_garden')],
            ['key' => "fruit_trees_citrus", 'value' => __('app.fruit_trees_citrus')],
            ['key' => "fruit_trees_tropical", 'value' => __('app.fruit_trees_tropical')],
            ['key' => "irrigation_rights", 'value' => __('app.irrigation_rights')],
            ['key' => "landscaped", 'value' => __('app.landscaped')],
            ['key' => "Lawn", 'value' => __('app.Lawn')],
            ['key' => "olive_grove", 'value' => __('app.olive_grove')],
            ['key' => "outdoor_dining", 'value' => __('app.outdoor_dining')],
            ['key' => "playground", 'value' => __('app.playground')],
            ['key' => "plenty_of_water", 'value' => __('app.plenty_of_water')],
            ['key' => "pool_house", 'value' => __('app.pool_house')],
            ['key' => "garden_private", 'value' => __('app.garden_private')],
            ['key' => "shade_control", 'value' => __('app.shade_control')],
            ['key' => "tropical_garden", 'value' => __('app.tropical_garden')],
            ['key' => "vegetable", 'value' => __('app.vegetable')],
            ['key' => "veranda", 'value' => __('app.veranda')],
            ['key' => "vineyard", 'value' => __('app.vineyard')],
        ];
    }

    public static function furnitures()
    {
        return [
            ['key' => "fully_furnished", 'value' => __('app.fully_furnished')],
            ['key' => "part_furnished", 'value' => __('app.part_furnished')],
            ['key' => "not_furnished", 'value' => __('app.not_furnished')],
        ];
        // $propertyFurnitures = [
        //     'optional' => __('app.'. strtolower('optional')),
        // ];
    }

    /**
     *
     * Get types html
     *
     * @param    array data array e.g for options return html 
     * @param    array options array e.g array('name'=>'test','id'=>'my_id',class='my_class')
     * @return   JSON OR html
     * @use      Dropdowns::typesHTML($data, $options = [name='test'])
    */
    public static function types_html($options)
    {
        $types = self::types();
        $types = self::prepare_select_data($types, 'key', 'value');

        return self::html_select($types, $options);
    }

    /**
     *
     * Get location groups html dropdown
     *
     * @param    array options array e.g array('name'=>'test','id'=>'my_id','class'=>'my_class')
     * @return   html
     * @use      Dropdowns::location_groups_html($options = [name='test'])
    */
    public static function location_groups_html($options = array('name' => 'lg_by_key[]'))
    {
        $locationGroups = self::locationGroups();
        $locationGroups = self::prepare_select_data($locationGroups, 'key_system', 'value');

        return self::html_select($locationGroups, $options);
    }

    /**
     *
     * Get locations html dropdown
     *
     * @param    array selected_locationGroups array e.g array('0'=>'712','1'=>'714')
     * @param    array options array e.g array('name'=>'test','id'=>'my_id','class'=>'my_class')
     * @return   html
     * @use      Dropdowns::locations_html($options = [name='test'])
    */
    public static function locations_html($selected_locationGroups, $options = array('name' => 'location[]'))
    {
        $locationGroups = self::locationGroups();
        $locations = [];
        $loc = [];

        foreach ($selected_locationGroups as $selected_locationGroup) {
            foreach ($locationGroups as $locationGroup) {
                if ($selected_locationGroup == $locationGroup['key_system']) {
                    $lGroups[] = $locationGroup;
                    if (isset($locationGroup['group_value'])) {
                        $locations = self::prepare_select_data($locationGroup['group_value'], 'key', strtolower(App::getLocale()) == 'es' ? 'es_AR' : strtolower(App::getLocale()));
                    }
                }
            }
            foreach ($locations as $value) {
                $loc[] = $value;
            }
        }
        $loc = array_unique($loc, SORT_REGULAR);
        usort($loc, "self::sortedLocation");

        return self::html_select($loc, $options);
    }

    public static function sortedLocation($a, $b)
    {
        return strcmp($a["option_value"], $b["option_value"]);
    }

    /**
     *
     * Get prepared select data
     *
     * @param    array data array e.g for options return html 
     * @param    array options array e.g array('name'=>'test','id'=>'my_id','class'=>'my_class')
     * @return   html
     * @use      Dropdowns::prepare_select_data($dataArray='Data to be formated', $option_key_index='key', $option_value_index='value')
    */
    public static function prepare_select_data($dataArray, $option_key_index = 'key', $option_value_index = 'value')
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

    /**
     *
     * Get dropdown
     *
     * @param    array data array e.g for options return html 
     * @param    array options array e.g array('name' => 'ContactUs[provinces][]', 'class' => "multiselect", 'multiple' => 'multiple', 'onchange' => 'loadCities()', 'id' => 'provinces', 'placeholder' => 'Provinces', 'noValueTranslation' => true )
     * @return   html
     * @use      Dropdowns::dropdown($dataArray='Data to be formated', $options = ['name' => 'ContactUs[provinces][]'])
    */
    public static function dropdown($dataArray, $options)
    {
        $finalFormatedSelectArray = array();
        foreach ($dataArray as $key => $value) {
            $finalFormatedSelectArray[$key]['option_key'] = $key;
            $finalFormatedSelectArray[$key]['option_value'] = $value;
        }

        return self::html_select($finalFormatedSelectArray, $options);
    }

    /**
     *
     * Get html_select dropdown
     *
     * @param    array data array e.g for options return html 
     * @param    array options array e.g array('name'=>'test','id'=>'my_id',class='my_class')
     * @return   html
     * @use      Dropdowns::html_select($data, $options = [name='test'])
    */
    public static function html_select($data, $options = [])
    {
        // $path =  dirname(dirname(__FILE__));
        // $view = Yii::$app->controller->view;

        // optimaAsset::register($view);

        // $select_html = '';
        // require($path . '/views/partials/selectDropdown.php');
        // return $select_html;
    }
}