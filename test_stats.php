<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::first();
if (!$user) {
    echo "No user found";
    exit;
}

$request = Illuminate\Http\Request::create('/api/statistics', 'GET');
$request->setUserResolver(function() use ($user) {
    return $user;
});

// Since statistics might check Sanctum token, we might need to bypass it or directly instantiate the controller
$controller = new App\Http\Controllers\Api\StatisticsController();
try {
    $res = $controller->index($request);
    echo json_encode($res->getData(), JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
