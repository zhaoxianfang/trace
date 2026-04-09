{{-- SQL 分组组件 --}}
<div class="sql-group {{ $class ?? '' }} {{ ($collapsed ?? false) ? 'collapsed' : '' }}">
    <div class="sql-group-header">
        <div class="sql-group-title">
            <span>{{ $name ?? 'SQL Group' }}</span>
            <span class="sql-group-count">{{ $count ?? 0 }}条</span>
        </div>
        <span class="sql-group-toggle">▼</span>
    </div>
    <ul class="sql-group-content">
        @foreach($sqls ?? [] as $sql)
            <li>
                <span class="json-label">{{ $sql['label'] ?? '' }}</span>
                <span class="json-right">{{ $sql['right'] ?? '-' }}</span>
            </li>
        @endforeach
    </ul>
</div>
