<?php
declare(strict_types=1);
/**
 * ReportExporter.php — Chức năng 5 (xuất báo cáo):
 * Xuất báo cáo tổng hợp dạng CSV hoặc JSON; ghi log report_queries.
 * Quy tắc giữ/xóa dữ liệu: sự kiện cũ hơn RETENTION_DAYS ngày sẽ bị xóa khi
 * chạy purgeOldEvents().
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Aggregator.php';

const RETENTION_DAYS = 90; // Giữ dữ liệu 90 ngày

/**
 * Xuất báo cáo tổng hợp theo ngày; ghi audit log.
 * @return string nội dung CSV hoặc JSON
 */
function exportReport(
    PDO    $pdo,
    int    $websiteId,
    string $format,
    array  $filter    = [],
    string $viewerKey = 'system'
): string {
    // Ghi audit log
    dbQuery($pdo,
        'INSERT INTO report_queries (website_id, viewer_key, params) VALUES (?,?,?)',
        [$websiteId, $viewerKey, json_encode($filter, JSON_UNESCAPED_UNICODE)]
    );

    $days = aggregateByDay($pdo, $websiteId, $filter);

    if ($format === 'json') {
        return json_encode([
            'website_id'   => $websiteId,
            'timezone'     => REPORT_TIMEZONE,
            'generated_at' => (new DateTimeImmutable('now', new DateTimeZone(REPORT_TIMEZONE)))->format('c'),
            'filter'       => $filter,
            'days'         => $days,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // CSV
    $lines = [];
    $lines[] = implode(',', ['date', 'pageviews', 'active_sessions', 'event_type', 'count']);
    foreach ($days as $day) {
        foreach ($day['events_by_type'] as $type => $count) {
            $lines[] = implode(',', [
                $day['date'],
                $day['pageviews'],
                $day['active_sessions'],
                $type,
                $count,
            ]);
        }
        if (empty($day['events_by_type'])) {
            $lines[] = implode(',', [$day['date'], $day['pageviews'], $day['active_sessions'], '', 0]);
        }
    }
    return implode("\n", $lines) . "\n";
}

/**
 * Xóa sự kiện cũ hơn RETENTION_DAYS ngày (giữ session nếu còn sự kiện khác).
 * Trả về số dòng đã xóa.
 */
function purgeOldEvents(PDO $pdo): int
{
    $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . RETENTION_DAYS . ' days'));
    $stmt   = dbQuery($pdo,
        'DELETE FROM events WHERE client_ts < ?',
        [$cutoff]
    );
    return $stmt->rowCount();
}

/**
 * Kiểm tra quyền: viewer phải cung cấp đúng api_key của website.
 * (Trong môi trường thực, dùng hệ thống xác thực riêng.)
 */
function checkReportPermission(PDO $pdo, int $websiteId, string $apiKey): bool
{
    $row = dbQuery($pdo,
        'SELECT id FROM websites WHERE id=? AND api_key=? AND active=1',
        [$websiteId, $apiKey]
    )->fetch();
    return $row !== false;
}
