<?php

use zxf\Trace\AssetController;

app('router')->prefix('zxf/trace')->name('zxf.trace.')->group(function ($router) {
    $router->get('assets/trace.css', [AssetController::class, 'css'])->name('trace.css');
    $router->get('assets/trace.js', [AssetController::class, 'js'])->name('trace.js');
});
