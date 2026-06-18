<?php

namespace zxf\Trace\Traits;

use Exception;

/**
 * 基础信息收集 Trait
 *
 * 负责：
 * 1. 获取基础信息（请求信息、运行时间、内存等）
 * 2. 获取会话信息
 * 3. 获取请求/响应信息
 * 4. IP 地址脱敏处理
 */
trait InfoCollectorTrait
{
    /**
     * 获取基础信息
     *
     * @param float $sqlTimes SQL 总耗时（秒）
     * @return array
     */
    private function getBaseInfo($sqlTimes = 0): array
    {
        $runtime = round(microtime(true) - $this->startTime, 3);
        $reqs = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';
        $base = [
            '请求信息' => $this->request->method() . ' ' . $this->request->fullUrl(),
            '运行时间' => $runtime . '秒',
            '吞吐率' => $reqs . ' req/s',
            '内存消耗' => size_format(memory_get_usage() - $this->startMemory),
            '查询时间' => $sqlTimes . '秒',
        ];

        try {
            if ($this->request->session()) {
                $base['会话信息'] = 'SESSION_ID=' . $this->request->session()->getId();
            }
        } catch (Exception $e) {
            $base['会话信息'] = 'SESSION_ID=';
        }

        $base['PHP Version'] = phpversion();
        $base['Laravel Version'] = $this->app->version();
        $base['Environment'] = $this->app->environment();
        $base['Locale'] = $this->app->getLocale();

        // DB 数据库连接信息
        try {
            $dbConfig = \Illuminate\Support\Facades\DB::connection()->getConfig();
            $username = $dbConfig['username'] ?? '-';

            $base['DB Driver'] = ($dbConfig['driver'] ?? '-') . '(' . $this->maskIP($dbConfig['host'] ?? '-') . ') ' . ($dbConfig['charset'] ?? '-');
            $base['DB Connect'] = ($dbConfig['database'] ?? '-') . '(' . substr($username, 0, 2) . '***' . substr($username, -2) . ')';
        } catch (Exception $e) {
            $base['DB Driver'] = '-';
            $base['DB Connect'] = '-';
        }

        // 操作系统名称
        $osName = php_uname('s');
        $friendlyOsName = match (strtoupper($osName)) {
            'DARWIN' => 'macOS',
            'LINUX' => 'Linux',
            'WINDOWS NT' => 'Windows',
            default => $osName,
        };

        $base['OS'] = $friendlyOsName . ' v' . php_uname('r') . ' ' . php_uname('m');

        // 磁盘空间信息（仅非 Windows 系统）
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                $directoryPath = '/';
                $totalSpace = disk_total_space($directoryPath);
                $freeSpace = disk_free_space($directoryPath);

                if ($totalSpace && $freeSpace) {
                    $useSpace = $totalSpace - $freeSpace;
                    $usageRate = round(($useSpace / $totalSpace) * 100, 2) . '%';
                    $base['Disk Space'] = 'total:' . size_format($totalSpace) . '; used:' . size_format($useSpace) . '; free:' . size_format($freeSpace) . '; usage:' . $usageRate;
                }
            } catch (Exception $e) {
                // 忽略磁盘空间获取错误
            }
        }

        return $base;
    }

    /**
     * 获取会话信息
     *
     * @return array
     */
    private function getSessionInfo(): array
    {
        try {
            $session = app('session');
            if (empty($session)) {
                return $_SESSION ?? [];
            }

            return $session->all();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取请求/响应信息
     *
     * @return array
     */
    private function getRequestInfo(): array
    {
        return [
            'path' => $this->request->path(),
            'status_code' => $this->response->getStatusCode(),
            'format' => $this->request->getRequestFormat(),
            'content_type' => $this->response->headers->get('Content-Type') ?: 'text/html',
            'host' => $this->request->host(),
            'ip' => $this->request->ip(),
            'request_query' => $this->request->query->all(),
            'request_request' => $this->request->request->all(),
            'request_headers' => $this->request->headers->all(),
            'response_headers' => $this->response->headers->all(),
        ];
    }

    /**
     * IP 地址掩码处理，隐藏中间部分
     *
     * @param  string  $ip
     * @return string
     */
    private function maskIP(string $ip): string
    {
        if (empty($ip) || strlen($ip) < 5 || $ip === 'localhost' || $ip === '127.0.0.1') {
            return $ip;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $this->maskIPv6($ip);
            }
            return $ip;
        }

        $parts = explode('.', $ip);

        if (count($parts) !== 4) {
            return $ip;
        }

        foreach ($parts as $part) {
            if (! is_numeric($part) || (int) $part < 0 || (int) $part > 255) {
                return $ip;
            }
        }

        return $parts[0] . '.***.***.' . $parts[3];
    }

    /**
     * IPv6 地址脱敏
     *
     * @param string $ip
     * @return string
     */
    private function maskIPv6(string $ip): string
    {
        $parts = explode(':', $ip);
        if (count($parts) < 4) {
            return '****:****:****:****';
        }

        $first = array_slice($parts, 0, 2);
        $last = array_slice($parts, -2, 2);

        return implode(':', $first) . ':****:****:' . implode(':', $last);
    }
}
