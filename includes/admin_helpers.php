<?php
// includes/admin_helpers.php
// Badge renderers, icon maps, and category metadata used in admin.php

// ── Badge helpers ─────────────────────────────────────────────────

function severityBadge(string $s): string {
    $map = [
        'low'      => ['bg-emerald-100 text-emerald-700', '🟢 Low'],
        'medium'   => ['bg-amber-100 text-amber-700',     '🟡 Medium'],
        'high'     => ['bg-orange-100 text-orange-700',   '🔴 High'],
        'critical' => ['bg-red-100 text-red-700',         '🚨 Critical'],
    ];
    [$cls, $label] = $map[$s] ?? ['bg-gray-100 text-gray-600', $s];
    return "<span class='inline-block text-xs font-semibold px-2 py-0.5 rounded-full $cls'>$label</span>";
}

function statusBadge(string $s): string {
    $map = [
        'open'        => ['bg-red-100 text-red-600',    'Open'],
        'in_progress' => ['bg-blue-100 text-blue-600',  'In Progress'],
        'resolved'    => ['bg-green-100 text-green-600','Resolved'],
    ];
    [$cls, $label] = $map[$s] ?? ['bg-gray-100 text-gray-600', $s];
    return "<span class='inline-block text-xs font-semibold px-2 py-0.5 rounded-full $cls'>$label</span>";
}

function typeIcon(string $t): string {
    $icons = [
        'fire'       => '🔥',
        'medical'    => '🏥',
        'accident'   => '⚠️',
        'suspicious' => '👁️',
        'theft'      => '🔓',
        'flooding'   => '🌊',
        'earthquake' => '🌍',
        'other'      => '📋',
    ];
    return $icons[$t] ?? '📋';
}

// ── Emergency category metadata ───────────────────────────────────

function getEcCategories(): array {
    return [
        'fire'    => ['label' => 'Fire Department',     'icon' => '🔥', 'badge' => 'bg-red-100 text-red-700',        'dot' => 'bg-red-500'],
        'medical' => ['label' => 'Medical / Ambulance', 'icon' => '🚑', 'badge' => 'bg-emerald-100 text-emerald-700','dot' => 'bg-emerald-500'],
        'police'  => ['label' => 'Police',              'icon' => '👮', 'badge' => 'bg-blue-100 text-blue-700',      'dot' => 'bg-blue-500'],
        'campus'  => ['label' => 'Campus Services',     'icon' => '🏫', 'badge' => 'bg-amber-100 text-amber-700',   'dot' => 'bg-amber-500'],
        'other'   => ['label' => 'Other',               'icon' => '📞', 'badge' => 'bg-gray-100 text-gray-600',     'dot' => 'bg-gray-400'],
    ];
}

// ── Toast message map ─────────────────────────────────────────────

function getToastMap(): array {
    return [
        'resolved'    => ['✅', 'Incident marked as resolved', 'bg-green-600'],
        'deleted'     => ['🗑️', 'Incident deleted',            'bg-gray-700'],
        'user_deleted'=> ['🗑️', 'User deleted',                'bg-gray-700'],
        'ec_added'    => ['✅', 'Emergency contact added',      'bg-green-600'],
        'ec_deleted'  => ['🗑️', 'Contact deleted',             'bg-gray-700'],
        'ec_updated'  => ['🔄', 'Contact updated',             'bg-blue-600'],
    ];
}
