<?php

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

try {
    $models = Gemini::models()->list();
    foreach ($models->models as $model) {
        echo $model->name."\n";
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
