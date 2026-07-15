<?php

namespace zxf\Trace\Traits;

/**
 * 模型事件收集 Trait
 *
 * 负责：
 * 1. 监听模型事件（retrieved, created, updated, deleted 等）
 * 2. 记录模型操作类型（DB查询 / 写操作 / 引用）
 * 3. 构建模型使用统计列表
 * 4. 解析模型文件路径（用于 IDE 链接）
 */
trait ModelCollectorTrait
{
    /**
     * 单次请求最多记录的模型事件数量（防止内存无限增长）
     */
    protected int $maxModelCount = 1000;

    /**
     * 监听模型事件
     *
     * 注意：使用静态标记确保事件监听器只注册一次，避免重复监听
     * 监听 CURD 操作（创建、更新、删除）和数据查询操作
     */
    public function listenModelEvent(): void
    {
        if (self::$eventListenersRegistered) {
            return;
        }

        if (!class_exists(\Illuminate\Support\Facades\Event::class)) {
            return;
        }

        $modelEvents = [
            'retrieved', 'creating', 'created', 'updating', 'updated',
            'deleting', 'deleted', 'saving', 'saved',
        ];

        foreach ($modelEvents as $event) {
            try {
                \Illuminate\Support\Facades\Event::listen('eloquent.' . $event . ':*', function ($listenString, $model) use ($event) {
                    if (self::$currentRequestId === $this->requestId) {
                        $this->logModelEvent($listenString, $model, $event);
                    }
                });
            } catch (\Throwable $e) {
                if (config('app.debug', false)) {
                    error_log('[Trace] 模型事件监听注册失败: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * 记录模型事件
     *
     * @param  string  $listenString  监听字符串
     * @param  mixed  $model  模型实例或模型数组
     * @param  string  $event  事件名称
     */
    protected function logModelEvent($listenString, $model, $event): void
    {
        if (self::$currentRequestId !== $this->requestId) {
            return;
        }

        // 内存/数量自保护：一旦内存临界或记录数超限即停止采集，
        // 从根源避免本调试包在海量模型加载时引发 OOM
        if ($this->isMemoryCritical()) {
            return;
        }
        $currentList = self::$modelList[$this->requestId] ?? [];
        if (count($currentList) >= $this->maxModelCount) {
            return;
        }

        $model = is_array($model) && isset($model[0]) ? $model[0] : $model;

        $modelName = trim(explode(':', $listenString)[1]);
        $modelId = $model->getKey();

        self::$modelList[$this->requestId] ??= [];
        self::$modelList[$this->requestId][] = [
            'model' => $modelName,
            'id' => $modelId,
            'event' => $event,
        ];

        self::$modelEventTypes[$this->requestId] ??= [];
        self::$modelEventTypes[$this->requestId][$modelName] ??= [
            'db_queries' => false,
            'db_writes' => false,
            'reference_only' => true,
        ];

        if ($event === 'retrieved') {
            self::$modelEventTypes[$this->requestId][$modelName]['db_queries'] = true;
            self::$modelEventTypes[$this->requestId][$modelName]['reference_only'] = false;
        }

        if (in_array($event, ['created', 'updated', 'deleted', 'saved', 'creating', 'updating', 'deleting', 'saving'], true)) {
            self::$modelEventTypes[$this->requestId][$modelName]['db_writes'] = true;
            self::$modelEventTypes[$this->requestId][$modelName]['reference_only'] = false;
        }
    }

    /**
     * 获取模型列表并清理数据
     *
     * @return array 模型使用统计列表（结构化数据）
     */
    private function getModelList(): array
    {
        $data = [];
        $eventDetails = [];

        $currentModels = self::$modelList[$this->requestId] ?? [];
        $eventTypes = self::$modelEventTypes[$this->requestId] ?? [];

        foreach ($currentModels as $model) {
            $key = $model['model'] . ':' . $model['id'];
            if (empty($data[$key])) {
                $data[$key] = 1;
                $eventDetails[$key] = [$model['event']];
            } else {
                $data[$key] += 1;
                $eventDetails[$key][] = $model['event'];
            }
        }

        $list = [];
        foreach ($data as $modelKey => $num) {
            $parts = explode(':', $modelKey, 2);
            $modelName = $parts[0];
            $modelId = $parts[1] ?? '';
            $modelTypes = $eventTypes[$modelName] ?? null;
            $events = array_unique($eventDetails[$modelKey] ?? []);

            $modelFilePath = $this->resolveModelFilePath($modelName);

            $list[] = [
                'type' => 'model_item',
                'model_class' => $modelName,
                'model_id' => $modelId,
                'event_count' => $num,
                'events' => $events,
                'has_db_queries' => $modelTypes['db_queries'] ?? false,
                'has_db_writes' => $modelTypes['db_writes'] ?? false,
                'reference_only' => $modelTypes['reference_only'] ?? false,
                'file_path' => $modelFilePath,
            ];
        }

        unset(self::$modelList[$this->requestId]);
        unset(self::$modelEventTypes[$this->requestId]);

        return $list;
    }

    /**
     * 通过反射解析模型类的文件路径
     *
     * @param string $modelClass 模型类名
     * @return string 文件路径，解析失败返回空字符串
     */
    private function resolveModelFilePath(string $modelClass): string
    {
        try {
            if (class_exists($modelClass)) {
                $reflection = new \ReflectionClass($modelClass);
                $file = $reflection->getFileName();
                if ($file && !str_contains($file, '/vendor/')) {
                    return $file;
                }
            }
        } catch (\Throwable) {
            // 反射失败
        }
        return '';
    }
}
