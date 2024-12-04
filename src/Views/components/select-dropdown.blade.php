@php
    $select_html .=
        '<select 
          name="' .
        (isset($options['name']) ? $options['name'] : '') .
        '" 
          class="' .
        (isset($options['class']) ? $options['class'] : '') .
        '" 
          id="' .
        (isset($options['id']) ? $options['id'] : '') .
        '" 
          onchange="' .
        (isset($options['onchange']) ? $options['onchange'] : '') .
        '" 
          data-placeholder="' .
        \Daz\OptimaClass\Components\Translate::t(isset($options['placeholder']) ? $options['placeholder'] : '') .
        '"
          data-nselectedtext="' .
        \Daz\OptimaClass\Components\Translate::t('selected') .
        '"
          data-allselectedtext="' .
        \Daz\OptimaClass\Components\Translate::t('All selected') .
        '"
          ' .
        (isset($options['disabled']) ? $options['disabled'] : '') .
        '
          ' .
        (isset($options['multiple']) ? $options['multiple'] : '') .
        '
          ' .
        (isset($options['required']) ? $options['required'] : '') .
        '
          >';

    $options['skip_options'] = isset($options['skip_options']) ? $options['skip_options'] : [];

    foreach ($data as $value) {
        if (!in_array($value['option_key'], $options['skip_options'])) {
            if (isset($value['count']) && !empty($value['count']) && $value['count'] > 0) {
                $select_html .= '<option';
                /* [NEW]:Pass `get_name` Option to get selected values from GET params */
                $option_name = isset($options['get_name'])
                    ? $options['get_name']
                    : str_replace('[]', '', $options['name']);

                if (is_array(request()->input($option_name))) {
                    if (in_array($value['option_key'], request()->input($option_name))) {
                        $select_html .= ' selected';
                    }
                } else {
                    if (request()->input($option_name) == $value['option_key']) {
                        $select_html .= ' selected';
                    }
                }

                $select_html .= ' value="' . $value['option_key'] . '">';
                $select_html .= isset($options['noValueTranslation'])
                    ? $value['option_value']
                    : ucfirst(\Daz\OptimaClass\Components\Translate::t($value['option_value']));
                $select_html .= '</option>';
            }
        }
    }

    $select_html .= '</select>';
@endphp
