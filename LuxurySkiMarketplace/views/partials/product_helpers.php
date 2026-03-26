<?php
/**
 * Product display helpers for visual content.
 */

/**
 * Get an SVG icon for a product type.
 */
function getProductIcon($icon) {
    $icons = [
        'jacket' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M50 15 L35 20 L25 45 L15 40 L10 65 L25 70 L25 55 L30 85 L70 85 L75 55 L75 70 L90 65 L85 40 L75 45 L65 20 Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M45 15 Q50 20 55 15" fill="none" stroke="currentColor" stroke-width="2"/><line x1="50" y1="20" x2="50" y2="50" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4,3"/></svg>',

        'suit' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M50 10 L35 15 L25 40 L15 35 L10 55 L25 60 L28 50 L30 85 L35 95 L65 95 L70 85 L72 50 L75 60 L90 55 L85 35 L75 40 L65 15 Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M45 10 Q50 15 55 10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="50" y1="15" x2="50" y2="55" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4,3"/><line x1="38" y1="65" x2="62" y2="65" stroke="currentColor" stroke-width="1" opacity="0.5"/></svg>',

        'pants' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M30 15 L30 10 L70 10 L70 15 L68 50 L65 90 L55 90 L50 55 L45 90 L35 90 L32 50 Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><line x1="35" y1="15" x2="65" y2="15" stroke="currentColor" stroke-width="1.5"/><ellipse cx="50" cy="13" rx="4" ry="2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',

        'base-layer' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M50 15 L35 20 L28 35 L20 32 L20 45 L28 42 L30 75 L70 75 L72 42 L80 45 L80 32 L72 35 L65 20 Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M45 15 Q50 20 55 15" fill="none" stroke="currentColor" stroke-width="2"/><path d="M42 40 Q50 48 58 40" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.5"/><path d="M40 52 Q50 58 60 52" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3"/></svg>',

        'gloves' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M35 85 L30 50 L30 35 L35 25 L40 25 L40 45 L45 20 L50 20 L50 45 L55 18 L60 18 L58 45 L63 22 L68 22 L65 50 L70 38 L75 40 L68 60 L65 85 Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><line x1="32" y1="55" x2="67" y2="55" stroke="currentColor" stroke-width="1.5" opacity="0.4"/></svg>',

        'helmet' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M25 55 Q25 20 50 15 Q75 20 75 55 L75 62 Q75 70 68 72 L32 72 Q25 70 25 62 Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><line x1="25" y1="55" x2="75" y2="55" stroke="currentColor" stroke-width="1.5"/><path d="M35 55 Q50 45 65 55" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4"/><ellipse cx="50" cy="80" rx="12" ry="4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',

        'skis' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><line x1="35" y1="10" x2="30" y2="88" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/><line x1="65" y1="10" x2="60" y2="88" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/><path d="M30 88 Q28 95 33 95" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M60 88 Q58 95 63 95" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><rect x="29" y="40" width="8" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="59" y="40" width="8" height="12" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
    ];

    return $icons[$icon] ?? $icons['jacket'];
}

/**
 * Get brand-specific gradient colors for product placeholders.
 */
function getBrandColors($brand) {
    $colors = [
        'Goldbergh'        => ['#1a1a2e', '#2d2d5e', '#c9a96e'],
        'Moncler'          => ['#1c1c1c', '#3a3a3a', '#e8e4df'],
        'Fusalp'           => ['#0a1628', '#1a3050', '#7ba7c9'],
        'Bogner'           => ['#2a0a0a', '#4a1a1a', '#d4a574'],
        'Christian Lacroix' => ['#3a0a1a', '#6a1a3a', '#d4a06e'],
        'Toni Sailer'      => ['#1a1a30', '#2a2a50', '#c0c0d0'],
        'POC'              => ['#2a1a1a', '#4a2a2a', '#e0b0a0'],
        'Stockli'          => ['#0a1a2a', '#1a3a5a', '#a0c0e0'],
        'Perfect Moment'   => ['#2a0a0a', '#5a1a1a', '#e06060'],
    ];

    return $colors[$brand] ?? ['#1a1a2e', '#2d2d5e', '#c9a96e'];
}

/**
 * Get category label for display.
 */
function getCategoryLabel($category) {
    $labels = [
        'goldbergh'      => 'Goldbergh Collection',
        'ski-collection'  => 'Pre-loved Collection',
        'deer-valley'     => 'Deer Valley Edit',
    ];
    return $labels[$category] ?? 'Collection';
}
