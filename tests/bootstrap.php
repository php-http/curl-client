<?php

$loader = require __DIR__.'/../vendor/autoload.php';

// Temporary fix for Puli
$loader->addClassMap(
    [
        'Puli\\GeneratedPuliFactory' => __DIR__.'/../.puli/GeneratedPuliFactory.php',
    ]
);
