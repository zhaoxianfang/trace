{{--
    JSON 数据显示组件
    用于在调试面板中格式化显示 JSON 数据
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
            {!! render_json_node($data) !!}
        </div>
    @else
        <span class="trace-json-value">{{ $data }}</span>
    @endif
@endif

@php
function render_json_node($data, $level = 0) {
    $indent = str_repeat('  ', $level);
    $html = '';
    
    if (is_array($data)) {
        if (empty($data)) {
            return '<span class="trace-json-bracket">[]</span>';
        }
        
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        
        if (!$isAssoc && count($data) <= 3) {
            // 简单数组内联显示
            $items = array_map(function($item) {
                return render_json_value($item);
            }, $data);
            return '<span class="trace-json-bracket">[</span>' . implode('<span class="trace-json-comma">, </span>', $items) . '<span class="trace-json-bracket">]</span>';
        }
        
        $html .= '<span class="trace-json-bracket">' . ($isAssoc ? '{' : '[') . '</span>';
        $html .= '<div class="trace-json-children">';
        
        foreach ($data as $key => $value) {
            $html .= '<div class="trace-json-item">';
            if ($isAssoc) {
                $html .= '<span class="trace-json-key">"' . htmlspecialchars($key) . '"</span><span class="trace-json-colon">: </span>';
            }
            $html .= render_json_node($value, $level + 1);
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '<span class="trace-json-bracket">' . ($isAssoc ? '}' : ']') . '</span>';
    } else {
        $html .= render_json_value($data);
    }
    
    return $html;
}

function render_json_value($value) {
    if (is_null($value)) {
        return '<span class="trace-json-null">null</span>';
    } elseif (is_bool($value)) {
        return '<span class="trace-json-boolean">' . ($value ? 'true' : 'false') . '</span>';
    } elseif (is_numeric($value)) {
        return '<span class="trace-json-number">' . $value . '</span>';
    } elseif (is_string($value)) {
        return '<span class="trace-json-string">"' . htmlspecialchars($value) . '"</span>';
    }
    return '<span>' . htmlspecialchars((string)$value) . '</span>';
}
@endphp

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
