<?php
/**
 * includes/sidebar_nav.php — one NAV array per role.
 * This is the PHP equivalent of the `NAV` constants at the bottom
 * of pwestora-lessor-web.jsx and pwestora-superadmin-web.jsx.
 * Icons are Bootstrap Icons class names (loaded via CDN in header.php).
 */

$NAV_BY_ROLE = [
    'lessor' => [
        ['id' => 'overview',      'label' => 'Dashboard',          'icon' => 'bi-speedometer2'],
        ['id' => 'listings',      'label' => 'My Properties',      'icon' => 'bi-building'],
        ['id' => 'applications',  'label' => 'Applications',       'icon' => 'bi-clipboard-check'],
        ['id' => 'tenants',       'label' => 'Tenants',            'icon' => 'bi-people'],
        ['id' => 'installments',  'label' => 'Installments',       'icon' => 'bi-wallet2'],
        ['id' => 'deposits',      'label' => 'Security Deposits',  'icon' => 'bi-shield-check'],
        ['id' => 'payments',      'label' => 'Rent Payments',      'icon' => 'bi-cash-coin'],
        ['id' => 'maintenance',   'label' => 'Maintenance',        'icon' => 'bi-tools'],
        ['id' => 'messages',      'label' => 'Messages',           'icon' => 'bi-chat-dots'],
        ['id' => 'violations',    'label' => 'Violations',         'icon' => 'bi-exclamation-triangle'],

    ],
    'superadmin' => [
        ['id' => 'overview',    'label' => 'Dashboard',        'icon' => 'bi-speedometer2'],
        ['id' => 'lessors',     'label' => 'Verify Lessors',   'icon' => 'bi-shield-check'],
        ['id' => 'properties',  'label' => 'Verify Properties','icon' => 'bi-building'],
        ['id' => 'users',       'label' => 'User Management',  'icon' => 'bi-people'],
        ['id' => 'notify',      'label' => 'Notify Lessors',   'icon' => 'bi-megaphone'],
        ['id' => 'revenue',     'label' => 'Revenue',          'icon' => 'bi-bar-chart'],
        ['id' => 'audit',       'label' => 'Audit Logs',       'icon' => 'bi-journal-text'],
        ['id' => 'settings',    'label' => 'Platform Settings','icon' => 'bi-gear'],
    ],
];
