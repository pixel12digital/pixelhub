<?php

require 'vendor/autoload.php';
require 'config/database.php';

$db = \PixelHub\Core\DB::getConnection();

require 'database/seeds/SeedOpportunityLostReasons.php';

$seed = new SeedOpportunityLostReasons();
$seed->run($db);
