<?php

require(__DIR__ . '/src/Lacore/Settings.php');
require(__DIR__ . '/tests/Lacore/Fixtures.php');
require(__DIR__ . '/src/Lacore/Bootstrap.php');

use \Lacore\Tests\Fixtures;
use \Lacore\Settings;
use \Lacore\Bootstrap;

$processing_url = getenv("PROCESSING_URL");
if ($processing_url == null) {
    $processing_url =  "https://lacore-sandbox.finixpayments.com";
}

Fixtures::$apiUrl = $processing_url;

Settings::configure([
    "root_url" => Fixtures::$apiUrl
]);

Bootstrap::init();
