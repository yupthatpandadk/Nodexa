<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;
use Pterodactyl\Http\Controllers\SupportTicketController;
use Pterodactyl\Http\Controllers\KnowledgebaseController;
use Pterodactyl\Http\Controllers\StatusController;
use Pterodactyl\Http\Controllers\ClientHubController;

Route::get('/csrf/refresh', function () {
    $response = response()
        ->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');

    // Older Nodexa installs may still have a host-only session cookie on a
    // subdomain. Once the shared .nordicnode.org cookie is active, expire the
    // old host-only cookie so the browser cannot send two competing sessions.
    $sessionDomain = ltrim((string) config('session.domain'), '.');
    $host = strtolower(request()->getHost());

    if ($sessionDomain === 'nordicnode.org' && $host !== 'nordicnode.org') {
        $response->withCookie(cookie()->forget((string) config('session.cookie'), '/', null));
    }

    return $response;
})->name('csrf.refresh');

Route::get('/', [StorefrontController::class, 'index'])->name('store.home');
Route::get('/status', [StatusController::class, 'index'])->name('store.status');
Route::get('/r/{code}', [ClientHubController::class, 'referral'])->name('store.referral');
Route::get('/hosting', [StorefrontController::class, 'hosting'])->name('store.hosting');
Route::get('/features', [StorefrontController::class, 'features'])->name('store.features');
Route::get('/about', [StorefrontController::class, 'about'])->name('store.about');
Route::get('/support', [StorefrontController::class, 'support'])->name('store.support');
Route::get('/knowledgebase', [KnowledgebaseController::class, 'index'])->name('knowledgebase.index');
Route::get('/knowledgebase/search', [KnowledgebaseController::class, 'search'])->name('knowledgebase.search');
Route::get('/knowledgebase/media/{filename}', [KnowledgebaseController::class, 'media'])->where('filename', '[A-Za-z0-9._-]+')->name('knowledgebase.media');
Route::get('/knowledgebase/category/{category:slug}', [KnowledgebaseController::class, 'category'])->name('knowledgebase.category');
Route::get('/knowledgebase/article/{article:slug}', [KnowledgebaseController::class, 'article'])->name('knowledgebase.article');
Route::post('/knowledgebase/article/{article:slug}/feedback', [KnowledgebaseController::class, 'feedback'])->name('knowledgebase.feedback');
Route::get('/store', [StorefrontController::class, 'hosting'])->name('store.index');
Route::get('/store/{product:slug}', [StorefrontController::class, 'show'])->name('store.product');
Route::middleware('auth.session')->group(function () {
    Route::get('/client', [StorefrontController::class, 'dashboard'])->name('store.client');
    Route::get('/client/servers', [StorefrontController::class, 'clientServers'])->name('store.client.servers');
    Route::get('/client/billing', [StorefrontController::class, 'clientBilling'])->name('store.client.billing');
    Route::get('/client/profile', [StorefrontController::class, 'clientProfile'])->name('store.client.profile');
    Route::get('/client/hub', [ClientHubController::class, 'index'])->name('store.client.hub');
    Route::post('/client/hub/notifications/{notification}/read', [ClientHubController::class, 'markNotification'])->name('store.client.hub.notifications.read');
    Route::post('/client/hub/notifications/read-all', [ClientHubController::class, 'markAllNotifications'])->name('store.client.hub.notifications.read-all');
    Route::post('/client/hub/backups', [ClientHubController::class, 'saveBackupPolicy'])->name('store.client.hub.backups.save');
    Route::post('/client/hub/addons', [ClientHubController::class, 'requestAddon'])->name('store.client.hub.addons.order');
    Route::post('/client/hub/affiliate', [ClientHubController::class, 'enableAffiliate'])->name('store.client.hub.affiliate.enable');
    Route::post('/client/hub/organizations', [ClientHubController::class, 'createOrganization'])->name('store.client.hub.organizations.create');
    Route::post('/client/hub/organizations/{organization}/members', [ClientHubController::class, 'addOrganizationMember'])->name('store.client.hub.organizations.members');
    Route::post('/client/hub/webhooks', [ClientHubController::class, 'createWebhook'])->name('store.client.hub.webhooks.create');
    Route::post('/client/hub/webhooks/{webhook}/test', [ClientHubController::class, 'testWebhook'])->name('store.client.hub.webhooks.test');
    Route::delete('/client/hub/webhooks/{webhook}', [ClientHubController::class, 'deleteWebhook'])->name('store.client.hub.webhooks.delete');
    Route::post('/client/hub/api-tokens', [ClientHubController::class, 'createApiToken'])->name('store.client.hub.tokens.create');
    Route::delete('/client/hub/api-tokens/{token}', [ClientHubController::class, 'revokeApiToken'])->name('store.client.hub.tokens.revoke');
    Route::get('/client/tickets', [SupportTicketController::class, 'index'])->name('store.client.tickets');
    Route::get('/client/tickets/new', [SupportTicketController::class, 'create'])->name('store.client.tickets.create');
    Route::post('/client/tickets', [SupportTicketController::class, 'store'])->name('store.client.tickets.store');
    Route::get('/client/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('store.client.tickets.show');
    Route::post('/client/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('store.client.tickets.reply');
    Route::post('/client/tickets/{ticket}/close', [SupportTicketController::class, 'close'])->name('store.client.tickets.close');
    Route::get('/client/tickets/{ticket}/attachments/{attachment}', [SupportTicketController::class, 'attachment'])->name('store.client.tickets.attachment');
    Route::get('/account', [StorefrontController::class, 'dashboard'])->name('store.dashboard');
    Route::post('/store/{product:slug}/order', [StorefrontController::class, 'order'])->name('store.order');
    Route::get('/billing/orders', [StorefrontController::class, 'orders'])->name('store.orders');
    Route::get('/billing/orders/{order}/checkout', [StorefrontController::class, 'checkout'])->name('store.checkout');
});
