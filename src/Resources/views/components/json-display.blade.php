{{--
    JSON 数据显示组件
    用于在调试面板中格式化显示 JSON 数据

    使用 \zxf\Trace\Helpers\JsonRenderer 进行渲染，
    避免在 Blade 模板中定义全局函数。
--}}
@if(!empty($json))
    @php
        $data = json_decode($json, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $data = $json;
        }
    @endphp

    @if(is_array($data) || is_object($data))
        <div class="trace-json-tree">
            {!! \zxf\Trace\Helpers\JsonRenderer::renderNode($data) !!}
        </div>
    @else
        <span class="trace-json-value">{{ $data }}</span>
    @endif
@endif

<style>
.trace-json-tree {
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, Monaco, monospace;
    font-size: 12px;
    line-height: 1.6;
}

.trace-json-bracket {
    color: #e6edf3;
}

.trace-json-key {
    color: #7ee787;
}

.trace-json-string {
    color: #a5d6ff;
}

.trace-json-number {
    color: #79c0ff;
}

.trace-json-boolean {
    color: #ff7b72;
}

.trace-json-null {
    color: #ff7b72;
    font-style: italic;
}

.trace-json-colon {
    color: #8b949e;
}

.trace-json-comma {
    color: #8b949e;
}

.trace-json-children {
    padding-left: 20px;
}

.trace-json-item {
    margin: 2px 0;
}
</style>
