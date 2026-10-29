<?php
declare(strict_types=1);

function filterEvents(array $events, string $query, string $category = '',
    bool $availableOnly = false): array
{
    $result = [];
    foreach ($events as $event) {
        if ($query !== '' && stripos($event['title'], $query) === false) {
            continue;
        }
        if ($category !== '' && $event['category'] !== $category) {
            continue;
        }
        if ($availableOnly && $event['registered'] >= $event['capacity']) {
            continue;
        }
        $result[] = $event;
    }
    return $result;
}
