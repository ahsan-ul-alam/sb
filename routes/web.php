<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SiteAdminController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::post('/subscribe', [PublicController::class, 'subscribe'])->middleware('throttle:6,1');
Route::get('/sitemap.xml', [PublicController::class, 'sitemap']);
Route::get('/robots.txt', [PublicController::class, 'robots']);
Route::get('/og-image.png', [PublicController::class, 'ogImage']);

// cache / storage:link / migrate / composer install — admin POST or secret token (see MaintenanceController)
Route::match(['get', 'post'], '/maintenance/{task}', [MaintenanceController::class, 'run'])->middleware('throttle:10,1');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'readAll']);

    // simple user
    Route::get('/shop/products', [ShopController::class, 'products']);
    Route::get('/shop/products/{product}', [ShopController::class, 'product']);
    Route::get('/shop/selections', [ShopController::class, 'selections']);
    Route::put('/shop/selections/{product}', [ShopController::class, 'select']);
    Route::get('/shop/requests', [ShopController::class, 'requests']);
    Route::post('/shop/requests', [ShopController::class, 'storeRequest']);
    Route::post('/shop/requests/{purchaseRequest}/cancel', [ShopController::class, 'cancelRequest']);

    // admin
    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::post('/products/{product}', [AdminController::class, 'updateProduct']); // multipart-safe update
        Route::delete('/products/{product}', [AdminController::class, 'destroyProduct']);
        Route::get('/selections', [AdminController::class, 'selections']);
        Route::get('/requests', [AdminController::class, 'requests']);
        Route::put('/requests/{purchaseRequest}', [AdminController::class, 'updateRequest']);

        // landing page content, SEO and subscribers
        Route::get('/site', [SiteAdminController::class, 'index']);
        Route::post('/site/settings/{group}', [SiteAdminController::class, 'saveSettings']);
        Route::post('/site/items/{type}', [SiteAdminController::class, 'saveItem']);
        Route::post('/site/items/{type}/{item}', [SiteAdminController::class, 'saveItem']);
        Route::delete('/site/items/{type}/{item}', [SiteAdminController::class, 'deleteItem']);
        Route::get('/subscribers', [SiteAdminController::class, 'subscribers']);
        Route::delete('/subscribers/{subscriber}', [SiteAdminController::class, 'deleteSubscriber']);
    });
});
