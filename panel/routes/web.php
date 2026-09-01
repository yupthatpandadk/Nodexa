<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhpMyAdminGatewayController;

$panelHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
$storefrontDomain = trim((string) env('NODEXA_STOREFRONT_DOMAIN', ''));
if ($storefrontDomain === '' && str_starts_with($panelHost, 'panel.')) {
    $storefrontDomain = substr($panelHost, 6);
}

$registerStorefront = static function (string $domain): void {
    Route::domain($domain)->group(function () {
        Route::view('/', 'storefront')->name('storefront.home');
        Route::view('/games', 'storefront');
        Route::view('/minecraft', 'storefront');
        Route::view('/fivem', 'storefront');
        Route::view('/vps', 'storefront');
        Route::view('/cart', 'storefront')->name('storefront.cart');
        Route::view('/checkout', 'storefront')->name('storefront.checkout');
        Route::view('/faq', 'storefront');
        Route::get('/panel', fn () => redirect()->away(rtrim((string) config('app.url'), '/')));
        Route::fallback(fn () => response()->view('storefront', [], 404));
    });
};

if ($storefrontDomain !== '' && $storefrontDomain !== $panelHost) {
    $registerStorefront($storefrontDomain);
    if (!str_starts_with($storefrontDomain, 'www.')) {
        $registerStorefront('www.'.$storefrontDomain);
    }
}

Route::get('/database-gateway/{token}', PhpMyAdminGatewayController::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('database.gateway');

Route::view('/admin/nodes/setup', 'admin.node-setup')->name('admin.nodes.setup');
Route::view('/admin/database-hosts', 'admin.database-hosts')->name('admin.database-hosts');
Route::view('/admin/servers/{server}/startup', 'admin.server-startup')->name('admin.server-startup');
Route::view('/admin/errors', 'admin-errors')->name('admin.errors');
Route::view('/admin/update', 'admin-update')->name('admin.update');
Route::view('/{any?}', 'app')->where('any', '.*');
