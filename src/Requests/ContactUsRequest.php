<?php

namespace Daxit\OptimaClass\Requests;

use Daxit\OptimaClass\Components\Translate;
use Daxit\ReCaptcha\Facades\ReCaptcha;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ContactUsRequest extends FormRequest
{
    const SCENARIO_V3 = 'v3validation';

    public function scenarios()
    {
        // $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_V3] = ['reCaptcha3'];

        return $scenarios;
    }

    protected function failedValidation(Validator $validator)
    {
        $formType = request()->has('type') ? request()->input('type') : 'default';

        throw new HttpResponseException(
            Redirect::back()->withErrors($validator->errors(), $formType)->withInput()
        );
    }

     /**
     * Clean the input data before validation.
     */
    public function prepareForValidation()
    {
        if(isset($this->phone) && !empty($this->phone)){
            $this->merge([
                'phone' => str_replace(["(", ")", "-", "+", " "], '', $this->phone),
            ]);
        }
        if(isset($this->mobile_phone) && !empty($this->mobile_phone)){
            $this->merge([
                'mobile_phone' => str_replace(["(", ")", "-", "+", " "], '', $this->mobile_phone),
            ]);
        }
        if(isset($this->home_phone) && !empty($this->home_phone)){
            $this->merge([
                'home_phone' => str_replace(["(", ")", "-", "+", " "], '', $this->home_phone),
            ]);
        }
        if(isset($this->sender_phone) && !empty($this->sender_phone)){
            $this->merge([
                'sender_phone' => str_replace(["(", ")", "-", "+", " "], '', $this->sender_phone),
            ]);
        }
        if(isset($this->work_phone) && !empty($this->work_phone)){
            $this->merge([
                'work_phone' => str_replace(["(", ")", "-", "+", " "], '', $this->work_phone),
            ]);
        }
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
            'phone' => 'required|regex:/^\d{8,}$/',
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
            'other_reference' => '',
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
            // 'accept_cookie' => '',
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
            // 'resume' => '',
            // 'imageFiles' => '',
            'application' => '',
            // 'cv_file' => '',
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
            // 'phone' => 'required|regex:/^\d{8,}$/',
            'reCaptcha' => $this->isReCaptchaEnabled() ? 'required' : 'nullable',
            'verifyCode' => [$this->verifyCode !== null ? 'required' : 'nullable'],
            'reCaptcha3' => $this->isReCaptchaV3Enabled() ? 'required' : 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'first_name.required' => Translate::t('first name cannot be blank.'),
            'last_name.required' => Translate::t('last name cannot be blank.'),
            'email.required' => Translate::t('email cannot be blank.'),
            'message.required' => Translate::t('message cannot be blank.'),
            'verifyCode.required' => Translate::t('the verification code is incorrect.'),
            'phone.required' => Translate::t('phone cannot be blank.'),
            'phone.regex' => Translate::t('please enter the valid phone number.'),
            'reCaptcha.required' => Translate::t('please confirm you are not a robot by completing the reCAPTCHA!'),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->first_name === $this->last_name) {
                $validator->errors()->add('first_name', Translate::t('first name and last name cannot be the same.'));
            }

            if(isset($this->reCaptcha) && !empty($this->reCaptcha)) {
                $recaptcha_secret_site_key = config('params.recaptcha_secret_site_key');
                if(!(ReCaptcha::verifyResponse($this->reCaptcha, Request::ip()) && (!empty($recaptcha_secret_site_key) && $this->reCaptcha !== 'null'))) {
                    $validator->errors()->add('reCaptcha', Translate::t('invalid reCAPTCHA!'));
                }
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
        return isset($this->reCaptcha);
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
