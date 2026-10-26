<?php
declare(strict_types=1);
/**
 * FunnelAnalyzer.php — Chức năng 5:
 * Thống kê chuỗi thao tác theo thứ tự đã định nghĩa.
 *
 * Quy tắc:
 * - Một chuỗi (funnel) hoàn tất khi TẤT CẢ bước xảy ra trong CÙNG một session
 *   theo đúng THỨ TỰ đã khai báo (bước sau phải có client_ts >= bước trước).
 * - Thiếu bước hoặc sai thứ tự không được coi là hoàn tất.
 * - Gửi lặp không tạo thêm lượt hoàn tất (đã lọc ở EventCollector).
 * - Một session chỉ được tính hoàn tất tối đa 1 lần cho mỗi funnel.
 */
require_once __DIR__ . '/Database.php';

/**
 * Định nghĩa một bước trong funnel.
 */
final class FunnelStep
{
    public function __construct(
        public readonly string $eventType,
        public readonly string $pagePath = '*',   // '*' = bất kỳ trang
        public readonly string $target   = '*',   // '*' = bất kỳ target
    ) {}
}

/**
 * Kết quả phân tích funnel cho một website.
 */
final class FunnelResult
{
    public function __construct(
        public readonly int   $totalSessions,
        public readonly int   $completedSessions,
        public readonly float $completionRate,
        public readonly array $stepCounts,          // số session đạt từng bước
        public readonly array $completedSessionIds,
    ) {}
}

/**
 * Phân tích chuỗi thao tác (funnel) cho một website.
 *
 * @param FunnelStep[] $steps Các bước theo thứ tự
 * @param array $filter ['date_from'=>'Y-m-d', 'date_to'=>'Y-m-d']
 */
function analyzeFunnel(PDO $pdo, int $websiteId, array $steps, array $filter = []): FunnelResult
{
    if (empty($steps)) {
        return new FunnelResult(0, 0, 0.0, [], []);
    }

    // Lấy toàn bộ sự kiện của website, sắp xếp theo session + thời gian
    $params  = [$websiteId];
    $clauses = [];

    if (!empty($filter['date_from'])) {
        $dt = new DateTimeImmutable($filter['date_from'] . ' 00:00:00',
            new DateTimeZone('Asia/Ho_Chi_Minh'));
        $clauses[] = 'client_ts >= ?';
        $params[]  = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    if (!empty($filter['date_to'])) {
        $dt = new DateTimeImmutable($filter['date_to'] . ' 23:59:59',
            new DateTimeZone('Asia/Ho_Chi_Minh'));
        $clauses[] = 'client_ts <= ?';
        $params[]  = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    $where = $clauses ? 'AND ' . implode(' AND ', $clauses) : '';
    $rows = dbQuery($pdo,
        "SELECT session_id, event_type, page_path, target_selector, client_ts
         FROM events WHERE website_id = ? $where
         ORDER BY session_id ASC, client_ts ASC",
        $params
    )->fetchAll();

    // Nhóm theo session
    $bySession = [];
    foreach ($rows as $row) {
        $bySession[$row['session_id']][] = $row;
    }

    $totalSessions     = count($bySession);
    $stepCounts        = array_fill(0, count($steps), 0);
    $completedSessions = [];

    foreach ($bySession as $sessionId => $events) {
        $stepIdx   = 0;
        $lastTs    = null;
        $stepsHit  = [];

        foreach ($events as $ev) {
            if ($stepIdx >= count($steps)) {
                break;
            }
            $step = $steps[$stepIdx];
            if (matchesStep($ev, $step)) {
                $stepCounts[$stepIdx]++;
                $stepsHit[] = $stepIdx;
                $lastTs     = $ev['client_ts'];
                $stepIdx++;
            }
        }

        if ($stepIdx === count($steps)) {
            // Tất cả bước đã hoàn tất theo đúng thứ tự
            $completedSessions[] = $sessionId;
        }
    }

    $completionRate = $totalSessions > 0
        ? round(count($completedSessions) / $totalSessions * 100, 2)
        : 0.0;

    return new FunnelResult(
        totalSessions:      $totalSessions,
        completedSessions:  count($completedSessions),
        completionRate:     $completionRate,
        stepCounts:         $stepCounts,
        completedSessionIds: $completedSessions,
    );
}

/**
 * Kiểm tra một event có khớp với một bước funnel không.
 */
function matchesStep(array $event, FunnelStep $step): bool
{
    if ($event['event_type'] !== $step->eventType) {
        return false;
    }
    if ($step->pagePath !== '*' && $event['page_path'] !== $step->pagePath) {
        return false;
    }
    if ($step->target !== '*' && $event['target_selector'] !== $step->target) {
        return false;
    }
    return true;
}

/**
 * Funnel mặc định: pageview → click#btn-start → click#btn-complete (website 2)
 */
function defaultFunnel(): array
{
    return [
        new FunnelStep('pageview'),
        new FunnelStep('click', '*', '#btn-start'),
        new FunnelStep('click', '*', '#btn-complete'),
    ];
}

/**
 * Funnel cho website 1: pageview → download
 */
function downloadFunnel(): array
{
    return [
        new FunnelStep('pageview'),
        new FunnelStep('click', '*', '#btn-download'),
    ];
}
