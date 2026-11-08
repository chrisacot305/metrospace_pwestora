<?php
/**
 * config.php — kept ONLY for backward compatibility.
 *
 * Settings and passwords now live in the .env file (see .env.example), and
 * start-up happens in app/init.php. Any old script that still does
 * require 'config.php' keeps working and gets the same $pdo and constants.
 */
require_once __DIR__ . '/app/init.php';
