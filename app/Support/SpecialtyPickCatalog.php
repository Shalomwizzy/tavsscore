<?php

namespace App\Support;

use InvalidArgumentException;

/** Shared definition for the additional daily market pages and notifications. */
class SpecialtyPickCatalog
{
    public static function get(string $type): array
    {
        $items = [
            'under35' => [
                'title' => 'Under 3.5 Goals', 'short_title' => 'Under 3.5', 'icon' => '🧊',
                'route' => 'under35-picks.index', 'admin_route' => 'under35', 'path' => '/under-3-5',
                'flag' => 'is_under35_pick', 'rank' => 'under35_rank', 'notified' => 'under35_notified',
                'market' => 'Under 3.5 Goals', 'floor' => 70.0,
            ],
            'under45' => [
                'title' => 'Under 4.5 Goals', 'short_title' => 'Under 4.5', 'icon' => '🛟',
                'route' => 'under45-picks.index', 'admin_route' => 'under45', 'path' => '/under-4-5',
                'flag' => 'is_under45_pick', 'rank' => 'under45_rank', 'notified' => 'under45_notified',
                'market' => 'Under 4.5 Goals', 'floor' => 82.0,
            ],
            'handicap' => [
                'title' => 'Asian Handicap Picks', 'short_title' => 'Handicap', 'icon' => '🛡️',
                'route' => 'handicap-picks.index', 'admin_route' => 'handicap', 'path' => '/handicap-picks',
                'flag' => 'is_handicap_pick', 'rank' => 'handicap_rank', 'notified' => 'handicap_notified',
                'market' => null, 'label_field' => 'handicap_label', 'floor' => 64.0,
            ],
        ];

        if (! isset($items[$type])) {
            throw new InvalidArgumentException("Unknown specialty pick type [{$type}].");
        }

        return $items[$type] + ['type' => $type];
    }

    public static function types(): array
    {
        return ['under35', 'under45', 'handicap'];
    }
}
