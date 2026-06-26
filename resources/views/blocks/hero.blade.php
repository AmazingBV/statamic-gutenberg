@php
    $heading = $attrs['heading'] ?? 'Hero heading';
    $text = $attrs['text'] ?? '';
    $buttonText = $attrs['buttonText'] ?? null;
    $buttonUrl = $attrs['buttonUrl'] ?? null;
@endphp

<section{!! get_block_wrapper_attributes(['class' => 'sgb-custom-block sgb-custom-block--hero']) !!}>
    <div class="sgb-custom-block__content">
        <h1>{{ $heading }}</h1>
        @if ($text)
            <p>{{ $text }}</p>
        @endif
        @if ($buttonText && $buttonUrl)
            <a class="wp-block-button__link" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
        @endif
    </div>
</section>
