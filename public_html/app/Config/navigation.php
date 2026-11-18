<?php
/**
 * app/Config/navigation.php — sidebar menu per role (was includes/sidebar_nav.php).
 * This is the PHP equivalent of the `NAV` constants at the bottom
 * of pwestora-lessor-web.jsx and pwestora-superadmin-web.jsx.
 * "icon" values are ico-* classes from assets/css/icons.css (Pwestora's own icons).
 */

return [
    'lessor' => [
        ['id' => 'overview',      'label' => 'Dashboard',          'icon' => 'ico-speedometer2'],
        ['id' => 'listings',      'label' => 'My Properties',      'icon' => 'ico-building'],
        ['id' => 'applications',  'label' => 'Applications',       'icon' => 'ico-clipboard-check'],
        ['id' => 'tenants',       'label' => 'Tenants',            'icon' => 'ico-people'],
        ['id' => 'contract_templates', 'label' => 'Contracts', 'icon' => 'ico-file-earmark-text'],
        ['id' => 'installments',  'label' => 'Installments',       'icon' => 'ico-wallet2'],
        ['id' => 'deposits',      'label' => 'Security Deposits',  'icon' => 'ico-shield-check'],
        ['id' => 'payments',      'label' => 'Rent Payments',      'icon' => 'ico-cash-coin'],
        ['id' => 'maintenance',   'label' => 'Maintenance',        'icon' => 'ico-tools'],
        ['id' => 'messages',      'label' => 'Messages',           'icon' => 'ico-chat-dots'],
        ['id' => 'violations',    'label' => 'Violations',         'icon' => 'ico-exclamation-triangle'],

    ],
    'superadmin' => [
        ['id' => 'overview',    'label' => 'Dashboard',        'icon' => 'ico-speedometer2'],
        ['id' => 'lessors',     'label' => 'Verify Lessors',   'icon' => 'ico-shield-check'],
        ['id' => 'properties',  'label' => 'Verify Properties','icon' => 'ico-building'],
        ['id' => 'users',       'label' => 'User Management',  'icon' => 'ico-people'],
        ['id' => 'notify',      'label' => 'Notify Lessors',   'icon' => 'ico-megaphone'],
        ['id' => 'revenue',     'label' => 'Revenue',          'icon' => 'ico-bar-chart'],
        ['id' => 'audit',       'label' => 'Audit Logs',       'icon' => 'ico-journal-text'],
        ['id' => 'settings',    'label' => 'Platform Settings','icon' => 'ico-gear'],
    ],
];
