@php
    $options['skip_options'] = isset($options['skip_options']) ? $options['skip_options'] : [];
    $dataOptions = isset($options['data-option']) ? $options['data-option'] : [];

    $appendOptions = "";
    foreach ($dataOptions as $key => $value) {
        $appendOptions .= ' data-' . $key .'="' . $value . '"';
    }
@endphp

<select
    name="{{ isset($options['name']) ? $options['name'] : '' }}"
    id="{{ isset($options['id']) ? $options['id'] : '' }}"
    class="{{ isset($options['class']) ? $options['class'] : '' }}"
    onchange="{{ isset($options['onchange']) ? $options['onchange'] : '' }}"
    data-placeholder="{{ \Daz\OptimaClass\Components\Translate::t(isset($options['placeholder']) ? $options['placeholder'] : '') }}"
    data-nselectedtext="{{ \Daz\OptimaClass\Components\Translate::t('selected') }}"
    data-allselectedtext="{{ \Daz\OptimaClass\Components\Translate::t('All selected') }}"
    {{ isset($options['disabled']) ? $options['disabled'] : '' }}
    {{ isset($options['multiple']) ? $options['multiple'] : '' }}
    {{ isset($options['required']) ? $options['required'] : '' }}
    {{ $appendOptions }}
>
    @foreach ($data as $value)
        @if (!in_array($value['option_key'], $options['skip_options']))
            @php
                $option_name = isset($options['get_name']) ? $options['get_name'] : str_replace('[]', '', $options['name']);
            @endphp

            <option value="{{ $value['option_key'] }}" {{ (is_array(request()->input($option_name)) && in_array($value['option_key'], request()->input($option_name))) || (request()->input($option_name) == $value['option_key']) ? 'selected' : '' }}>
                {{ isset($options['noValueTranslation']) ? $value['option_value'] : ucfirst(\Daz\OptimaClass\Components\Translate::t($value['option_value'])) }}
            </option>
        @endif
    @endforeach
</select>