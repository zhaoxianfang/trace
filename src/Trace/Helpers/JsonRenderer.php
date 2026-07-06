<?php

namespace zxf\Trace\Helpers;

/**
 * JSON 渲染辅助类
 *
 * 将 JSON 数据渲染为带语法高亮的 HTML 片段。
 * 原本在 json-display.blade.php 中定义为全局函数，
 * 现已提取为静态方法避免全局命名空间污染。
 */
class JsonRenderer
{
    /**
     * 渲染 JSON 节点
     *
     * @param mixed $data JSON 数据
     * @param int $level 缩进级别
     * @return string HTML
     */
    public static function renderNode($data, int $level = 0): string
    {
        if (is_array($data)) {
            if (empty($data)) {
                return '<span class="trace-json-bracket">[]</span>';
            }

            $isAssoc = array_keys($data) !== range(0, count($data) - 1);

            if (!$isAssoc && count($data) <= 3) {
                $items = array_map([self::class, 'renderValue'], $data);
                return '<span class="trace-json-bracket">[</span>'
                    . implode('<span class="trace-json-comma">, </span>', $items)
                    . '<span class="trace-json-bracket">]</span>';
            }

            $html = '<span class="trace-json-bracket">' . ($isAssoc ? '{' : '[') . '</span>';
            $html .= '<div class="trace-json-children">';

            foreach ($data as $key => $value) {
                $html .= '<div class="trace-json-item">';
                if ($isAssoc) {
                    $html .= '<span class="trace-json-key">"'
                        . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8')
                        . '"</span><span class="trace-json-colon">: </span>';
                }
                $html .= self::renderNode($value, $level + 1);
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '<span class="trace-json-bracket">' . ($isAssoc ? '}' : ']') . '</span>';

            return $html;
        }

        return self::renderValue($data);
    }

    /**
     * 渲染 JSON 值
     *
     * @param mixed $value
     * @return string HTML
     */
    public static function renderValue($value): string
    {
        if (is_null($value)) {
            return '<span class="trace-json-null">null</span>';
        }
        if (is_bool($value)) {
            return '<span class="trace-json-boolean">' . ($value ? 'true' : 'false') . '</span>';
        }
        if (is_numeric($value)) {
            return '<span class="trace-json-number">' . $value . '</span>';
        }
        if (is_string($value)) {
            return '<span class="trace-json-string">"'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                . '"</span>';
        }

        return '<span>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
