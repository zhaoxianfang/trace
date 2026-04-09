{{-- Trace 数据项组件 --}}
@php
    $filePath = $data['file_path'] ?? '';
    $line = $data['line'] ?? 1;
    $local = $data['local'] ?? '';
    $var = $data['var'] ?? null;
    $editor = $data['editor'] ?? 'phpstorm';
@endphp

<span class="json-label">
    <a href="{{ $editor }}://open?file={{ urlencode($filePath) }}&amp;line={{ $line }}" class="phpdebugbar-link">
        {{ $local }}
    </a>
</span>

@if(is_array($var) && !empty($var))
    @include('trace::components.json-display', ['json' => json_encode($var, JSON_UNESCAPED_UNICODE)])
@elseif(is_array($var))
    <div class='json-string-content'>[]</div>
@else
    <div class='json-string-content'>
        @if(is_scalar($var) || is_null($var))
            {{ format_param($var) }}
        @else
            {{ (string) $var }}
        @endif
    </div>
@endif
