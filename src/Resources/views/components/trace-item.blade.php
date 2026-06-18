{{-- Trace 数据项组件 --}}
@php
    $filePath = $data['file_path'] ?? '';
    $line = $data['line'] ?? 1;
    $local = $data['local'] ?? '';
    $basePath = $data['base_path'] ?? $filePath;
    $var = $data['var'] ?? null;
    $editor = $data['editor'] ?? 'phpstorm';
    $rightType = $data['right'] ?? '';
@endphp

<span class="json-label">{{ $local }}</span>
<span class="json-string-content" style="font-size:13px;">
    <a href="{{ $editor }}://open?file={{ urlencode($filePath) }}&amp;line={{ $line }}" class="phpdebugbar-link">
        {{ $basePath }}#{{ $line }}
    </a>
</span>

@if(is_array($var) && !empty($var))
    <span class="json-arrow-pre-wrapper json-arrow-pre-wrapper-inline">
        <span class="json-arrow" onclick="toggleJson(this)">▶</span>
        <pre class="json">{{ json_encode($var, JSON_UNESCAPED_UNICODE) }}</pre>
    </span>
@elseif(is_array($var))
    <span class='json-string-content'>[]</span>
@else
    <span class='json-string-content'>
        @if(is_scalar($var) || is_null($var))
            {{ format_param($var) }}
        @else
            {{ (string) $var }}
        @endif
    </span>
@endif

@if($rightType)
    <span class="json-right">{{ $rightType }}</span>
@endif
