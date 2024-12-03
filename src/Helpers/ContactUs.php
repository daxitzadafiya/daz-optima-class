<?php

namespace Daz\OptimaClass\Helpers;

use Daz\OptimaClass\Requests\ContactUsRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContactUs extends Model
{
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
}
