<?php
// bootstrap.php for debug scripts

// Show all errors for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change directory to the API root to fix relative paths
chdir(__DIR__ . '/..');

// Define a base path to make includes reliable
define('API_ROOT', getcwd());

// Include necessary files
include_once 'config/database_auto.php';
include_once 'classes/ActiveTrip.php';
include_once 'classes/TripRequest.php';
include_once 'classes/TripBid.php';
include_once 'classes/User.php';

// Any other global setup for debug scripts can go here

?>