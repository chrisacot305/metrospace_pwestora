<?php
/**
 * modules/revenue.php  (SuperAdmin → "Revenue & Commission")
 * Controller for Revenue & Commission.
 */

// --- SAFETY CHECKS ---
$modelPath = __DIR__ . '/../models/RevenueModel.php';
$viewPath = __DIR__ . '/../views/superadmin/revenue.php';

if (!file_exists($modelPath)) {
    die("<div class='card' style='margin: 20px; border: 2px solid red; color: red;'><strong>Fatal Error:</strong> Cannot find <b>models/RevenueModel.php</b></div>");
}
if (!file_exists($viewPath)) {
    die("<div class='card' style='margin: 20px; border: 2px solid red; color: red;'><strong>Fatal Error:</strong> Cannot find <b>views/superadmin/revenue.php</b></div>");
}
// ---------------------

require_once $modelPath;
$revenueModel = new RevenueModel($pdo);

// Capture filters if applied
$filterType = $_GET['type'] ?? null;
$searchQuery = $_GET['search'] ?? null;

// 1. Get Dropdown Options & Summary Data
$availableTypes = $revenueModel->getAvailablePropertyTypes();
$feePercent = $revenueModel->getPlatformFeePercent();
$summary = $revenueModel->getSummaryStats($filterType, $searchQuery);

$activeLeases = (int) $summary['active_leases'];
$mrr = (float) $summary['mrr'];
$avgRent = (float) $summary['avg_rent'];
$estCommission = $mrr * ($feePercent / 100);

// 2. Get Distribution Data (Filtered)
$byType = $revenueModel->getRevenueByType($filterType, $searchQuery);
$byLessor = $revenueModel->getRevenueByLessor($filterType, $searchQuery);

// 3. Math for Progress Bars
$typeMax = $byType ? max(array_column($byType, 'total')) : 0;
$lessorMax = $byLessor ? max(array_column($byLessor, 'total')) : 0;

$barColors = ['var(--primary)', 'var(--accent)', 'var(--success)', 'var(--warning)', 'var(--error)', 'var(--steel)'];

// Load View
require_once $viewPath;