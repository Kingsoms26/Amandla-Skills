<?php
function getVerificationBadge($tier, $size = 'normal') {
    // Define styles
    $badgeConfig = [
        'verified' => [
            'label' => 'Verified',
            'bg' => '#dcfce7', 
            'text' => '#166534', 
            'border' => '#bbf7d0'
        ],
        'verified_pro' => [
            'label' => 'Verified Pro',
            'bg' => '#7c3aed',
            'text' => '#ffffff',
            'border' => '#7c3aed'
        ],
        'top_pro' => [
            'label' => 'Top Pro',
            'bg' => '#fbbf24', 
            'text' => '#78350f',
            'border' => '#d97706'
        ]
    ];

    if (!isset($badgeConfig[$tier]) || $tier === 'none') return '';

    $config = $badgeConfig[$tier];
    $padding = ($size === 'small') ? '0.2rem 0.6rem' : '0.4rem 1rem';
    $fontSize = ($size === 'small') ? '0.7rem' : '0.85rem';

    return "
    <style>
        .badge-pill-{$tier} {
            background-color: {$config['bg']};
            color: {$config['text']};
            border: 1px solid {$config['border']};
            padding: {$padding};
            border-radius: 50px;
            font-size: {$fontSize};
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
    </style>
    <span class='badge-pill-{$tier}'>{$config['label']}</span>
    ";
}
?>