<?php

declare(strict_types=1);

namespace App\UI\Controller\Admin;

use App\Infrastructure\Mailing\EmailSpoolService;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Infrastructure\Search\ProductIndexer;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\MonitorLogsRequestDto;
use App\UI\Response\ViewResponse;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Redis;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Контроллер мониторинга внутренних сервисов.
 */
final class SystemMonitorController implements ControllerInterface
{
    private const LOG_LINES_LIMIT = 20;

    public function __construct(
        private readonly ?\Redis $redis,
        private readonly ?CacheItemPoolInterface $cache,
        private readonly Connection $dbConnection,
        private readonly string $projectDir,
        private readonly EmailSpoolService $emailSpoolService,
        private readonly AsyncJobTracker $jobTracker,
        private readonly ?ProductIndexer $productIndexer = null,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly string $prometheusUrl = '',
        private readonly string $grafanaUrl = '',
        private readonly string $jaegerUrl = '',
        private readonly string $prometheusInternalUrl = '',
        private readonly string $grafanaInternalUrl = '',
        private readonly string $jaegerInternalUrl = '',
    ) {
    }

    /**
     * GET /admin/monitor - Страница мониторинга систем
     */
    public function __invoke(): ViewResponse
    {
        $status = [
            'database' => $this->getCachedBlock('database'),
            'cache' => $this->getCachedBlock('cache'),
            'redis' => $this->getCachedBlock('redis'),
            'disk' => $this->getCachedBlock('disk'),
            'resources' => $this->getCachedBlock('resources'),
            'php' => $this->getCachedBlock('php'),
            'logs' => $this->getCachedRecentLogs(5),
            'emailSpool' => $this->getCachedBlock('emailSpool'),
            'productIndex' => $this->getCachedBlock('productIndex'),
            'asyncJobs' => $this->getCachedBlock('asyncJobs'),
            'queueTelemetry' => $this->getCachedBlock('queueTelemetry'),
            'prometheus' => $this->getCachedBlock('prometheus'),
            'grafana' => $this->getCachedBlock('grafana'),
            'jaeger' => $this->getCachedBlock('jaeger'),
        ];

        return new ViewResponse(
            'admin/monitor.html.twig', [
                'status' => $status,
            ]
        );
    }

