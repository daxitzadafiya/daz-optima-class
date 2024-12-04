<?php

namespace Daz\OptimaClass\Requests;

use Daz\OptimaClass\Traits\ConfigTrait;
use Daz\ReCaptcha\Facades\ReCaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ContactUsRequest extends FormRequest
{
    use ConfigTrait;

    public $name;
    public $first_name;
    public $last_name;
    public $lead_status;
    public $email;
    public $phone;
    public $home_phone;
    public $call_remember;
    public $message;
    public $redirect_url;
    public $url;
    public $attach;
    public $reference;
    public $agency_set_ref;
    public $verifyCode;
    public $transaction;
    public $property_type;
    public $bedrooms;
    public $bathrooms;
    public $pool;
    public $address;
    public $house_area;
    public $plot_area;
    public $price;
    public $price_reduced;
    public $close_to_sea;
    public $sea_view;
    public $exclusive_property;
    public $to_email;
    public $owner;
    public $source;
    public $accept_cookie_text;
    public $accept_cookie;
    public $get_updates;
    public $html_content;
    public $booking_period;
    public $guests;
    public $transaction_types;
    public $subscribe;
    public $booking_enquiry;
    public $sender_first_name;
    public $sender_last_name;
    public $sender_email;
    public $sender_phone;
    public $assigned_to;
    public $news_letter;
    public $arrival_date;
    public $buy_price_from;
    public $buy_price_to;
    public $ltrent_from_date;
    public $ltrent_price_from;
    public $ltrent_price_to;
    public $strent_price_from;
    public $strent_price_to;
    public $departure_date;
    public $contact_check_1;
    public $contact_check_2;
    public $contact_check_3;
    public $gdpr_status;
    public $cv_file;
    public $language;
    public $listing_agency_email;
    public $listing_agency_id;
    public $user_id;
    public $buyer;
    public $mobile_phone;
    public $lgroups;
    public $reCaptcha;
    public $reCaptcha3;
    public $resume;
    public $imageFiles;
    public $application;
    public $feet_setting;
    public $feet_views;
    public $sub_types;
    public $feet_categories;
    public $parking;
    public $office;
    public $p_type;
    public $year_built_from;
    public $year_built_to;
    public $built_size_from;
    public $built_size_to;
    public $plot_size_from;
    public $plot_size_to;
    public $usefull_area_from;
    public $usefull_area_to;
    public $building_style;
    public $gated_comunity;
    public $elevator;
    public $settings;
    public $orientation;
    public $views;
    public $garden;
    public $only_golf_properties;
    public $only_off_plan;
    public $buy_from_date;
    public $condition;
    public $countries;
    public $regions;
    public $provinces;
    public $cities;
    public $locations;
    public $urbanization;
    public $furniture;
    public $occupancy_status;
    public $legal_status;
    public $total_floors;
    public $mooring_type;
    public $only_projects;
    public $only_holiday_homes;
    public $only_bank_repossessions;
    public $own;
    public $custom_categories;
    public $account_alert;
    public $min_sleeps;
    public $id_number;
    public $country;
    public $postal_code;
    public $infants;
    public $appt;
    public $visit_date;
    public $title;
    public $work_phone;
    public $only_investments;
    public $only_urgent_sales;
    public $from_source;
    public $collaborator;
    public $classification;
    public $street_address;
    public $street_number;
    public $city_town;

    const SCENARIO_V3 = 'v3validation';

    public function scenarios()
    {
        // $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_V3] = ['reCaptcha3'];

        return $scenarios;
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => '',
            'mobile_phone' => '',
            'phone' => '',
            'home_phone' => '',
            'office' => '',
            'infants' => '',
            'call_remember' => '',
            'appt' => '',
            'visit_date' => '',
            'to_email' => '',
            'html_content' => '',
            'source' => '',
            'owner' => '',
            'last_name' => '',
            'lead_status' => '',
            'language' => '',
            'parking' => '',
            'redirect_url' => '',
            'attach' => '',
            'postal_code' => '',
            'reference' => '',
            'transaction' => '',
            'property_type' => '',
            'bedrooms' => '',
            'bathrooms' => '',
            'pool' => '',
            'address' => '',
            'house_area' => '',
            'plot_area' => '',
            'price' => '',
            'price_reduced' => '',
            'close_to_sea' => '',
            'sea_view' => '',
            'exclusive_property' => '',
            'accept_cookie' => '',
            'accept_cookie_text' => '',
            'get_updates' => '',
            'booking_period' => '',
            'guests' => '',
            'transaction_types' => '',
            'subscribe' => '',
            'booking_enquiry' => '',
            'sender_first_name' => '',
            'sender_last_name' => '',
            'sender_email' => '',
            'sender_phone' => '',
            'assigned_to' => '',
            'news_letter' => '',
            'arrival_date' => '',
            'buy_price_from' => '',
            'country' => '',
            'buy_price_to' => '',
            'ltrent_from_date' => '',
            'ltrent_price_from' => '',
            'ltrent_price_to' => '',
            'strent_price_from' => '',
            'strent_price_to' => '',
            'departure_date' => '',
            'contact_check_1' => '',
            'contact_check_2' => '',
            'contact_check_3' => '',
            'resume' => '',
            'imageFiles' => '',
            'application' => '',
            'cv_file' => '',
            'gdpr_status' => '',
            'buyer' => '',
            'listing_agency_email' => '',
            'listing_agency_id' => '',
            'user_id' => '',
            'lgroups' => '',
            'feet_setting' => '',
            'feet_views' => '',
            'sub_types' => '',
            'feet_categories' => '',
            'p_type' => '',
            'year_built_from' => '',
            'year_built_to' => '',
            'plot_size_from' => '',
            'plot_size_to' => '',
            'built_size_from' => '',
            'built_size_to' => '',
            'usefull_area_from' => '',
            'usefull_area_to' => '',
            'building_style' => '',
            'gated_comunity' => '',
            'elevator' => '',
            'settings' => '',
            'orientation' => '',
            'views' => '',
            'garden' => '',
            'only_golf_properties' => '',
            'only_off_plan' => '',
            'buy_from_date' => '',
            'countries' => '',
            'regions' => '',
            'provinces' => '',
            'cities' => '',
            'locations' => '',
            'urbanization' => '',
            'furniture' => '',
            'condition' => '',
            'occupancy_status' => '',
            'legal_status' => '',
            'total_floors' => '',
            'mooring_type' => '',
            'only_projects' => '',
            'only_holiday_homes' => '',
            'only_bank_repossessions' => '',
            'own' => '',
            'min_sleeps' => '',
            'id_number' => '',
            'custom_categories' => '',
            'account_alert' => '',
            'title' => '',
            'work_phone' => '',
            'only_investments' => '',
            'only_urgent_sales' => '',
            'classification' => '',
            'street_address' => '',
            'street_number' => '',
            'city_town' => '',
            'first_name' => 'required',
            // 'last_name' => 'required',
            'email' => 'required|email',
            'message' => 'required',
            'accept_cookie' => $this->isAcceptCookie() ? 'required' : 'nullable',
            'resume' => 'nullable|file|mimes:jpg,png,pdf,txt',
            'imageFiles' => 'nullable|file|mimes:jpg,png,jpeg',
            'cv_file' => 'nullable|file',
            'phone' => 'required|regex:/^\d{8,}$/',
            'reCaptcha' => $this->isReCaptchaEnabled() ? 'required' : 'nullable',
            'verifyCode' => [$this->verifyCode !== null ? 'required' : 'nullable'],
            'reCaptcha3' => $this->isReCaptchaV3Enabled() ? 'required' : 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'first_name.required' => __('app.first name cannot be blank.'),
            'last_name.required' => __('app.last name cannot be blank.'),
            'email.required' => __('app.email cannot be blank.'),
            'message.required' => __('app.message cannot be blank.'),
            'verifyCode.required' => __('app.the verification code is incorrect.'),
            'phone.regex' => __('app.please enter the valid phone number.'),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->first_name === $this->last_name) {
                $validator->errors()->add('first_name', __('app.first name and last name cannot be the same.'));
            }
        });
    }

    /**
     * Determine if the request is in the 'toAcceptCookie' state.
     *
     * @return bool
     */
    private function isAcceptCookie(): bool
    {
        return $this->accept_cookie === 'toAcceptCookie';
    }

    /**
     * Check if reCaptcha is enabled in the configuration.
     *
     * @return bool
     */
    private function isReCaptchaEnabled(): bool
    {
        self::initialize();
        return ReCaptcha::verifyResponse($this->reCaptcha, Request::ip()) && (!empty(self::$recaptcha_secret_site_key) && $this->reCaptcha !== 'nulll');
    }

    /**
     * Check if reCaptcha3 is enabled in the configuration.
     *
     * @return bool
     */
    private function isReCaptchaV3Enabled(): bool
    {
        return $this->reCaptcha3 === self::SCENARIO_V3;
    }
}
