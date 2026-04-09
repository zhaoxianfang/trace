{{-- JSON 数据显示组件 --}}
<div class="json-arrow-pre-wrapper">
    <span class="json-arrow" onclick="toggleJson(this)" role="button" tabindex="0" aria-expanded="false">▶</span>
    <pre class="json">{{ $json ?? '{}' }}</pre>
</div>