    /**
     * GET /admin/monitor/live - Асинхронные данные для автообновления.
     */
    public function liveData(): JsonResponse
    {
        $health = $this->jobTracker->getHealthSnapshot(200);

        return new JsonResponse([
            'asyncJobs' => $this->getAsyncJobsStatus($health),
            'logs' => $this->getCachedRecentLogs(self::LOG_LINES_LIMIT),
            'updatedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    /**
     * GET /admin/monitor/block/{block} - данные одного блока по запросу.
     *
     * Тяжёлые проверки (БД, Elasticsearch, очереди) кешируются, поэтому
     * раскрытие карточки не создаёт нагрузку на каждый клик.
     */
    public function blockData(string $block): JsonResponse
    {
        $allowed = [
            'database', 'cache', 'redis', 'disk', 'resources', 'php',
            'emailSpool', 'productIndex', 'asyncJobs', 'queueTelemetry',
            'prometheus', 'grafana', 'jaeger',
        ];

        if (!\in_array($block, $allowed, true)) {
            return new JsonResponse(['error' => 'Unknown block'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getCachedBlock($block);

        return new JsonResponse([
            'block' => $block,
            'data' => $data,
            'cachedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * GET /admin/monitor/logs?type=app&limit=50 - часть логов по запросу.
     */
    public function logsData(MonitorLogsRequestDto $dto): JsonResponse
    {
        $files = $this->getLogFiles();

        if (!isset($files[$dto->type])) {
            return new JsonResponse(['error' => 'Unknown log type'], Response::HTTP_NOT_FOUND);
        }

        $filepath = $this->projectDir.'/var/log/'.$files[$dto->type];
        if (!is_file($filepath)) {
            return new JsonResponse([
                'type' => $dto->type,
                'lines' => [],
                'info' => 'Log file not found',
                'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);
        }

        $lines = [];
        foreach ($this->getLastLines($filepath, $dto->limit) as $line) {
            $parsed = $this->parseLogLine($line);
            if ($parsed !== null) {
                $lines[] = $parsed;
            }
        }

        return new JsonResponse([
            'type' => $dto->type,
            'lines' => $lines,
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * POST /admin/monitor/clear-cache - Очистка кеша.
     */
    public function clearCache(): Response
    {
        $messages = [];

        // Очистка Redis cache (безопасно)
        if ($this->redis !== null) {
            try {
                $this->redis->flushDB();
                $messages[] = 'Redis cache cleared';
            } catch (\Exception $e) {
                $messages[] = 'Redis clear error: '.$e->getMessage();
            }
        }

        // Очистка Symfony cache через процесс (безопаснее)
        try {
            $process = new Process(['php', $this->projectDir.'/bin/console', 'cache:clear', '--no-warmup']);
            $process->setWorkingDirectory($this->projectDir);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                $messages[] = 'Symfony cache cleared via console';
            } else {
                $messages[] = 'Cache clear warning: '.$process->getErrorOutput();
            }
        } catch (\Exception $e) {
            $messages[] = 'Cache clear error: '.$e->getMessage();
        }

        return new Response(
            json_encode(['success' => true, 'messages' => $messages]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    private function getCachedBlock(string $block): array
    {
        if ($this->cache === null) {
            return $this->computeBlock($block);
        }

        $item = $this->cache->getItem('admin_monitor_block_'.$block);
        if ($item->isHit() && \is_array($item->get())) {
            return $item->get();
        }

        $data = $this->computeBlock($block);
        $item->set($data);
        $item->expiresAfter($block === 'productIndex' ? 30 : 15);
        $this->cache->save($item);

        return $data;
    }

    private function computeBlock(string $block): array
    {
        return match ($block) {
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'disk' => $this->checkDiskSpace(),
            'resources' => $this->checkResources(),
            'php' => $this->checkPhpInfo(),
            'emailSpool' => $this->getEmailSpoolStatus(),
            'productIndex' => $this->checkProductIndexing(),
            'asyncJobs' => $this->getAsyncJobsStatus(),
            'queueTelemetry' => $this->getQueueTelemetry(),
            'prometheus' => $this->checkObservabilityService(
                'Prometheus',
                $this->prometheusInternalUrl,
                '/-/healthy',
                $this->prometheusUrl
            ),
            'grafana' => $this->checkObservabilityService(
                'Grafana',
                $this->grafanaInternalUrl,
                '/api/health',
                $this->grafanaUrl
            ),
            'jaeger' => $this->checkObservabilityService(
                'Jaeger',
                $this->jaegerInternalUrl,
                '/',
                $this->jaegerUrl
            ),
            default => ['status' => 'error', 'message' => 'Unknown block'],
        };
    }

    private function checkDatabase(): array
    {
        try {
            $this->dbConnection->executeQuery('SELECT 1');

            $platform = $this->dbConnection->getDatabasePlatform();

            // В DBAL 4.x getName() удалён, используем class name
            $driver = $platform::class;
            $driver = str_replace('Doctrine\DBAL\Platforms\\', '', $driver);

            $result = [
                'status' => 'ok',
                'message' => 'Database connection OK',
                'driver' => $driver,
                'tableCount' => null,
                'databaseSize' => null,
                'databaseSizeHuman' => null,
                'topTables' => [],
            ];

            try {
                $result['tableCount'] = (int) $this->dbConnection->fetchOne(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'"
                );
                $size = (int) $this->dbConnection->fetchOne('SELECT pg_database_size(current_database())');
                $result['databaseSize'] = $size;
                $result['databaseSizeHuman'] = $this->humanBytes($size);

                $rows = $this->dbConnection->fetchAllAssociative(
                    'SELECT relname AS name, pg_total_relation_size(relid) AS size
                     FROM pg_catalog.pg_statio_user_tables
                     ORDER BY pg_total_relation_size(relid) DESC
                     LIMIT 5'
                );
                $result['topTables'] = array_map(
                    fn (array $row): array => [
                        'name' => (string) $row['name'],
                        'size' => (int) $row['size'],
                        'sizeHuman' => $this->humanBytes((int) $row['size']),
                    ],
                    $rows
                );
            } catch (\Throwable $e) {
                $result['detailsError'] = $e->getMessage();
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            if ($this->cache === null) {
                return [
                    'status' => 'warning',
                    'message' => 'Cache pool not configured',
                ];
            }

            $item = $this->cache->getItem('health_check');
            $item->set(time());
            $item->expiresAfter(60);
            $this->cache->save($item);

            $checkItem = $this->cache->getItem('health_check');

            $result = [
                'status' => $checkItem->isHit() ? 'ok' : 'warning',
                'message' => $checkItem->isHit() ? 'Cache working' : 'Cache write failed',
                'keys' => null,
                'memory' => null,
                'hits' => null,
                'misses' => null,
                'hitRate' => null,
            ];

            if ($this->redis !== null) {
                $stats = $this->redis->info('stats');
                $memory = $this->redis->info('memory');

                $hits = (int) ($stats['keyspace_hits'] ?? 0);
                $misses = (int) ($stats['keyspace_misses'] ?? 0);
                $total = $hits + $misses;

                $result['keys'] = $this->redis->dbSize();
                $result['memory'] = $memory['used_memory_human'] ?? null;
                $result['hits'] = $hits;
                $result['misses'] = $misses;
                $result['hitRate'] = $total > 0 ? round($hits / $total * 100, 2) : null;
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        if ($this->redis === null) {
            return [
                'status' => 'warning',
                'message' => 'Redis not configured',
            ];
        }

        try {
            $this->redis->ping();
            $info = $this->redis->info();

            return [
                'status' => 'ok',
                'message' => 'Redis connected',
                'version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'keys' => $this->redis->dbSize(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        $free = disk_free_space($this->projectDir);
        $total = disk_total_space($this->projectDir);

        if ($free === false || $total === false) {
            return [
                'status' => 'error',
                'message' => 'Unable to determine disk space',
            ];
        }

        $used = $total - $free;
        $percentUsed = round(($used / $total) * 100, 2);

        return [
            'status' => $percentUsed > 90 ? 'error' : ($percentUsed > 75 ? 'warning' : 'ok'),
            'message' => \sprintf('Disk usage: %.2f GB / %.2f GB (%.2f%% used)',
                $used / 1024 / 1024 / 1024,
                $total / 1024 / 1024 / 1024,
                $percentUsed
            ),
            'free' => $free,
            'total' => $total,
            'percentUsed' => $percentUsed,
        ];
    }

    /**
     * Получить статус email spool.
     */
    private function getEmailSpoolStatus(): array
    {
        $spoolSize = $this->emailSpoolService->getSpoolSize();
        $emails = $this->emailSpoolService->getSpooledEmails();

        return [
            'enabled' => $this->emailSpoolService->isSpoolEnabled(),
            'count' => $spoolSize['count'],
            'totalSize' => $spoolSize['totalSize'],
            'totalSizeHuman' => $spoolSize['totalSizeHuman'],
            'emails' => \array_slice($emails, 0, 10), // Показываем только первые 10
        ];
    }

    /**
     * Проверить доступность observability-сервиса.
     *
     * Internal URL используется PHP-контейнером, public URL отдаётся в браузер.
     */
    private function checkObservabilityService(
        string $name,
        string $internalUrl,
        string $healthPath,
        string $publicUrl
    ): array {
        $internalUrl = rtrim($internalUrl, '/');
        $publicUrl = rtrim($publicUrl, '/');

        $result = [
            'status' => 'warning',
            'message' => $name.' internal URL is not configured',
            'url' => $publicUrl,
            'checkedAt' => (new \DateTimeImmutable())->format('H:i:s'),
        ];

        if ($internalUrl === '' || $this->httpClient === null) {
            return $result;
        }

        try {
            $response = $this->httpClient->request('GET', $internalUrl.$healthPath, [
                'timeout' => 2,
                'max_duration' => 3,
            ]);
            $statusCode = $response->getStatusCode();

            $result['status'] = $statusCode >= 200 && $statusCode < 400 ? 'ok' : 'error';
            $result['message'] = $statusCode >= 200 && $statusCode < 400
                ? $name.' is healthy'
                : $name.' returned HTTP '.$statusCode;
            $result['statusCode'] = $statusCode;
        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['message'] = $name.' unavailable: '.$e->getMessage();
        }

        return $result;
    }

    private function checkPhpInfo(): array
    {
        return [
            'version' => \PHP_VERSION,
            'memory_limit' => \ini_get('memory_limit'),
            'max_execution_time' => \ini_get('max_execution_time'),
            'upload_max_filesize' => \ini_get('upload_max_filesize'),
            'post_max_size' => \ini_get('post_max_size'),
        ];
    }

    /**
     * Минимальные CPU/RAM/disk метрики, снятые в момент запроса.
     */
    private function checkResources(): array
    {
        $load = \function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit((string) \ini_get('memory_limit'));
        $memoryPercent = $memoryLimit > 0 ? round($memoryUsage / $memoryLimit * 100, 2) : null;

        $load1 = \is_array($load) ? round($load[0], 2) : null;
        $status = 'ok';
        if ($load1 !== null && $load1 > 8.0) {
            $status = 'error';
        } elseif ($load1 !== null && $load1 > 4.0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'load1' => $load1,
            'load5' => \is_array($load) ? round($load[1], 2) : null,
            'load15' => \is_array($load) ? round($load[2], 2) : null,
            'memoryUsage' => $memoryUsage,
            'memoryUsageHuman' => $this->humanBytes($memoryUsage),
            'memoryPeak' => $memoryPeak,
            'memoryPeakHuman' => $this->humanBytes($memoryPeak),
            'memoryLimit' => $memoryLimit,
            'memoryLimitHuman' => $this->humanBytes($memoryLimit),
            'memoryPercent' => $memoryPercent,
            'checkedAt' => (new \DateTimeImmutable())->format('H:i:s'),
        ];
    }

    private function parseMemoryLimit(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), \count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }

    /**
     * Кешированное чтение логов для live-обновления.
     */
    private function getCachedRecentLogs(int $limit): array
    {
        if ($this->cache === null) {
            return $this->getRecentLogs($limit);
        }

        $item = $this->cache->getItem('admin_monitor_recent_logs_'.$limit);

        if ($item->isHit() && \is_array($item->get())) {
            return $item->get();
        }

        $logs = $this->getRecentLogs($limit);
        $item->set($logs);
        $item->expiresAfter(10);
        $this->cache->save($item);

        return $logs;
    }

    /**
     * @return array<string, string>
     */
    private function getLogFiles(): array
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev';

        return [
            $env => $env.'.log',
            'access' => 'access.log',
            'Critical_'.$env => 'critical_'.$env.'.log',
            'Deprications' => 'deprication_'.$env.'.log',
            'TEST_errors' => 'error_test.log',
            'Fatal_errors' => 'fatal_errors.log',
            'not_found_'.$env => 'not_found_'.$env.'.log',
            'PHPerrors' => 'php_errors.log',
            'Request_'.$env => 'request_'.$env.'.log',
            'Search_'.$env => 'search_'.$env.'.log',
            'Seq_'.$env => 'security_'.$env.'.log',
            'Slow' => 'slow.log',
            'Dupervisord' => 'supervisord.log',
            'Test' => 'test.log',
            'Twig_'.$env => 'twig_'.$env.'.log',
            'worker_async_00' => 'worker_async_00.log',
            'worker_async_00_Err' => 'worker_async_00_error.log',
            'worker_email_00' => 'worker_email_00.log',
            'worker_email_00_Err' => 'worker_email_00_error.log',
            'worker_images_00' => 'worker_images_00.log',
            'worker_images_00_Err' => 'worker_images_00_error.log',
            'worker_xml_00' => 'worker_xml_00.log',
            'worker_xml_00_Err' => 'worker_xml_00_error.log',
        ];
    }

    /**
     * Получить последние логи (по 20 записей для каждого типа).
     */
    private function getRecentLogs(int $limit = 100): array
    {
        $logDir = $this->projectDir.'/var/log';
        $logs = [];

        if (!is_dir($logDir)) {
            return ['error' => 'Log directory not found'];
        }

        $logFiles = $this->getLogFiles();

        foreach ($logFiles as $type => $filename) {
            $filepath = $logDir.'/'.$filename;

            if (!file_exists($filepath)) {
                $logs[$type] = ['info' => 'Log file not found'];
                continue;
            }

            // Читаем последние N строк
            $lines = $this->getLastLines($filepath, $limit);
            $parsedLines = [];

            foreach ($lines as $line) {
                $parsed = $this->parseLogLine($line);
                if ($parsed !== null) {
                    $parsedLines[] = $parsed;
                }
            }

            $logs[$type] = $parsedLines;
        }

        return $logs;
    }

    /**
     * Прочитать последние N строк файла.
     */
    private function getLastLines(string $filepath, int $limit): array
    {
        $lines = [];
        $handle = fopen($filepath, 'r');

        if ($handle === false) {
            return [];
        }

        // Читаем с конца файла
        $lineBuffer = '';
        $position = filesize($filepath);
        $chunkSize = 8192;

        while ($position > 0 && \count($lines) < $limit) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;
            fseek($handle, $position);
            $chunk = fread($handle, $readSize);

            if ($chunk === false) {
                break;
            }

            $lineBuffer = $chunk.$lineBuffer;
            $currentLines = explode("\n", $lineBuffer);

            // Последняя часть может быть неполной строкой
            $lineBuffer = array_pop($currentLines);

            // Добавляем строки в начало массива
            $lines = array_merge($currentLines, $lines);
        }

        // Добавляем оставшийся буфер
        if ($lineBuffer !== '' && \count($lines) < $limit) {
            array_unshift($lines, $lineBuffer);
        }

        fclose($handle);

        // Возвращаем последние N строк
        return \array_slice($lines, -$limit);
    }

    /**
     * Распарсить строку лога.
     */
    private function parseLogLine(string $line): ?array
    {
        // Пропускаем пустые строки
        if (trim($line) === '') {
            return null;
        }

        // Пытаемся распарсить JSON лог
        $jsonStart = strpos($line, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($line, $jsonStart);
            $data = json_decode($jsonStr, true);

            if ($data !== null) {
                return [
                    'level' => $data['level_name'] ?? 'UNKNOWN',
                    'channel' => $data['channel'] ?? 'app',
                    'message' => $data['message'] ?? '',
                    'datetime' => $data['datetime'] ?? '',
                    'raw' => $line,
                ];
            }
        }

        // Фоллбэк для обычных логов
        return [
            'level' => 'INFO',
            'channel' => 'app',
            'message' => substr($line, 0, 200),
            'datetime' => '',
            'raw' => $line,
        ];
    }

    /**
     * Проверить статус индексации продуктов.
     */
    private function checkProductIndexing(): array
    {
        // Получаем количество продуктов через DBAL (без ORM)
        $totalProducts = (int) $this->dbConnection->fetchOne('SELECT COUNT(*) FROM product');

        if ($this->productIndexer === null) {
            return [
                'status' => 'warning',
                'totalInDb' => $totalProducts,
                'indexedCount' => 0,
                'indexExists' => false,
                'message' => 'Elasticsearch не настроен',
            ];
        }

        try {
            $index = $this->productIndexer->getIndex();
            $indexExists = $index->exists();
            $indexedCount = 0;
            $statsData = [];

            if ($indexExists) {
                $indexedCount = $index->count();
                $statsData = $index->getStats()->getData();
            }

            $primary = $statsData['_all']['primaries'] ?? [];
            $docsCount = (int) ($primary['docs']['count'] ?? $indexedCount);
            $deletedDocs = (int) ($primary['docs']['deleted'] ?? 0);
            $storeBytes = (int) ($primary['store']['size_in_bytes'] ?? 0);
            $segments = (int) ($primary['segments']['count'] ?? 0);
            $searchQueries = (int) ($primary['search']['query_total'] ?? 0);
            $indexingOperations = (int) ($primary['indexing']['index_total'] ?? 0);
            $health = 'unknown';

            try {
                $healthData = $index->getClient()->getCluster()->getHealth()->getData();
                $health = (string) ($healthData['status'] ?? 'unknown');
            } catch (\Throwable) {
                // health опционален: Elasticsearch может быть недоступен по cluster API
            }

            $isSynced = $indexExists && $indexedCount === $totalProducts;

            return [
                'status' => $isSynced ? 'ok' : ($indexExists ? 'warning' : 'error'),
                'totalInDb' => $totalProducts,
                'indexedCount' => $indexedCount,
                'indexExists' => $indexExists,
                'indexName' => $index->getName(),
                'health' => $health,
                'docsCount' => $docsCount,
                'deletedDocs' => $deletedDocs,
                'storeBytes' => $storeBytes,
                'storeHuman' => $this->humanBytes($storeBytes),
                'segments' => $segments,
                'searchQueries' => $searchQueries,
                'indexingOperations' => $indexingOperations,
                'message' => $isSynced
                    ? 'Индекс синхронизирован'
                    : ($indexExists
                        ? "В индексе $indexedCount из $totalProducts продуктов"
                        : 'Индекс не создан'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'totalInDb' => $totalProducts,
                'indexedCount' => 0,
                'indexExists' => false,
                'message' => 'Elasticsearch error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Получить статус последних async-задач.
     */
    private function getAsyncJobsStatus(?array $health = null): array
    {
        $health ??= $this->jobTracker->getHealthSnapshot(200);
        $jobs = \array_slice($health['jobs'] ?? [], 0, 30);

        $byType = [];
        $byStatus = [];
        foreach ($jobs as $job) {
            $type = (string) ($job['type'] ?? 'unknown');
            $status = (string) ($job['status'] ?? 'unknown');
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        $oldestPending = null;
        foreach (($health['queues'] ?? []) as $queue) {
            $seconds = $queue['oldest_pending_seconds'] ?? null;
            if ($seconds !== null && ($oldestPending === null || $seconds > $oldestPending)) {
                $oldestPending = (float) $seconds;
            }
        }

        return [
            'summary' => $health['summary'],
            'jobs' => $jobs,
            'health' => $health['queues'],
            'byType' => $byType,
            'byStatus' => $byStatus,
            'oldestPendingSeconds' => $oldestPending,
        ];
    }

    /**
     * Получить статус телеметрии очередей.
     */
    private function getQueueTelemetry(?array $health = null): array
    {
        $health ??= $this->jobTracker->getHealthSnapshot(200);

        return [
            'summary' => $health['summary'],
            'queues' => $health['queues'],
        ];
    }
}
