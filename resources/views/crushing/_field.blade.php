@php
    $kind       = $kind ?? 'text';
    $required   = $required ?? false;
    $unit       = $unit ?? '';
    $hint       = $hint ?? '';
    $id         = 'f-' . $form . '-' . $name;
    $fieldValue = old($name, $value ?? '');
    $fieldError = $errors->first($name);

    $isNumber = in_array($kind, ['number', 'integer'], true);

    $hintText = $hint;

    if ($isNumber) {
        $hintText = ($hint !== '' ? $hint . ' · ' : '')
            . ($kind === 'integer' ? 'Whole numbers only' : 'Numbers only');
    }
@endphp

<div class="field capture-field {{ !empty($full) ? 'fg-full' : '' }}">
    <label for="{{ $id }}">
        {{ $label }}

        @if($required)
            <span class="field-req">*</span>
        @else
            <span class="field-opt">optional</span>
        @endif
    </label>

    <div class="capture-control {{ $isNumber && $unit !== '' ? 'has-unit' : '' }}">
        @if($kind === 'select')
            <select
                id="{{ $id }}"
                name="{{ $name }}"
                data-capture
                data-kind="select"
                data-label="{{ $label }}"
                data-required="{{ $required ? 1 : 0 }}"
                aria-required="{{ $required ? 'true' : 'false' }}"
                aria-describedby="{{ $id }}-hint {{ $id }}-error"
                @if($fieldError) aria-invalid="true" @endif
            >
                <option value="">{{ $placeholder ?? 'Select...' }}</option>

                @foreach(($options ?? []) as $optValue => $optText)
                    <option
                        value="{{ $optValue }}"
                        @selected((string) $fieldValue === (string) $optValue)
                    >
                        {{ $optText }}
                    </option>
                @endforeach
            </select>

        @elseif($kind === 'textarea')
            <textarea
                id="{{ $id }}"
                name="{{ $name }}"
                rows="3"
                data-capture
                data-kind="text"
                data-label="{{ $label }}"
                data-required="{{ $required ? 1 : 0 }}"
                data-note-counter
                aria-required="{{ $required ? 'true' : 'false' }}"
                aria-describedby="{{ $id }}-hint {{ $id }}-count {{ $id }}-error"
                @if($fieldError) aria-invalid="true" @endif
                @if(!empty($maxlength)) maxlength="{{ $maxlength }}" @endif
                placeholder="{{ $placeholder ?? '' }}"
            >{{ $fieldValue }}</textarea>

        @else
            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ in_array($kind, ['date', 'time'], true) ? $kind : 'text' }}"
                @if($isNumber)
                    inputmode="{{ $kind === 'integer' ? 'numeric' : 'decimal' }}"
                @endif
                data-capture
                data-kind="{{ $kind }}"
                data-label="{{ $label }}"
                data-unit="{{ $unit }}"
                data-required="{{ $required ? 1 : 0 }}"
                aria-required="{{ $required ? 'true' : 'false' }}"
                aria-describedby="{{ $id }}-hint {{ $id }}-error"
                @if($fieldError) aria-invalid="true" @endif
                @isset($min) data-min="{{ $min }}" @endisset
                @isset($max) data-max="{{ $max }}" @endisset
                @if(!empty($maxlength)) maxlength="{{ $maxlength }}" @endif
                value="{{ $fieldValue }}"
                @if(!empty($readonly)) readonly aria-readonly="true" @endif
                @isset($htmlMax) max="{{ $htmlMax }}" @endisset
                autocomplete="off"
                placeholder="{{ $placeholder ?? ($isNumber ? ($required ? 'Enter value' : 'No reading') : '') }}"
            >

            @if($isNumber && $unit !== '')
                <span class="capture-unit" aria-hidden="true">{{ $unit }}</span>
            @endif
        @endif
    </div>

    <div class="capture-help">
        <div id="{{ $id }}-hint" class="field-hint">{{ $hintText }}</div>

        @if($kind === 'textarea')
            <span id="{{ $id }}-count" class="capture-counter"></span>
        @endif
    </div>

    <div
        id="{{ $id }}-error"
        class="field-error"
        data-error-for="{{ $name }}"
        role="alert"
    >{{ $fieldError }}</div>
</div>
