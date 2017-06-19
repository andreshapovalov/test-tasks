#!/usr/bin/env php
<?php
require_once '../src/AutoLoader.php';
\ASH\XMLProcessor\AutoLoader::register();

$config = require_once 'config.php';

$app = new ASH\XMLProcessor\Console\ConsoleApp($config);
$app->run();