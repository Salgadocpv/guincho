<?php
// bootstrap.php for debug scripts

// Show all errors for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define a base path to make includes reliable
define('API_ROOT', realpath(__DIR__ . '/..'));

// Include necessary files
include_once API_ROOT . '/config/database_auto.php';
include_once API_ROOT . '/classes/ActiveTrip.php';
include_once API_ROOT . '/classes/TripRequest.php';
include_once API_ROOT . '/classes/TripBid.php';
include_once API_ROOT . '/classes/User.php';

// Any other global setup for debug scripts can go here

?>