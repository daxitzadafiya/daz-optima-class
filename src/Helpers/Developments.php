<?php

namespace Daxit\OptimaClass\Helpers;

use Daxit\OptimaClass\Components\Translate;
use Daxit\OptimaClass\Traits\ConfigTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;

class Developments
{
    use ConfigTrait;

    public static function findAll($query, $cache = false, $set_query = true, $options = [])
    {
        self::initialize();
        $langugesSystem = Cms::SystemLanguages();
        $lang = strtoupper(App::getLocale());
        $contentLang = $lang;

        foreach ($langugesSystem as $sysLang) {
            if ((isset($sysLang['internal_key']) && $sysLang['internal_key'] != '') && $lang == $sysLang['internal_key']) {
                $contentLang = $sysLang['key'];
            }
        }

        if ($set_query) {
            $query .= self::setQuery();
        }

        $url = self::$apiUrl . 'constructions&user=' . self::$user . $query;

        if ($cache == true) {
            $JsonData = self::DoCache($query, $url);
        } else {
            $JsonData = Functions::getCRMData($url, false);
        }

        $apiData = json_decode($JsonData);
        $return_data = [];

        if (strpos($query, "&latlng=true")) {
            return $apiData;
        }

        foreach ($apiData as $property) {
            $data = [];
            $features = [];
            $slugs = [];

            if (isset($property->total_properties)) {
                $data['total_properties'] = $property->total_properties;
            }

            if (isset($property->property->_id) && $property->property->_id != '') {
                $data['_id'] = $property->property->_id;
            }

            if (isset($property->property->reference) && $property->property->reference != '')
                $data['id'] = $property->property->reference;

            if (isset($property->property->user_reference) && $property->property->user_reference != '')
                $data['reference'] = $property->property->user_reference;

            if (isset($property->property->title->$lang) && $property->property->title->$lang != '')
                $data['title'] = $property->property->title->$lang;

            if (isset($property->property->description->$lang) && $property->property->description->$lang != '')
                $data['content'] = $property->property->description->$lang;

            if (isset($property->property->phase) && $property->property->phase != '')
                $data['phase_name'] = isset($property->property->phase['0']->phase_name) ? $property->property->phase['0']->phase_name : '';

            if (isset($property->property->phase) && $property->property->phase != '')
                $data['phase_completion_date'] = isset($property->property->phase['0']->completion_date) ? $property->property->phase['0']->completion_date : '';

            if (isset($property->property->type) && $property->property->type != '')
                $data['type'] = implode(', ', $property->property->type);

            if (isset($property->property->total_number_of_unit) && $property->property->total_number_of_unit != '')
                $data['total_number_of_unit'] = $property->property->total_number_of_unit;

            if (isset($property->property->city_name) && $property->property->city_name != '')
                $data['city_name'] = $property->property->city_name;

            if (isset($property->property->phase_low_price_from) && $property->property->phase_low_price_from != '')
                $data['price_from'] = number_format((int) $property->property->phase_low_price_from, 0, '', '.');

            if (isset($property->property->phase_heigh_price_from) && $property->property->phase_heigh_price_from != '')
                $data['price_to'] = number_format((int) $property->property->phase_heigh_price_from, 0, '', '.');

            if (isset($property->property->bedrooms_from) && $property->property->bedrooms_from > 0) {
                $data['bedrooms_from'] = $property->property->bedrooms_from;
            }

            if (isset($property->property->bedrooms_to) && $property->property->bedrooms_to > 0) {
                $data['bedrooms_to'] = $property->property->bedrooms_to;
            }

            if (isset($property->property->bathrooms_from) && $property->property->bathrooms_from > 0) {
                $data['bathrooms_from'] = $property->property->bathrooms_from;
            }

            if (isset($property->property->bathrooms_to) && $property->property->bathrooms_to > 0) {
                $data['bathrooms_to'] = $property->property->bathrooms_to;
            }

            if (isset($property->property->own) && $property->property->own == true && isset($property->agency_logo) && !empty($property->agency_logo)) {
                $data['agency_logo'] = 'https://images.optima-crm.com/agencies/' . (isset($property->agency_logo->_id) ? $property->agency_logo->_id : '') . '/' . (isset($property->agency_logo->logo->name) ? $property->agency_logo->logo->name : '');
            } elseif (isset($property->agency_logo) && !empty($property->agency_logo)) {
                $data['agency_logo'] = 'https://images.optima-crm.com/companies/' . (isset($property->agency_logo->_id) ? $property->agency_logo->_id : '') . '/' . (isset($property->agency_logo->logo->name) ? $property->agency_logo->logo->name : '');
            }

            if (isset($property->property->built_size_from) && $property->property->built_size_from > 0) {
                $data['built_from'] = $property->property->built_size_from;
            }

            if (isset($property->property->built_size_to) && $property->property->built_size_to > 0) {
                $data['built_to'] = $property->property->built_size_to;
            }

            if (isset($property->property->location) && $property->property->location != '') {
                $data['location'] = $property->property->location;
            }

            if (isset($property->property->latitude)) {
                $data['lat'] = $property->property->latitude;
            }

            if (isset($property->property->longitude)) {
                $data['lng'] = $property->property->longitude;
            }

            if (isset($property->attachments) && count($property->attachments) > 0) {
                $attachments_size = isset($options['images_size']) && !empty($options['images_size']) ? $options['images_size'] . '/' : '1200/';
                $attachments = [];

                foreach ($property->attachments as $pic) {
                    $attachments[] = self::$dev_img . '/' . $pic->model_id . '/' . $attachments_size . $pic->file_md5_name;
                }

                $data['attachments'] = $attachments;
            }

            foreach ($langugesSystem as $lang_sys) {
                $lang_sys_key = $lang_sys['key'];
                $lang_sys_internal_key = isset($lang_sys['internal_key']) ? $lang_sys['internal_key'] : '';

                if (isset($property->property->perma_link->$lang_sys_key) && $property->property->perma_link->$lang_sys_key != '') {
                    $slugs[$lang_sys_internal_key] = $property->property->perma_link->$lang_sys_key;
                } else if (isset($property->property->title->$lang_sys_key) && $property->property->title->$lang_sys_key != '') {
                    $slugs[$lang_sys_internal_key] = $property->property->title->$lang_sys_key;
                }
            }

            $data['slug_all'] = $slugs;
            $return_data[] = $data;
        }

        return $return_data;
    }

