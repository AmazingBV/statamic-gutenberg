@php
    $heading = $attrs['heading'] ?? 'Hero heading';
    $text = $attrs['text'] ?? '';
    $buttonText = $attrs['buttonText'] ?? null;
    $buttonUrl = $attrs['buttonUrl'] ?? null;
@endphp

<section class="sgb-hero">
    <div class="sgb-hero__inner">
        <h1>{{ $heading }}</h1>
        @if ($text)
            <p>{{ $text }}</p>
        @endif
        @if ($buttonText && $buttonUrl)
            <a class="sgb-button" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
        @endif
    </div>
</section>
