<?php declare(strict_types=1);

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('HistoryHeraldry\\DatabaseTranslations\\', dirname(__DIR__) . '/src');

require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