    public static function findOne($reference, $options = [])
    {
        self::initialize();
        $langugesSystem = Cms::SystemLanguages();
        $lang = strtoupper(App::getLocale());
        $agency = self::$agency;
        $get = Functions::mergeRequest( $_GET ?? []);
        $contentLang = $lang;

        foreach ($langugesSystem as $sysLang) {
            if ((isset($sysLang['internal_key']) && $sysLang['internal_key'] != '') && $lang == $sysLang['internal_key']) {
                $contentLang = $sysLang['key'];
            }
        }

        $ref = $reference;
        $url = self::$apiUrl . 'constructions/view-by-ref&user=' . self::$user . '&ref=' . $ref;
        $development_status = (isset($get['status']) && !empty($get['status']) ? $get['status'] : (isset(self::$status) && !empty(self::$status) ? self::$status : []));

        foreach ($development_status as $status) {
            $url .= '&status[]=' . $status;
        }

        if(isset($get['model']) && !empty($get['model'])){
            $url .= '&model='.$get['model'];
        }

        // only_similar (only similar/with their units), exclude_similar (one per group + all not part of group), include_similar (all properties)
        if(isset($get['similar_commercials']) && !empty($get['similar_commercials'])) {
            $url .= '&similar_commercials='.$get['similar_commercials'];
        } else {
            $url .= '&similar_commercials='.config('params.similar_commercials', 'include_similar');
        }

        $headers = [
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ];

        if ($clientIp = Request::ip()) {
            $headers[] = 'x-forwarded-for: ' . $clientIp;
        }

        $JsonData = Functions::getCRMData($url, false, [], false, $headers);
        $property = json_decode($JsonData);

        $return_data = [];
        $attachments = [];
        $floor_plans = [];
        $home_staging = [];
        $quality_specifications = [];
        $settings = Cms::settings();

        if (isset($property->property->_id))
            $return_data['_id'] = $property->property->_id;
        if (isset($settings['general_settings']['reference']) && $settings['general_settings']['reference'] != 'reference') {
            $ref = $settings['general_settings']['reference'];
            if ($ref == 'external_reference') {
                $return_data['reference'] = $property->property->user_reference;
            } elseif($ref == 'other_reference') {
                $return_data['reference'] = $property->property->agency_reference;
            }
        } else {
            $return_data['reference'] = $property->property->reference;
        }
        if (isset($property->property->reference) && $property->property->reference != '')
            $return_data['id'] = $property->property->reference;

        if (isset($property->property->title->$lang) && $property->property->title->$lang != '')
            $return_data['title'] = $property->property->title->$lang;
        else
            $return_data['title'] = 'N/A';

        if (isset($property->property->city) && $property->property->city != '')
            $return_data['city'] = $property->property->city;

        if (isset($property->property->phase_low_price_from) && $property->property->phase_low_price_from != '')
            $return_data['price_from'] = number_format((int) $property->property->phase_low_price_from, 0, '', '.');

        if (isset($property->property->phase_heigh_price_from) && $property->property->phase_heigh_price_from != '')
            $return_data['price_to'] = number_format((int) $property->property->phase_heigh_price_from, 0, '', '.');

        if (isset($property->property->description->$lang))
            $return_data['description'] = $property->property->description->$lang;
        if ((isset($property->property->alternative_latitude) && $property->property->alternative_latitude != '') && (isset($property->property->alternative_longitude) && $property->property->alternative_longitude != '')) {
            if (isset($property->property->alternative_latitude))
                $return_data['lat'] = $property->property->alternative_latitude;
            if (isset($property->property->alternative_longitude))
                $return_data['lng'] = $property->property->alternative_longitude;
        } else {
            if (isset($property->property->latitude))
                $return_data['lat'] = $property->property->latitude;
            if (isset($property->property->longitude))
                $return_data['lng'] = $property->property->longitude;
        }
        if (isset($property->property->location)) {
            $return_data['location'] = $property->property->location;
            $return_data['location_key'] = isset($property->property->location_key) ? $property->property->location_key : '';
        }
        if (isset($property->property->province)) {
            $return_data['province'] = $property->property->province;
        }
        if (isset($property->property->bedrooms_from) && $property->property->bedrooms_from > 0) {
            $return_data['bedrooms_from'] = $property->property->bedrooms_from;
        }
        if (isset($property->property->bedrooms_to) && $property->property->bedrooms_to > 0) {
            $return_data['bedrooms_to'] = $property->property->bedrooms_to;
        }
        if (isset($property->property->plot_size_from) && $property->property->plot_size_from != '') {
            $return_data['plot_size_from'] = $property->property->plot_size_from;
        }
        if (isset($property->property->plot_size_to) && $property->property->plot_size_to != '') {
            $return_data['plot_size_to'] = $property->property->plot_size_to;
        }
        if (isset($property->property->terrace_from) && $property->property->terrace_from != '') {
            $return_data['terrace_from'] = $property->property->terrace_from;
        }
        if (isset($property->property->terrace_to) && $property->property->terrace_to != '') {
            $return_data['terrace_to'] = $property->property->terrace_to;
        }
        if (isset($property->property->total_number_of_unit) && $property->property->total_number_of_unit != ''){
            $return_data['total_number_of_unit'] = $property->property->total_number_of_unit;
        }
        if (isset($property->property->phase) && $property->property->phase != ''){
            $return_data['phase_completion_date'] = isset($property->property->phase['0']->completion_date) ? $property->property->phase['0']->completion_date : '';
        }
        if (isset($property->property->bathrooms_from) && $property->property->bathrooms_from > 0) {
            $return_data['bathrooms_from'] = $property->property->bathrooms_from;
        }
        if (isset($property->property->bathrooms_to) && $property->property->bathrooms_to > 0) {
            $return_data['bathrooms_to'] = $property->property->bathrooms_to;
        }
        if (isset($property->property->built_size_from) && $property->property->built_size_from > 0) {
            $return_data['built_size_from'] = $property->property->built_size_from;
        }
        if (isset($property->property->built_size_to) && $property->property->built_size_to > 0) {
            $return_data['built_size_to'] = $property->property->built_size_to;
        }
        if (isset($property->property->videos) && $property->property->videos > 0) {
            $return_data['videos'] = $property->property->videos;
        }

        if (isset($property->property->own) && $property->property->own == true && isset($property->agency_logo) && !empty($property->agency_logo)) {
            $return_data['agency_logo'] = 'https://images.optima-crm.com/agencies/' . (isset(self::$agency) ? self::$agency : '') . '/' . (isset($property->agency_logo->logo->name) ? $property->agency_logo->logo->name : '');
        } elseif (isset($property->agency_logo) && !empty($property->agency_logo)) {
            $return_data['agency_logo'] = 'https://images.optima-crm.com/companies/' . (isset(self::$agency) ? self::$agency : '') . '/' . (isset($property->agency_logo->logo->name) ? $property->agency_logo->logo->name : '');
        }
        $attachments_size = isset($options['images_size']) && !empty($options['images_size']) ? $options['images_size'] . '/' : '1200/';
        if (isset($property->attachments) && count($property->attachments) > 0) {
            foreach ($property->attachments as $pic) {
                $attachments[] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size. $pic->file_md5_name;
            }
            $return_data['attachments'] = $attachments;
        }
        if (isset($property->identification_type_images) && count($property->identification_type_images) > 0) {
            foreach ($property->identification_type_images as $pic) {
                if(isset($pic->identification_type) && $pic->identification_type == '104' ){
                    $home_staging[0]['before'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                 if(isset($pic->identification_type) && $pic->identification_type == '106' ){
                    $home_staging[1]['before'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                if(isset($pic->identification_type) && $pic->identification_type == '107' ){
                    $home_staging[2]['before'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                if(isset($pic->identification_type) && $pic->identification_type == '108' ){
                    $home_staging[3]['before'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                if(isset($pic->identification_type) && $pic->identification_type == '105' ){
                    $home_staging[0]['after'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                if(isset($pic->identification_type) && $pic->identification_type == '109' ){
                    $home_staging[1]['after'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                if(isset($pic->identification_type) && $pic->identification_type == '110' ){
                    $home_staging[2]['after'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                if(isset($pic->identification_type) && $pic->identification_type == '111' ){
                    $home_staging[3]['after'] = self::$dev_img . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
            }
            $return_data['home_staging'] = $home_staging;
        }

        if (isset($property->documents) && count($property->documents) > 0) {
            foreach ($property->documents as $pic) {
                if (isset($pic->identification_type) && $pic->identification_type == 'FP') {

                    if (isset(self::$constructions_doc_url)) {
                        $floor_plans[] = array(
                            'url' => self::$constructions_doc_url . '/' . $pic->model_id . '/' . $pic->file_md5_name,
                            'name' => (isset($pic->file_name)) ? $pic->file_name : '',
                            'description' => (isset($pic->description)) ? $pic->description : ''
                        );
                    }
                }

            }

            $return_data['floor_plans'] = $floor_plans;
        }

        if (isset($property->documents) && count($property->documents) > 0) {
            foreach ($property->documents as $pic) {
                if (isset($pic->identification_type) && $pic->identification_type == 'QS') {
                    if (isset(self::$constructions_doc_url))
                        $quality_specifications[] = self::$constructions_doc_url . '/' . $pic->model_id . '/' . $pic->file_md5_name;
                }
            }
            $return_data['quality_specifications'] = $quality_specifications;
        }
        if (isset($property->property->phase) && count($property->property->phase) > 0) {
            $phases = [];
            foreach ($property->property->phase as $phase) {
                $arr = [];
                if (isset($phase->phase_name) && $phase->phase_name != '') {
                    $arr['phase_name'] = $phase->phase_name;
                }
                if (isset($phase->price_from) && $phase->price_from != '') {
                    $arr['price_from'] = $phase->price_from;
                }
                if (isset($phase->price_to) && $phase->price_to != '') {
                    $arr['price_to'] = $phase->price_to;
                }
                if (isset($phase->tq) && count($phase->tq) > 0) {
                    $all_types = Dropdowns::types();
                    $types = [];
                    foreach ($phase->tq as $tq) {
                        if (isset($tq->type) && $tq->type != '') {
                            foreach ($all_types as $type) {
                                if ($type['key'] == $tq->type)
                                    $types[] = isset($type['value'][strtolower($contentLang)]) ? $type['value'][strtolower($contentLang)] : (isset($type['value']['en']) ? $type['value']['en'] : '');
                            }
                        }
                    }
                    $arr['types'] = $types;
                }
                $phases[] = $arr;
            }
            $return_data['phases'] = $phases;
        }
        $features = [];
        $setting = [];
        $views = [];
        if (isset($property->property->setting)) {
            foreach ($property->property->setting as $key => $value) {
                if ($value == true)
                    $setting[] = ucfirst(str_replace('_', ' ', $key));
            }
        }
        if (isset($property->property->views)) {
            foreach ($property->property->views as $key => $value) {
                if ($value == true)
                    $views[] = ucfirst(str_replace('_', ' ', $key));
            }
        }
        if (isset($property->property->general_features)) {
            foreach ($property->property->general_features as $key => $value) {
                if (is_array($value)) {
                    if (($key == 'kitchens' || $key == 'floors' || $key == 'furniture') && $value != []) {
                        foreach ($value as $val) {
                            $gen_feature[] = Translate::t($val);
                            $value = implode(', ', $gen_feature);
                        }
                    }
                }else{
                    if ($key == 'kitchens' && $value != '') {
                        $features[] = Translate::t('kitchens') . ': ' . $value;
                    }
                    if ($key == 'floors' && $value != '') {
                        $features[] = Translate::t('floors') . ': ' . $value;
                    }
                    if ($key == 'furniture' && $value != 'No') {
                        $features[] = Translate::t('furniture') . ': ' . $value;
                    } else {
                        if ($value == true && $key != 'furniture' && $key != 'kitchens' && $key != 'floors') {
                            $features[] = ucfirst(str_replace('_', ' ', $key));
                        }
                    }
                }
            }
        }
        $properties = [];
        foreach ($property->properties as $key => $value) {
            $data = [];
            if (isset($value->property->sale) && $value->property->sale == 1)
                $data['sale'] = $value->property->sale;
            if (isset($value->property->rent) && $value->property->rent == 1)
                $data['rent'] = $value->property->rent;
            if (isset($value->property->oldprice->price_on_demand) && $value->property->oldprice->price_on_demand == true)
                $data['price_on_demand'] = true;
            if (isset($value->property->currentprice) && $value->property->currentprice > 0)
                $data['currentprice'] = str_replace(',', '.', (number_format((int) ($value->property->currentprice))));
            if (isset($value->property->price_from) && $value->property->price_from > 0)
                $data['price_from'] = str_replace(',', '.', (number_format((int) ($value->property->price_from))));
            if (isset($value->property->price_to) && $value->property->price_to > 0)
                $data['price_to'] = str_replace(',', '.', (number_format((int) ($value->property->price_to))));
            if (isset($value->property->plot) && $value->property->plot > 0)
                $data['plot'] = str_replace(',', '.', (number_format((int) ($value->property->plot))));
            if (isset($value->property->bedrooms) && $value->property->bedrooms > 0)
                $data['bedrooms'] = str_replace(',', '.', (number_format((int) ($value->property->bedrooms))));
            if (isset($value->property->bathrooms) && $value->property->bathrooms > 0)
                $data['bathrooms'] = str_replace(',', '.', (number_format((int) ($value->property->bathrooms))));
            if (isset($value->property->type_one))
                $data['type'] = $value->property->type_one;
            if (isset($value->property->property_name))
                $data['property_name'] = $value->property->property_name;
            if (isset($value->property->block))
                $data['block'] = $value->property->block;
            if (isset($value->property->portal))
                $data['portal'] = $value->property->portal;
            if (isset($value->property->status))
                $data['status'] = $value->property->status;
            if (isset($value->property->plot))
                $data['plot'] = $value->property->plot;
            if (isset($value->property->floors->floor))
                $data['floor'] = $value->property->floors->floor;
            if (isset($value->property->private_info_object->$agency->apartment_no))
                $data['apartment_no'] = $value->property->private_info_object->$agency->apartment_no;
            if (isset($value->property->terrace))
                $data['terrace'] = $value->property->terrace;
            if (isset($value->property->built))
                $data['built'] = $value->property->built;
            if (isset($value->property->location))
                $data['location'] = $value->property->location;
            if (isset($value->property->address_city))
                $data['city'] = $value->property->address_city;
            if (isset($value->property->reference))
                $data['id'] = $value->property->reference;
            if (isset($value->property->year_built))
                $data['year_built'] = $value->property->year_built;
            if (isset($value->property->new_construction) && $value->property->new_construction == true)
                $data['new_construction'] = $value->property->new_construction;
            if (isset($value->property->description->$lang))
                $data['description'] = $property->property->description->$lang;

            if (isset($value->documents)) {
                $fplans = [];
                foreach ($value->documents as $pic) {
                    if (isset($pic->identification_type) && $pic->identification_type == 'FP') {
                        if (isset(self::$floor_plans_url))
                            $fplans[] = self::$floor_plans_url . '/' . $pic->model_id . '/' . $pic->file_md5_name;
                    }
                }
                $data['floor_plans'] = $fplans;
            }
            if (isset($value->property->title->$lang) && $value->property->title->$lang != '')
                $data['title'] = $value->property->title->$lang;
            else if (isset($value->property->location))
                $data['title'] = Translate::t($value->property->type_one) . ' ' . Translate::t('in') . ' ' . Translate::t($value->property->location);
            if (isset($value->attachments)) {
                $attachments = [];
                foreach ($value->attachments as $pic) {
                    $attachments[] = self::$img_url . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                }
                $data['attachments'] = $attachments;
            }
            $properties[] = $data;
        }
        // commercial properties
        $commercial_properties = [];
        if(isset($get['model']) && !empty($get['model'])){
            foreach ($property->properties as $key => $value) {
                $data = [];
                if (isset($value->property->sale) && $value->property->sale == 1)
                    $data['sale'] = $value->property->sale;
                if (isset($value->property->rent) && $value->property->rent == 1)
                    $data['rent'] = $value->property->rent;
                if (isset($value->property->currentprice) && $value->property->currentprice > 0){
                    $data['currentprice'] = str_replace(',', '.', (number_format((int) ($value->property->currentprice))));
                }elseif(isset($value->property->current_price) && $value->property->current_price > 0){
                    $data['currentprice'] = str_replace(',', '.', (number_format((int) ($value->property->current_price))));
                }
                if (isset($value->property->price_from) && $value->property->price_from > 0)
                    $data['price_from'] = str_replace(',', '.', (number_format((int) ($value->property->price_from))));
                if (isset($value->property->price_to) && $value->property->price_to > 0)
                    $data['price_to'] = str_replace(',', '.', (number_format((int) ($value->property->price_to))));
                if (isset($value->property->plot) && $value->property->plot > 0)
                    $data['plot'] = str_replace(',', '.', (number_format((int) ($value->property->plot))));
                if (isset($value->property->bedrooms) && $value->property->bedrooms > 0)
                    $data['bedrooms'] = str_replace(',', '.', (number_format((int) ($value->property->bedrooms))));
                if (isset($value->property->bathrooms) && $value->property->bathrooms > 0)
                    $data['bathrooms'] = str_replace(',', '.', (number_format((int) ($value->property->bathrooms))));
                if (isset($value->property->type_one))
                    $data['type'] = $value->property->type_one;
                if (isset($value->property->property_name))
                    $data['property_name'] = $value->property->property_name;
                if (isset($value->property->block))
                    $data['block'] = $value->property->block;
                if (isset($value->property->portal))
                    $data['portal'] = $value->property->portal;
                if (isset($value->property->status))
                    $data['status'] = $value->property->status;
                if (isset($value->property->plot))
                    $data['plot'] = $value->property->plot;
                if (isset($value->property->terrace))
                    $data['terrace'] = $value->property->terrace;
                if (isset($value->property->built))
                    $data['built'] = $value->property->built;
                if (isset($value->property->location))
                    $data['location'] = $value->property->location;
                if (isset($value->property->address_city))
                    $data['city'] = $value->property->address_city;
                if (isset($value->property->reference))
                    $data['id'] = $value->property->reference;
                if (isset($value->property->year_built))
                    $data['year_built'] = $value->property->year_built;
                if (isset($value->property->new_construction) && $value->property->new_construction == true)
                    $data['new_construction'] = $value->property->new_construction;
                if (isset($value->property->description->$lang))
                    $data['description'] = $property->property->description->$lang;

                if (isset($value->documents)) {
                    $fplans = [];
                    foreach ($value->documents as $pic) {
                        if (isset($pic->identification_type) && $pic->identification_type == 'FP') {
                            if (isset(self::$floor_plans_url))
                                $fplans[] = self::$floor_plans_url . '/' . $pic->model_id . '/' . $pic->file_md5_name;
                        }
                    }
                    $data['floor_plans'] = $fplans;
                }
                if (isset($value->property->title->$lang) && $value->property->title->$lang != '')
                    $data['title'] = $value->property->title->$lang;
                else if (isset($value->property->location))
                    $data['title'] = Translate::t($value->property->type_one) . ' ' . Translate::t('in') . ' ' . Translate::t($value->property->location);
                if (isset($value->attachments)) {
                    $attachments = [];
                    foreach ($value->attachments as $pic) {
                        $attachments[] = self::$img_url . '/' . $pic->model_id . '/'.$attachments_size . $pic->file_md5_name;
                    }
                    $data['attachments'] = $attachments;
                }
                $commercial_properties[] = $data;
            }
        }
        //        start slug_all
        $slugs = [];
        foreach ($langugesSystem as $lang_sys) {
            $lang_sys_key = $lang_sys['key'];
            $lang_sys_key = $lang_sys['key'];
            if (!isset($lang_sys['internal_key']))
                continue;
            $lang_sys_internal_key = $lang_sys['internal_key'];
            if (isset($property->property->perma_link->$lang_sys_key) && $property->property->perma_link->$lang_sys_key != '') {
                $slugs[$lang_sys_internal_key] = $property->property->perma_link->$lang_sys_key;
            } else if (isset($property->property->title->$lang_sys_key) && $property->property->title->$lang_sys_key != '') {
                $slugs[$lang_sys_internal_key] = $property->property->title->$lang_sys_key;
            }
        }
        $distances=[];
        if (isset($property->property->distance_airport) && count((array) $property->property->distance_airport) > 0 && isset($property->property->distance_airport->value) && $property->property->distance_airport->value > 0) {
            $distances['distance_airport'] = $property->property->distance_airport->value . ' ' . (isset($property->property->distance_airport->unit) ? $property->property->distance_airport->unit : 'km');
        }
        if (isset($property->property->distance_beach) && count((array) $property->property->distance_beach) > 0 && isset($property->property->distance_beach->value) && $property->property->distance_beach->value > 0) {
            $distances['distance_beach'] = $property->property->distance_beach->value . ' ' . (isset($property->property->distance_beach->unit) ? $property->property->distance_beach->unit : 'km');
        }
        if (isset($property->property->distance_golf) && count((array) $property->property->distance_golf) > 0 && isset($property->property->distance_golf->value) && $property->property->distance_golf->value > 0) {
            $distances['distance_golf'] = $property->property->distance_golf->value . ' ' . (isset($property->property->distance_golf->unit) ? $property->property->distance_golf->unit : 'km');
        }
        if (isset($property->property->distance_restaurant) && count((array) $property->property->distance_restaurant) > 0 && isset($property->property->distance_restaurant->value) && $property->property->distance_restaurant->value > 0) {
            $distances['distance_restaurant'] = $property->property->distance_restaurant->value . ' ' . (isset($property->property->distance_restaurant->unit) ? $property->property->distance_restaurant->unit : 'km');
        }
        if (isset($property->property->distance_sea) && count((array) $property->property->distance_sea) > 0 && isset($property->property->distance_sea->value) && $property->property->distance_sea->value > 0) {
            $distances['distance_sea'] = $property->property->distance_sea->value . ' ' . (isset($property->property->distance_sea->unit) ? $property->property->distance_sea->unit : 'km');
        }
        if (isset($property->property->distance_supermarket) && count((array) $property->property->distance_supermarket) > 0 && isset($property->property->distance_supermarket->value) && $property->property->distance_supermarket->value > 0) {
            $distances['distance_supermarket'] = $property->property->distance_supermarket->value . ' ' . (isset($property->property->distance_supermarket->unit) ? $property->property->distance_supermarket->unit : 'km');
        }
        if (isset($property->property->distance_next_town) && count((array) $property->property->distance_next_town) > 0 && isset($property->property->distance_next_town->value) && $property->property->distance_next_town->value > 0) {
            $distances['distance_next_town'] = $property->property->distance_next_town->value . ' ' . (isset($property->property->distance_next_town->unit) ? $property->property->distance_next_town->unit : 'km');
        }
        $return_data['slug_all'] = $slugs;
        //        end slug_all
        $return_data['property_features'] = [];
        $return_data['property_features']['features'] = $features;
        $return_data['property_features']['setting'] = $setting;
        $return_data['property_features']['views'] = $views;
        $return_data['property_features']['distances'] = $distances;
        $return_data['properties'] = $properties;
        $return_data['commercial_properties'] = $commercial_properties;

        return $return_data;
    }

    public static function setQuery()
    {
        $get = Functions::mergeRequest( $_GET ?? []);
        $query = '';

        if (isset($get["province"]) && $get["province"] != "") {
            if (is_array($get["province"]) && count($get["province"])) {
                foreach ($get["province"] as $value) {
                    if ($value != '')
                        $query .= '&address_province[]=' . $value;
                }
            }
        }

        if (isset($get["location"]) && $get["location"] != "") {
            if (is_array($get["location"]) && count($get["location"])) {
                foreach ($get["location"] as $value) {
                    if ($value != '')
                        $query .= '&location[]=' . $value;
                }
            }
        }

        if (isset($get["type"]) && is_array($get["type"]) && $get["type"] != "") {
            foreach ($get["type"] as $key => $value) {
                if ($value != '')
                    $query .= '&type[]=' . $value;
            }
        }

        if (isset($get["location_group"]) && is_array($get["location_group"]) && count($get["location_group"]) > 0) {
            foreach ($get["location_group"] as $key => $value) {
                $query .= '&location_group[]=' . $value;
            }
        }

        if (isset($get["bedrooms"]) && $get["bedrooms"] != "") {
            $query .= '&bedrooms[]=' . $get["bedrooms"] . '&bedrooms[]=50';
        }

        if (isset($get["bathrooms"]) && $get["bathrooms"] != "") {
            $query .= '&bathrooms[]=' . $get["bathrooms"] . '&bathrooms[]=50';
        }

        if (isset($get["orderby"]) && !empty($get["orderby"]) && is_array($get["orderby"])) {
            foreach ($get['orderby'] as $order) {
                $query .= '&orderby[]=phase_low_price_from&orderby[]=ASC';
            }
        }

        if (isset($get["price_from"]) && $get["price_from"] != "") {
            $query .= '&phase_low_price_from=' . $get["price_from"];
        }

        if (isset($get["price_from"]) && $get["price_from"] == "" && isset($get["price_to"]) && $get["price_to"] != "") {
            $query .= '&phase_low_price_from=0';
        }

        if (isset($get["price_to"]) && $get["price_to"] != "") {
            $query .= '&phase_heigh_price_from=' . $get["price_to"];
        }

        if (isset($get["price_to"]) && $get["price_to"] == "" && $get["price_from"] != "") {
            $query .= '&phase_heigh_price_from=100000000';
        }

        if (isset($get["orientation"]) && $get["orientation"] != "") {
            $query .= '&orientation[]=' . $get['orientation'];
        }

        if (isset($get["usefull_area"]) && $get["usefull_area"] != "") {
            $query .= '&usefull_area=' . $get['usefull_area'];
        }

        if (isset($get["communal_pool"]) && $get["communal_pool"] != "" && $get["communal_pool"]) {
            $query .= '&pool[]=pool_communal';
        }

        if (isset($get["new_property"]) && $get["new_property"] != "" && $get["new_property"]) {
            $query .= '&conditions[]=never_lived';
        }

        if (isset($get["reference"]) && $get["reference"] != "") {
            $query .= '&reference=' . $get['reference'];
        }

        return $query;
    }

    public static function DoCache($query, $url)
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

        $file = $tempDirectory . '/develop_' . $query . '.json';

        if (!file_exists($file) || (file_exists($file) && time() - filemtime($file) > 2 * 3600)) {
            $headers = [];
            $clientIp = \Illuminate\Support\Facades\Request::ip();
            if ($clientIp) {
                $headers[] = 'x-forwarded-for: ' . $clientIp;
            }
            $file_data = Functions::getCRMData($url, false, [], false, $headers);
            file_put_contents($file, $file_data);
        } else {
            $file_data = file_get_contents($file);
        }

        return $file_data;
    }
}
