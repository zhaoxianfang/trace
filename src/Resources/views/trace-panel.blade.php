{{--
    Trace 调试面板视图
    用于渲染调试工具栏和内容面板
--}}
<div id="trace-tools-box">
    <div class="trace-logo">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" style="height: 18px;" class="logo">
        <span class="title">Trace</span>
    </div>
    <div class="tabs-container">
        <div class="tabs-header">
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAAXNSR0IArs4c6QAAAcBJREFUOE/F1MtKAlEYB/DvaI1OlJIuhBYKFbSzi9lKcIJadnkIoQeoTYvw0mu0laBlPYAOQUG2cFGEQdioQXbTUcsy9cQZm+HkXBRcdEAGjt/8zn/OzHcQ9DEwH4qQMhQ8kK5GAxn9iRMhDsw4DBi4Th2K9kI1QTXUvaw+rAI7j4fDvR5NL7ECSqlMOKEJ2WcAxIz2GgiSgBEvb4UE5g8DHGsdizgdE8Huu7B3B4CAZOROAAnHf0rE50pauCnA7N75vLTLMohwJ52VtfEExp51APeadqpfOHuV40vFshTCF0tJlgokkzb/dnF0atOlt49NsdDIHu3mag+303KNIch6t8BsnwTLECNaXIt2+aZW6b7+ep2sm0fGHU9ncfh8EZQ1+wLlaqb9VawIwjCB5LmBwGb1sY4/zCy9Bf8LMp5VYNwrSqCBExKJvBQCkysNiplTIL/uYfhS6GICmpxLb9W7rEMLkr49DMmF/dSy8h3KQD4eiCCk7uNGrZ0uFZpzOr0X9cUulGNNdTiQNoQ2cDSsBdKp6IV0z0M6LQ0SqGXCUX/0MqmV2PCAlfo8Hoh8v7c2yvlm2QiS8Z6gXj/rzf8AmFQQJJO/2LAAAAAASUVORK5CYII=" alt="Logo" class="tabs-logo-small">
            <div class="tabs-menu">
                @foreach(array_keys($trace) as $key => $name)
                    @php
                        $tabKey = $key + 1;
                        $activeClass = $key < 1 ? 'active' : '';
                        $isSelected = $key < 1 ? 'true' : 'false';
                    @endphp
                    <div class='tabs-item {{ $activeClass }}' data-tab='tab{{ $tabKey }}' tabindex='0' role='tab' aria-selected='{{ $isSelected }}'>
                        {{ $name }}
                    </div>
                @endforeach
            </div>
            <div class="tabs-close" title="关闭调试面板 (ESC)">×</div>
        </div>

        @php $tabIndex = 0; @endphp
        @foreach($trace as $key => $tabs)
            @php
                $tabKey = ++$tabIndex;
                $active = $tabIndex < 2 ? 'active' : '';
            @endphp
            <div id="tab{{ $tabKey }}" class="tabs-content {{ $active }}" role="tabpanel" aria-labelledby="tab{{ $tabKey }}">
                <ul>
                    @foreach($tabs as $k => $item)
                        @if(is_array($item) && isset($item['type']) && $item['type'] == 'sql_group')
                            {{-- SQL 分组通过组件渲染 --}}
                            @include('trace::components.sql-group', [
                                'name' => $item['name'] ?? 'SQL Group',
                                'class' => $item['class'] ?? 'sql-group',
                                'collapsed' => $item['collapsed'] ?? false,
                                'count' => $item['count'] ?? 0,
                                'sqls' => $item['sqls'] ?? []
                            ])
                        @else
                            <li>
                                @if(is_array($item) && !empty($item['type']) && $item['type'] == 'trace')
                                    {{-- Trace 数据项 --}}
                                    @include('trace::components.trace-item', ['data' => $item])
                                @else
                                    {{-- 左侧 label --}}
                                    @if(is_array($item) && !empty($item['label']))
                                        <span class='json-label'>{{ $item['label'] }}</span>
                                    @elseif(is_string($k))
                                        <span class='json-label'>{{ $k }}</span>
                                    @endif

                                    {{-- 中间内容 --}}
                                    @if(!is_array($item))
                                        @php $class = is_numeric($k) ? 'json-label' : 'json-string-content'; @endphp
                                        @if(is_scalar($item) || is_null($item))
                                            <div class='{{ $class }}'>{{ format_param($item) }}</div>
                                        @else
                                            <div class='{{ $class }}'>{{ ucfirst(gettype($item)) }}:{{ get_class($item) }}</div>
                                        @endif
                                    @else
                                        @if(!empty(array_diff(array_keys($item), ['label', 'right'])))
                                            @include('trace::components.json-display', [
                                                'json' => json_encode($item, JSON_UNESCAPED_UNICODE)
                                            ])
                                        @elseif(empty($item))
                                            <span class='json-string-content'>[]</span>
                                        @endif
                                    @endif

                                    {{-- 右侧 right --}}
                                    @if(is_array($item) && !empty($item['right']))
                                        <span class='json-right'>{{ $item['right'] }}</span>
                                    @endif
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
