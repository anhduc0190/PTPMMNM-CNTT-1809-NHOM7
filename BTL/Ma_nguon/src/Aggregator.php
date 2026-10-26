<?php
declare(strict_types=1);
/**
 * Aggregator.php — Chức năng 4:
 * Tổng hợp sự kiện theo ngày (múi giờ Asia/Ho_Chi_Minh), trang và loại sự kiện.
 * Không tính đôi: dựa trên bảng events gốc (đã lọc duplicate ở EventCollector).
 */
require_once __DIR__ . '/Database.php';

const REPORT_TIMEZONE = 'Asia/Ho_Chi_Minh'; // UTC+7 — múi giờ công bố

/**
 * Chuyển datetime UTC sang múi giờ báo cáo để nhóm theo ngày.
 * Thực hiện trong PHP để tránh phụ thuộc vào cài đặt MySQL timezone.
 */
function utcToReportDate(string $utcDatetime): string
{
    $dt = new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone(REPORT_TIMEZONE))->format('Y-m-d');
}

/**
 * Tổng hợp lượt xem, phiên và sự kiện theo ngày cho một website.
 *
 * @param array $filter ['date_from'=>'Y-m-d', 'date_to'=>'Y-m-d', 'event_type'=>string|null]
 * @return array[
 *   'date'           => string,   // Y-m-d theo UTC+7
 *   'pageviews'      => int,
 *   'active_sessions'=> int,      // phiên có ít nhất 1 sự kiện trong ngày
 *   'events_by_type' => array<string,int>,
 *   'pages'          => array<string, ['pageviews'=>int, 'events'=>int]>
 * ][]
 */
function aggregateByDay(PDO $pdo, int $websiteId, array $filter = []): array
{
    // Lấy toàn bộ sự kiện của website (chỉ OK, không phải error)
    $sql    = 'SELECT id, session_id, event_type, page_path, client_ts
               FROM events WHERE website_id = ? ORDER BY client_ts ASC';
    $rows   = dbQuery($pdo, $sql, [$websiteId])->fetchAll();

    // Nhóm theo ngày UTC+7
    $byDay  = [];
    foreach ($rows as $row) {
        $date = utcToReportDate($row['client_ts']);

        // Lọc khoảng thời gian nếu có
        if (!empty($filter['date_from']) && $date < $filter['date_from']) {
            continue;
        }
        if (!empty($filter['date_to']) && $date > $filter['date_to']) {
            continue;
        }
        if (!empty($filter['event_type']) && $row['event_type'] !== $filter['event_type']) {
            continue;
        }

        $byDay[$date] ??= [
            'date'            => $date,
            'pageviews'       => 0,
            'active_sessions' => [],
            'events_by_type'  => [],
            'pages'           => [],
        ];

        $et   = $row['event_type'];
        $path = $row['page_path'];
        $sid  = $row['session_id'];

        $byDay[$date]['active_sessions'][$sid]     = true;
        $byDay[$date]['events_by_type'][$et]       = ($byDay[$date]['events_by_type'][$et] ?? 0) + 1;
        $byDay[$date]['pages'][$path]              ??= ['pageviews' => 0, 'events' => 0];
        $byDay[$date]['pages'][$path]['events']++;

        if ($et === 'pageview') {
            $byDay[$date]['pageviews']++;
            $byDay[$date]['pages'][$path]['pageviews']++;
        }
    }

    // Chuẩn hóa kết quả
    $result = [];
    ksort($byDay);
    foreach ($byDay as $day) {
        $result[] = [
            'date'            => $day['date'],
            'pageviews'       => $day['pageviews'],
            'active_sessions' => count($day['active_sessions']),
            'events_by_type'  => $day['events_by_type'],
            'pages'           => $day['pages'],
        ];
    }
    return $result;
}

/**
 * Tổng hợp tổng cộng cho một website trong khoảng thời gian.
 */
function aggregateSummary(PDO $pdo, int $websiteId, array $filter = []): array
{
    $days = aggregateByDay($pdo, $websiteId, $filter);

    $total = [
        'pageviews'         => 0,
        'unique_sessions'   => 0,
        'events_by_type'    => [],
        'top_pages'         => [],
    ];

    $allSessions = [];
    $allPages    = [];

    foreach ($days as $day) {
        $total['pageviews'] += $day['pageviews'];
        foreach ($day['events_by_type'] as $type => $count) {
            $total['events_by_type'][$type] = ($total['events_by_type'][$type] ?? 0) + $count;
        }
    }

    // Đếm session và trang từ DB một lần (chuẩn hơn)
    $params = [$websiteId];
    $where  = 'WHERE website_id = ?';
    $rows   = dbQuery($pdo, "SELECT session_id, page_path, event_type FROM events $where ORDER BY client_ts", $params)->fetchAll();

    $sessionSet = [];
    $pageStats  = [];
    foreach ($rows as $r) {
        $sessionSet[$r['session_id']] = true;
        $pageStats[$r['page_path']]   ??= ['pageviews' => 0, 'events' => 0];
        $pageStats[$r['page_path']]['events']++;
        if ($r['event_type'] === 'pageview') {
            $pageStats[$r['page_path']]['pageviews']++;
        }
    }
    $total['unique_sessions'] = count($sessionSet);

    arsort($pageStats);
    $total['top_pages'] = array_slice($pageStats, 0, 10, true);

    return $total;
}

/**
 * Lấy danh sách sự kiện thô (cho mục đích kiểm tra / xuất báo cáo).
 */
function getRawEvents(PDO $pdo, int $websiteId, array $filter = []): array
{
    $params = [$websiteId];
    $clauses = [];

    if (!empty($filter['event_type'])) {
        $clauses[] = 'event_type = ?';
        $params[]  = $filter['event_type'];
    }
    if (!empty($filter['page_path'])) {
        $clauses[] = 'page_path = ?';
        $params[]  = $filter['page_path'];
    }
    if (!empty($filter['date_from'])) {
        // client_ts lưu UTC; chuyển date từ UTC+7 sang UTC
        $dt = new DateTimeImmutable($filter['date_from'] . ' 00:00:00', new DateTimeZone(REPORT_TIMEZONE));
        $clauses[] = 'client_ts >= ?';
        $params[]  = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    if (!empty($filter['date_to'])) {
        $dt = new DateTimeImmutable($filter['date_to'] . ' 23:59:59', new DateTimeZone(REPORT_TIMEZONE));
        $clauses[] = 'client_ts <= ?';
        $params[]  = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    $where = $clauses ? 'AND ' . implode(' AND ', $clauses) : '';
    return dbQuery($pdo,
        "SELECT id, session_id, event_type, page_path, target_selector, client_ts, server_ts
         FROM events WHERE website_id = ? $where ORDER BY client_ts ASC",
        $params
    )->fetchAll();
}
