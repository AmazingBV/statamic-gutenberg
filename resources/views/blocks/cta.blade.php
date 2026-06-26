@php
    $heading = $attrs['heading'] ?? 'Call to action';
    $text = $attrs['text'] ?? '';
    $buttonText = $attrs['buttonText'] ?? 'Learn more';
    $buttonUrl = $attrs['buttonUrl'] ?? '#';
@endphp

<section{!! get_block_wrapper_attributes(['class' => 'sgb-custom-block sgb-custom-block--cta']) !!}>
    <div class="sgb-custom-block__content">
        <h2>{{ $heading }}</h2>
        @if ($text)
            <p>{{ $text }}</p>
        @endif
    </div>
    <a class="wp-block-button__link wp-element-button" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
</section>
