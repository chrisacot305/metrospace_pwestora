<?php
/**
 * lease_contract.php — Printable lease contract (?tenant_id=).
 * Entry point only: the logic lives in app/Controllers/DocumentController.php (leaseContract).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\DocumentController())->leaseContract();
