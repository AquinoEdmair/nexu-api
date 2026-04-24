<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = app();
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$c = app(App\Http\Controllers\Api\MetricsController::class);
echo $c->ranking()->content();
