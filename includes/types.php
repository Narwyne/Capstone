<?php
// includes/types.php — incident types (admin-managed), with a built-in fallback.
function incidentTypes(?PDO $pdo, bool $activeOnly = true): array {
    $defaults = [
        ['slug'=>'fire','label'=>'Fire / Smoke','icon'=>'🔥','is_active'=>1],
        ['slug'=>'medical','label'=>'Medical Emergency','icon'=>'🏥','is_active'=>1],
        ['slug'=>'accident','label'=>'Accident / Injury','icon'=>'⚠️','is_active'=>1],
        ['slug'=>'suspicious','label'=>'Suspicious Activity','icon'=>'👁️','is_active'=>1],
        ['slug'=>'theft','label'=>'Theft / Robbery','icon'=>'🔓','is_active'=>1],
        ['slug'=>'flooding','label'=>'Flooding','icon'=>'🌊','is_active'=>1],
        ['slug'=>'earthquake','label'=>'Earthquake Damage','icon'=>'🌍','is_active'=>1],
        ['slug'=>'other','label'=>'Other','icon'=>'📋','is_active'=>1],
    ];
    if (!$pdo) return $defaults;
    try {
        $sql = "SELECT slug,label,icon,is_active FROM incident_types"
             . ($activeOnly ? " WHERE is_active=1" : "") . " ORDER BY sort_order, id";
        $rows = $pdo->query($sql)->fetchAll();
        return $rows ?: $defaults;
    } catch (PDOException $e) {
        return $defaults;
    }
}
