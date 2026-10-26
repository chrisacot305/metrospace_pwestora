<?php
/**
 * modules/overview_superadmin.php
 * Controller for the Super Admin Overview.
 */

// Load the model from the SAME directory
require_once __DIR__ . '/../models/OverviewModel.php';

// Instantiate model using the global $pdo connection
$overviewModel = new OverviewModel($pdo);

// Fetch metrics
$totalLessors   = $overviewModel->getTotalActiveLessors();
$totalLessees   = $overviewModel->getTotalUniqueLessees();
$pendingLessors = $overviewModel->getPendingLessorsCount();
$pendingProps   = $overviewModel->getPendingPropertiesCount();
$totalPending   = $pendingLessors + $pendingProps;

// Fetch lists
$pendingLessorRows = $overviewModel->getRecentPendingLessors();
$pendingPropRows   = $overviewModel->getRecentPendingProperties();

// Render the view (assuming a views folder in public_html)
require_once __DIR__ . '/../views/superadmin/overview.php';