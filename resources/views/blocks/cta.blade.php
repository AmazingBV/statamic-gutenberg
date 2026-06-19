@php
    $heading = $attrs['heading'] ?? 'Call to action';
    $text = $attrs['text'] ?? '';
    $buttonText = $attrs['buttonText'] ?? 'Learn more';
    $buttonUrl = $attrs['buttonUrl'] ?? '#';
@endphp

<section class="sgb-cta">
    <div>
        <h2>{{ $heading }}</h2>
        @if ($text)
            <p>{{ $text }}</p>
        @endif
    </div>
    <a class="sgb-button" href="{{ $buttonUrl }}">{{ $buttonText }}</a>
</section>
