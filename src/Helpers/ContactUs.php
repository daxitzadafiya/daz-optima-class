<?php

namespace Daxit\OptimaClass\Helpers;

use Daxit\OptimaClass\Components\Translate;
use Daxit\OptimaClass\Requests\ContactUsRequest;
use Daxit\OptimaClass\Traits\ConfigTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContactUs extends Model
{
    use ConfigTrait;

    protected $guarded = [];

    public static function validate($data): bool
    {
        // Manually call the FormRequest validation logic here
        $request = new ContactUsRequest();
        $validator = Validator::make($data, $request->rules());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    /**
     * @return array customized attribute labels
    */
    public function attributeLabels()
    {
        return [
            'verifyCode' => Translate::t(strtolower('Verification Code')),
            'first_name' => Translate::t(strtolower('First Name')),
            'last_name' => Translate::t(strtolower('Last Name')),
            'email' => Translate::t(strtolower('Email')),
            'message' => Translate::t(strtolower('Message')),
        ];
    }

    public function uploadvalidate()
    {
        if ($this->cv_file != '') {
            $this->cv_file->move(public_path('uploads'), 'uploads/' . $this->cv_file->baseName . '.' . $this->cv_file->extension);
        }
        // if ($this->validate()) {
        //     $this->cv_file->saveAs('uploads/' . $this->cv_file->baseName . '.' . $this->cv_file->extension);
        //     return true;
        // } else {
        //     return false;
        // }
    }

    public function sendMail()
    {
        self::initialize();
        //if you wanna pass email with name format will me:  Your Name[Your@email.address]
        $settings = Cms::settings();
        $htmlBody = "";

        if (isset($settings['general_settings']['admin_email']) && $settings['general_settings']['admin_email'] != '') {
            $ae_array = explode(',', $settings['general_settings']['admin_email']);

            foreach ($ae_array as $k) {
                $arr = explode('[', $k);

                if (count($arr) > 0) {
                    if (isset($arr[1])) {
                        $formatted_email = true;
                        $ae_arr = [str_replace(']', '', $arr[1]) => $arr[0]];
                        break;
                    }
                }
            }
            if (isset($formatted_email)) {
                $ae_array = $ae_arr;
            }
        }

        if (isset($ae_array) && is_array($ae_array)) {
            $from_email = $ae_array;
        } elseif (isset($ae_array[0])) {
            $from_email = trim($ae_array[0]);
        } else {
            $ae_array = explode(',', self::$from_email);
            $from_email = self::$from_email;
        }

        // if ($this->validate($this->toArray()) && isset($ae_array)) {
        if (isset($ae_array)) {
            if (isset($this->attach) && $this->attach == 1) {
                $webroot = public_path();

                if (File::exists($webroot . '/uploads/pdf')) {

                    $this->saveAccount();

                    Mail::send([], [], function ($message) use($webroot, $ae_array, $settings) {
                        // From address
                        $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                        // Recipient
                        $message->to($this->email)->subject('Thank you for contacting us');

                        $message->html(isset($settings['email_response'][strtoupper(App::getLocale())]) ? $settings['email_response'][strtoupper(App::getLocale())] : 'Thank you for contacting us');

                        $message->attach($webroot . '/uploads/pdf/property.pdf');
                    });

                    Mail::send("optima::emails.mail", ['model' => $this], function ($message) use($ae_array, $settings) {
                        // From address
                        $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                        // Recipient
                        $message->to(isset($ae_array) ? $ae_array : '')->subject(isset($settings['email_response_subject'][strtoupper(App::getLocale())]) ? $settings['email_response_subject'][strtoupper(App::getLocale())] : (isset($settings['email_response_subject'][0]) ? $settings['email_response_subject'][0]['key'] : 'Web enquiry'));
                    });

                    if (isset($this->sender_first_name) || isset($this->sender_last_name) || isset($this->sender_email) || isset($this->sender_phone)) {
                        $this->saveSenderAccount();

                        Mail::send("optima::emails.mail", ['model' => $this], function ($message) use($webroot, $ae_array) {
                            // From address
                            $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                            // Recipient
                            $message->to($this->sender_email)->subject('Suggested property');

                            $message->attach($webroot . '/uploads/pdf/property.pdf');
                        });
                    }
                }
            } else if (isset($this->subscribe) && $this->subscribe == 1) {
                $subscribe_msg = '';
                $subscribe_subject = '';
                $logo = 'https://my.optima-crm.com/uploads/cms_settings/' . $settings['_id'] . '/' . $settings['header']['logo']['name'];

                foreach ($settings['custom_settings'] as $setting) {
                    if ($setting['key'] == 'subscribe') {
                        $subscribe_msg = Translate::t($setting['value']);
                    }
                    if ($setting['key'] == 'newsletter_subject') {
                        $subscribe_subject = Translate::t($setting['value']);
                    }
                }

                $htmlBody = $subscribe_msg . '<br><br><br><br> <img style="width:40%" src=' . $logo . '> ';
                $email_response = isset($settings['email_response'][strtoupper(App::getLocale())]) ? $settings['email_response'][strtoupper(App::getLocale())] : 'Thank you for Subscribing';

                $this->saveAccount();

                Mail::send("optima::emails.mail", ['model' => $this], function ($message) use($ae_array) { // a view rendering result becomes the message body here
                    // From address
                    $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                    // Recipient
                    $message->to(isset($ae_array) ? $ae_array : '')->subject('Subscribing newsletter Email');

                    $message->html($this->email . ' would like to be added to your newsletters');
                });

                Mail::send([], [], function ($message) use($from_email, $subscribe_subject, $subscribe_msg, $htmlBody, $email_response) {
                    // From address
                    $message->from($from_email);

                    // Recipient
                    $message->to($this->email)->subject($subscribe_subject != '' ? $subscribe_subject : 'Thank you for contacting us');

                    $message->html($subscribe_msg != '' ? $htmlBody : $email_response);
                });
            } else if (isset($this->booking_enquiry) && $this->booking_enquiry == 1) {
                $html = '';
                if (isset($this->first_name) && $this->first_name != '') {
                    $html .= 'First Name: ' . $this->first_name;
                }
                if (isset($this->last_name) && $this->last_name != '') {
                    $html .= '<br>';
                    $html .= 'Last Name : ' . $this->last_name;
                }
                if (isset($this->email) && $this->email != '') {
                    $html .= '<br>';
                    $html .= 'Email: ' . $this->email;
                }
                if (isset($this->phone) && $this->phone != '') {
                    $html .= '<br>';
                    $html .= 'Phone: ' . $this->phone;
                }
                if (isset($this->language) && $this->language != '') {
                    $html .= '<br>';
                    $html .= 'Language: ' . $this->language;
                }
                if (isset($this->agency_set_ref) && $this->agency_set_ref != '') {
                    $html .= '<br>';
                    $html .= 'Prop. Ref : ' . $this->agency_set_ref;
                }
                if (!(isset($this->agency_set_ref) && $this->agency_set_ref != '') && isset($this->reference) && $this->reference != '') {
                    $html .= '<br>';
                    $html .= 'Prop.Ref : ' . $this->reference;
                }
                if (isset($this->arrival_date) && $this->arrival_date != '') {
                    $html .= '<br>';
                    $html .= 'Arrival Date : ' . $this->arrival_date;
                }
                if (isset($this->departure_date) && $this->departure_date != '') {
                    $html .= '<br>';
                    $html .= 'Departure Date : ' . $this->departure_date;
                }
                if (isset($this->guests) && $this->guests != '') {
                    $html .= '<br>';
                    $html .= 'Guests: ' . $this->guests;
                }
                if (isset($this->message) && $this->message != '') {
                    $html .= '<br>';
                    $html .= 'Message: ' . $this->message;
                }

                if (isset($this->html_content) && $this->html_content != '') {
                    $html .= '<br>';
                    $html .= 'Price: ' . $this->html_content;
                }
                $call_rememeber = '';
                if (isset($this->call_remember) && $this->call_remember == 0) {
                    $call_rememeber = '9:00 to 18:00';
                } else if (isset($this->call_remember) && $this->call_remember == 'After 18:00') {
                    $call_rememeber = 'After 18:00';
                }
                $this->saveAccount();


                Mail::send("optima::emails.mail", ['model' => $this], function ($message) use($ae_array, $html) { // a view rendering result becomes the message body here
                    // From address
                    $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                    // Recipient
                    $message->to(isset($ae_array) ? $ae_array : '')->subject('Booking Enquiry');

                    $message->html($html);
                });

                Mail::send([], [], function ($message) use($ae_array, $settings) {
                    // From address
                    $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                    // Recipient
                    $message->to($this->email)->subject('Thank you for contacting us');

                    $message->html(isset($settings['email_response'][strtoupper(App::getLocale())]) ? $settings['email_response'][strtoupper(App::getLocale())] : 'Thank you for Subscribing');
                });
            } elseif (isset($_GET['ContactUs']['file_link'])) {
                $file = $_GET['ContactUs']['file_link'];
                $subscribe_subject = '';
                $lngn = 0; //isset(App::getLocale())&& strtoupper(App::getLocale())=='ES'?1:0;

                foreach ($settings['custom_settings'] as $setting) {
                    if (isset($setting['key']) && $setting['key'] == 'enquiry_subject') {
                        $subscribe_subject = Translate::t($setting['value']);
                    }
                }
                $htmlBody = '';
                if (isset($settings['email_response'][strtoupper(App::getLocale())])) {
                    $htmlBody = $settings['email_response'][strtoupper(App::getLocale())];
                    if ($this->reference != '') {
                        $htmlBody = '<br>' . Translate::t(strtolower('Enquiry about property')) . ' (' . Translate::t(strtolower('Ref')) . ' : ' . $this->reference . ')<br><br>' . $htmlBody;
                    }
                }
                $this->saveAccount();

                Mail::send("optima::emails.mail", ['model' => $this], function ($message) use($ae_array, $settings) { // a view rendering result becomes the message body here
                    // From address
                    $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                    $message->cc(isset($this->listing_agency_email) && $this->listing_agency_email != '' ? $this->listing_agency_email : []);

                    // Recipient
                    $message->to(isset($ae_array) ? $ae_array : '')->subject(isset($settings['email_response_subject'][strtoupper(App::getLocale())]) ? $settings['email_response_subject'][strtoupper(App::getLocale())] : (isset($settings['email_response_subject'][0]) ? $settings['email_response_subject'][0]['key'] : 'Web enquiry'));
                });

                Mail::send([], [], function ($message) use($from_email, $settings, $htmlBody) {
                    // From address
                    $message->from($from_email);

                    // Recipient
                    $message->to($this->email)->subject(isset($settings['email_response_subject'][strtoupper(App::getLocale())]) ? $settings['email_response_subject'][strtoupper(App::getLocale())] : (isset($settings['email_response_subject'][0]) ? $settings['email_response_subject'][0]['key'] : 'Thank you for contacting us'));

                    $message->html((isset($htmlBody) && !empty($htmlBody)) && isset($_GET['ContactUs']['file_link']) ? "<a href=" . $_GET['ContactUs']['file_link'] . ">Download File</a><br>" . $htmlBody : 'Thank you for contacting us');
                });

                if (isset($this->sender_first_name) || isset($this->sender_last_name) || isset($this->sender_email) || isset($this->sender_phone))
                    $this->saveSenderAccount();
            } else {
                $subscribe_subject = '';
                $lngn = 0; //isset(App::getLocale())&& strtoupper(App::getLocale())=='ES'?1:0;
                if (isset($settings['custom_settings'])) {
                    foreach ($settings['custom_settings'] as $setting) {
                        if (isset($setting['key']) && $setting['key'] == 'enquiry_subject') {
                            $subscribe_subject = Translate::t($setting['value']);
                        }
                    }
                }

                if (isset($settings['email_response'][strtoupper(App::getLocale())])) {
                    $htmlBody = $settings['email_response'][strtoupper(App::getLocale())];
                    if ($this->reference != '') {
                        $htmlBody = '<br>' . Translate::t(strtolower('Enquiry about property')) . ' (' . Translate::t(strtolower('Ref')) . ' : ' . $this->reference . ')<br><br>' . $htmlBody;
                    }
                }

                $this->saveAccount();

                Mail::send("optima::emails.mail", ['model' => $this], function ($message) use($ae_array, $settings) { // a view rendering result becomes the message body here
                    // From address
                    $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                    $message->cc(isset($this->listing_agency_email) && $this->listing_agency_email != '' ? $this->listing_agency_email : []);

                    // Recipient
                    $message->to(isset($ae_array) ? $ae_array : '')->subject(isset($settings['email_response_subject'][strtoupper(App::getLocale())]) ? $settings['email_response_subject'][strtoupper(App::getLocale())] : (isset($settings['email_response_subject'][0]) ? $settings['email_response_subject'][0]['key'] : 'Web enquiry'));
                });

                Mail::send([], [], function ($message) use($ae_array, $settings, $htmlBody) {
                    // From address
                    $message->from(isset($ae_array[0]) ? trim($ae_array[0]) : self::$from_email);

                    // Recipient
                    $message->to($this->email)->subject(isset($settings['email_response_subject'][strtoupper(App::getLocale())]) ? $settings['email_response_subject'][strtoupper(App::getLocale())] : (isset($settings['email_response_subject'][0]) ? $settings['email_response_subject'][0]['key'] : 'Thank you for contacting us'));

                    $message->html(isset($htmlBody) && !empty($htmlBody) ? $htmlBody : 'Thank you for contacting us');
                });

                if (isset($this->sender_first_name) || isset($this->sender_last_name) || isset($this->sender_email) || isset($this->sender_phone))
                    $this->saveSenderAccount();
            }

            return true;
        } else {
            return false;
        }
    }

    public function saveAccount()
    {
        self::initialize();
        $settings = Cms::settings();

        $call_rememeber = '';
        if (isset($this->call_remember) && $this->call_remember == '9:00 to 18:00') {
            $call_rememeber = 'call me back:  9:00 to 18:00';
        } else if (isset($this->call_remember) && $this->call_remember == 'After 18:00') {
            $call_rememeber = 'call me back: After 18:00';
        } else if (isset($this->call_remember) && $this->call_remember != '') {
            $call_rememeber = $this->call_remember;
        }
        if ($this->owner)
            $url = self::$apiUrl . "owners/index&user_apikey=" . self::$api_key. '&json=1';
        else
            $url = self::$apiUrl . "accounts/index&user_apikey=" . self::$api_key. '&json=1';


        $fields = array(
            'f_title' => $this->title,
            'forename' => $this->first_name,
            'surname' => $this->last_name,
            'email' => $this->email,
            'office' => $this->office,
            'gdpr_status' => $this->gdpr_status,
            'source' => isset($this->source) ? $this->source : urlencode('web-client'),
            'from_source' => isset($this->from_source) ? $this->from_source : '',
            'lead_status' => isset($this->lead_status) ? $this->lead_status : '1001',
            'message' => $this->message,
            'phone' => $this->phone,
            'work_phone' => isset($this->work_phone) ? $this->work_phone : null,
            'home_phone' => isset($this->home_phone) ? $this->home_phone : null,
            'country ' => isset($this->country) ? $this->country : null,
            'appt' => isset($this->appt) ? $this->appt : null,
            'date' => isset($this->visit_date) ? $this->visit_date : null,
            'mobile_phone' => isset($this->mobile_phone) ? $this->mobile_phone : null,
            'listing_agency_id' => isset($this->listing_agency_id) ? $this->listing_agency_id : null,
            'user_id' => isset($this->user_id) ? $this->user_id : null,
            'id_number' => isset($this->id_number) ? $this->id_number : null,
            'min_sleeps' => isset($this->min_sleeps) ? $this->min_sleeps : null,
            'postal_code' => isset($this->postal_code) ? $this->postal_code : null,
            'address' => isset($this->address) ? $this->address : null,
            'property' => isset($this->reference) ? $this->reference : null,
            'classification' => isset($this->classification) ? $this->classification : null,
            'newsletter' => isset($this->news_letter) && $this->news_letter == true ? $this->news_letter : false,
            'assigned_to' => isset($this->assigned_to) ? $this->assigned_to : null,
            'rent_from_date' => isset($this->arrival_date) ? $this->arrival_date : null,
            'rent_to_date' => isset($this->departure_date) ? $this->departure_date : null,
            'types' => isset($this->property_type) ? (is_array($this->property_type) ? implode(",", $this->property_type) : $this->property_type) : null,
            'p_type' => isset($this->p_type) ? $this->p_type : null,
            'min_bedrooms' => isset($this->bedrooms) ? $this->bedrooms : null,
            'min_bathrooms' => isset($this->bathrooms) ? $this->bathrooms : null,
            'only_urgent_sales' => isset($this->only_urgent_sales) ? $this->only_urgent_sales : null,
            'budget_min' => isset($this->buy_price_from) && $this->buy_price_from != '' ? $this->buy_price_from : null,
            'budget_max' => isset($this->buy_price_to) && $this->buy_price_to != '' ? $this->buy_price_to : null,
            'long_term_rent_from_date' => isset($this->ltrent_from_date) ? $this->ltrent_from_date : null,
            'long_term_Rent_price_low' => isset($this->ltrent_price_from) ? $this->ltrent_price_from : null,
            'long_term_Rent_price_high' => isset($this->ltrent_price_to) ? $this->ltrent_price_to : null,
            'st_budget_min' => isset($this->strent_price_from) ? $this->strent_price_from : null,
            'st_budget_max' => isset($this->strent_price_to) ? $this->strent_price_to : null,
            'transaction_types' => isset($this->transaction_types) ? (is_array($this->transaction_types) ? implode(",", $this->transaction_types) : $this->transaction_types) : null,
            'legal_status' => isset($this->legal_status) ? (is_array($this->legal_status) ? implode(",", $this->legal_status) : $this->legal_status) : null,
            'to_email' => isset($settings['general_settings']['admin_email']) ? $settings['general_settings']['admin_email'] : null,
            'html_content' => isset($this->html_content) ? $this->html_content : null,
            'lgroups' => isset($this->lgroups) ? (is_array($this->lgroups) ? implode(",", $this->lgroups) : $this->lgroups) : null,
            'comments' => isset($call_rememeber) && $call_rememeber != '' ? $call_rememeber : (isset($this->message) ? $this->message : null),
            'language' => isset($this->language) ? $this->language : strtoupper(App::getLocale()),
            'sub_types' => isset($this->sub_types) ? (is_array($this->sub_types) ? implode(",", $this->sub_types) : $this->sub_types) : null,
            'feet_setting' => isset($this->feet_setting) ? (is_array($this->feet_setting) ? implode(",", $this->feet_setting) : $this->feet_setting) : null,
            'feet_categories' => isset($this->feet_categories) ? (is_array($this->feet_categories) ? implode(",", $this->feet_categories) : $this->feet_categories) : null,
            'account_alert' => isset($this->account_alert) ? $this->account_alert : false,
            'custom_categories' => isset($this->custom_categories) ? (is_array($this->custom_categories) ? implode(",", $this->custom_categories) : $this->custom_categories) : null,
            'feet_views' => isset($this->feet_views) ? (is_array($this->feet_views) ? implode(",", $this->feet_views) : $this->feet_views) : null,
            'parking' => isset($this->parking) ? (is_array($this->parking) ? implode(",", $this->parking) : $this->parking) : null,
            'pool' => isset($this->pool) ? (is_array($this->pool) ? implode(",", $this->pool) : $this->pool) : null,
            'year_built_from' => isset($this->year_built_from) ? $this->year_built_from : null,
            'year_built_to' => isset($this->year_built_to) ? $this->year_built_to : null,
            'plot_size_from' => isset($this->plot_size_from) ? $this->plot_size_from : null,
            'plot_size_to' => isset($this->plot_size_to) ? $this->plot_size_to : null,
            'built_size_to' => isset($this->built_size_to) ? $this->built_size_to : null,
            'built_size_from' => isset($this->built_size_from) ? $this->built_size_from : null,
            'usefull_area_from' => isset($this->usefull_area_from) ? $this->usefull_area_from : null,
            'usefull_area_to' => isset($this->usefull_area_to) ? $this->usefull_area_to : null,
            'building_style' => isset($this->building_style) ? (is_array($this->building_style) ? implode(",", $this->building_style) : $this->building_style) : null,
            'gated_comunity' => isset($this->gated_comunity) ? $this->gated_comunity : null,
            'only_investments' => isset($this->only_investments) ? $this->only_investments : null,
            'elevator' => isset($this->elevator) ? $this->elevator : null,
            'settings' => isset($this->settings) ? (is_array($this->settings) ? implode(",", $this->settings) : $this->settings) : null,
            'orientation' => isset($this->orientation) ? (is_array($this->orientation) ? implode(",", $this->orientation) : $this->orientation) : null,
            'views' => isset($this->views) ? (is_array($this->views) ? implode(",", $this->views) : $this->views) : null,
            'garden' => isset($this->garden) ? (is_array($this->garden) ? implode(",", $this->garden) : $this->garden) : null,
            'furniture' => isset($this->furniture) ? (is_array($this->furniture) ? implode(",", $this->furniture) : $this->furniture) : null,
            'condition' => isset($this->condition) ? (is_array($this->condition) ? implode(",", $this->condition) : $this->condition) : null,
            'only_golf_properties' => isset($this->only_golf_properties) ? $this->only_golf_properties : null,
            'own' => isset($this->own) ? $this->own : null,
            'only_off_plan' => isset($this->only_off_plan) ? $this->only_off_plan : null,
            'buy_from_date' => isset($this->buy_from_date) ? $this->buy_from_date : null,
            'countries' => isset($this->countries) ? (is_array($this->countries) ? implode(",", $this->countries) : $this->countries) : null,
            'regions' => isset($this->regions) ? (is_array($this->regions) ? implode(",", $this->regions) : $this->regions) : null,
            'provinces' => isset($this->provinces) ? (is_array($this->provinces) ? implode(",", $this->provinces) : $this->provinces) : null,
            'cities' => isset($this->cities) ? (is_array($this->cities) ? implode(",", $this->cities) : $this->cities) : null,
            'locations' => isset($this->locations) ? (is_array($this->locations) ? implode(",", $this->locations) : $this->locations) : null,
            'urbanization' => isset($this->urbanization) ? (is_array($this->urbanization) ? implode(",", $this->urbanization) : $this->urbanization) : null,
            'occupancy_status' => isset($this->occupancy_status) ? (is_array($this->occupancy_status) ? implode(",", $this->occupancy_status) : $this->occupancy_status) : null,
            'total_floors' => isset($this->total_floors) ? $this->total_floors : null,
            'only_projects' => isset($this->only_projects) ? $this->only_projects : null,
            'only_holiday_homes' => isset($this->only_holiday_homes) ? $this->only_holiday_homes : null,
            'only_bank_repossessions' => isset($this->only_bank_repossessions) ? $this->only_bank_repossessions : null,
            'mooring_type' => isset($this->mooring_type) ? (is_array($this->mooring_type) ? implode(",", $this->mooring_type) : $this->mooring_type) : null,
            'collaborator' => $this->collaborator,
            'city_town' => isset($this->city_town) ? $this->city_town : null,
            'street_address' => isset($this->street_address) ? $this->street_address : null,
            'street_number' => isset($this->street_number) ? $this->street_number : null
        );
        
        $response = Http::post($url, $fields);
        $res = $response->json();

        return $res['_id'];
    }

    public function saveSenderAccount()
    {
        self::initialize();
        $settings = Cms::settings();

        $url = self::$apiUrl . "accounts/index&user_apikey=" . self::$api_key. '&json=1';

        $fields = array(
            'forename' => isset($this->sender_first_name) ? $this->sender_first_name : null,
            'surname' => isset($this->sender_last_name) ? $this->sender_last_name : null,
            'email' => isset($this->sender_email) ? $this->sender_email : null,
            'gdpr_status' => $this->gdpr_status,
            'source' => isset($this->source) ? $this->source : urlencode('web-client'),
            'lead_status' => isset($this->lead_status) ? $this->lead_status : '1001',
            'message' => $this->message,
            'phone' => isset($this->sender_phone) ? $this->sender_phone : null,
            'property' => isset($this->reference) ? $this->reference : null,
            'transaction_types' => isset($this->transaction_types) ? (is_array($this->transaction_types) ? implode(",", $this->transaction_types) : $this->transaction_types) : null,
            'to_email' => isset($settings['general_settings']['admin_email']) ? $settings['general_settings']['admin_email'] : null,
            'html_content' => isset($this->html_content) ? $this->html_content : null,
            'comments' => isset($call_rememeber) && $call_rememeber != '' ? $call_rememeber : (isset($this->guests) ? 'Number of Guests: ' . $this->guests : null),
        );

        $response = Http::post($url, $fields);
        $res = $response->json();

        return $res['_id'];
    }

    public function collaboratorEmail()
    {
        self::initialize();
        $settings = Cms::settings();

        $url = self::$apiUrl . "accounts/index&user_apikey=" . self::$api_key. '&json=1';

        $fields = array(
            'forename' => isset($this->first_name) ? $this->first_name : null,
            'surname' => isset($this->last_name) ? $this->last_name : null,
            'email' => isset($this->email) ? $this->email : null,
            'gdpr_status' => $this->gdpr_status,
            'source' => isset($this->source) ? $this->source : urlencode('web-client'),
            'lead_status' => isset($this->lead_status) ? $this->lead_status : '1001',
            'message' => $this->message,
            'phone' => isset($this->phone) ? $this->phone : null,
            'property' => isset($this->reference) ? $this->reference : null,
            'transaction_types' => isset($this->transaction_types) ? (is_array($this->transaction_types) ? implode(",", $this->transaction_types) : $this->transaction_types) : null,
            'comments' => isset($call_rememeber) && $call_rememeber != '' ? $call_rememeber : (isset($this->guests) ? 'Number of Guests: ' . $this->guests : null),
            //For IC agency Only.
            'collaborator' => 'true',
        );

        $response = Http::asForm()->post($url, $fields);
        $res = $response->json();

        return $res['_id'];
    }

    public static function loadAccount($token)
    {
        self::initialize();
        if (!empty($token)) {
            $url = self::$apiUrl . "accounts/view&user_apikey=" . self::$api_key;

            $fields = array(
                'token' => $token,
            );

            $response = Http::asForm()->post($url, $fields);

            $res = $response->json();

            return $res;
        } else {
            return 'Please provide Account Token';
        }
    }

    public static function createUserAccount($data)
    {
        self::initialize();
        $url = self::$apiUrl . 'users/create&user_apikey=' . self::$api_key. '&json=1';

        $fields = array(
            'social_id' => isset($data['social_id']) ? $data['social_id'] : null,
            'first_name' => isset($data['first_name']) ? $data['first_name'] : null,
            'last_name' => isset($data['last_name']) ? $data['last_name'] : null,
            'company_name' => isset($data['company_name']) ? $data['company_name'] : '', //For company registration
            'tax_id' => isset($data['tax_id']) ? $data['tax_id'] : '',                   //For company registration
            'nationality' => isset($data['nationality']) ? $data['nationality'] : '',    //For company registration
            'full_name' => isset($data['full_name']) ? $data['full_name'] : '',          //For company registration
            'user_email' => isset($data['user_email']) ? $data['user_email'] : null,    //For company and user registration
            "password_hash" => isset($data['password_hash']) ? $data['password_hash'] : null, //For company and user registration
            "password_repeat" => isset($data['password_repeat']) ? $data['password_repeat'] : null, //For company and user registration
            "type" => isset($data['type']) ? $data['type'] : null,        //For company and user registration
            "company_type" => isset($data['company_type']) ? $data['company_type'] : null,        //For company and user registration
            'address' => isset($data['address']) ? $data['address'] : '', //For company agency registration
            'country' => isset($data['country']) ? $data['country'] : '', //For company agency registration
            'province' => isset($data['province']) ? $data['province'] : '', //For company agency registration
            "city" => isset($data['city']) ? $data['city'] : '', //For company agency registration
            "location" => isset($data['location']) ? $data['location'] : '', //For company agency registration
            "street" => isset($data['street']) ? $data['street'] : '', //For company agency registration
            "street_number" => isset($data['street_number']) ? $data['street_number'] : '', //For company agency registration
            "phone" => isset($data['phone']) ? $data['phone'] : null,
            "office" => isset($data['office']) ? $data['office'] : null,
            "status" => isset($data['status']) ? $data['status'] : null,
            "gdpr_status" => isset($data['gdpr_status']) ? $data['gdpr_status'] : null,
            "source" => isset($data['source']) ? $data['source'] : null,
        );

        $response = Http::post($url, $fields);

        return $response->json();
    }
}
