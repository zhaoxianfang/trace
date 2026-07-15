<?php

namespace zxf\Trace;

use Throwable;

/**
 * 安全存储操作类
 *
 * 用途：提供对文件系统的安全操作，捕获并处理所有可能的异常
 * 注意：本类不使用任何缓存机制，确保资源占用低、执行速度快
 */
class SafeStorage
{
    /**
     * 安全地写入文件
     *
     * 使用原子写入策略（临时文件+重命名）确保数据完整性
     */
    public static function filePut(string $path, string $content): bool
    {
        try {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                // 使用错误抑制符防止警告，并检查结果
                if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    EmergencyRenderer::logError("Failed to create directory: {$dir}", 'SafeStorage::filePut');
                    return false;
                }
            }
            
            // 检查目录是否可写
            if (!is_writable($dir)) {
                EmergencyRenderer::logError("Directory not writable: {$dir}", 'SafeStorage::filePut');
                return false;
            }
            
            // 写入文件，使用临时文件+重命名策略确保原子性
            // 使用 tempnam 生成防碰撞的临时文件名，避免高并发下 uniqid 碰撞导致互相覆盖
            $tempFile = tempnam($dir, 'trace_');
            if ($tempFile === false) {
                EmergencyRenderer::logError("Failed to create temp file in: {$dir}", 'SafeStorage::filePut');
                return false;
            }
            $result = @file_put_contents($tempFile, $content, LOCK_EX);
            
            if ($result === false) {
                @unlink($tempFile);
                return false;
            }
            
            // 重命名为目标文件
            if (!@rename($tempFile, $path)) {
                @unlink($tempFile);
                return false;
            }
            
            return true;
        } catch (Throwable $e) {
            EmergencyRenderer::logError($e, 'SafeStorage::filePut');
            return false;
        }
    }

    /**
     * 安全地读取文件
     */
    public static function fileGet(string $path, string $default = ''): string
    {
        try {
            if (!is_file($path) || !is_readable($path)) {
                return $default;
            }
            
            $content = @file_get_contents($path);
            return $content !== false ? $content : $default;
        } catch (Throwable $e) {
            EmergencyRenderer::logError($e, 'SafeStorage::fileGet');
            return $default;
        }
    }

    /**
     * 安全地删除文件
     */
    public static function fileDelete(string $path): bool
    {
        try {
            if (!is_file($path)) {
                return true; // 文件不存在，视为删除成功
            }
            return @unlink($path);
        } catch (Throwable $e) {
            EmergencyRenderer::logError($e, 'SafeStorage::fileDelete');
            return false;
        }
    }

    /**
     * 安全地检查文件是否存在
     */
    public static function fileExists(string $path): bool
    {
        try {
            return is_file($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 安全地创建目录
     */
    public static function makeDirectory(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        try {
            if (is_dir($path)) {
                return true;
            }
            return @mkdir($path, $mode, $recursive);
        } catch (Throwable $e) {
            EmergencyRenderer::logError($e, 'SafeStorage::makeDirectory');
            return false;
        }
    }
}
