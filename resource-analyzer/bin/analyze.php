#!/usr/bin/env php
<?php
require_once '../src/AutoLoader.php';
ASH\ResourceAnalyzer\AutoLoader::register();

$app = new ASH\ResourceAnalyzer\Console\ConsoleApp();
$app->run();