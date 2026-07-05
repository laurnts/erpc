<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CallbackController;
use App\Http\Controllers\Auth\RedirectController;
use App\Http\Controllers\BuyerQuotePoDeleteController;
use App\Http\Controllers\BuyerQuotePoDownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\RequestGoodsReceiveDeleteController;
use App\Http\Controllers\SupplierQuoteQuotationDeleteController;
use App\Http\Controllers\SupplierQuoteQuotationDownloadController;
use App\Http\Controllers\TermsOfServiceController;
use App\Http\Controllers\UserGuideDownloadController;
use App\Livewire\Catalog\CatalogHome;
use App\Livewire\Catalog\QuoteCartPage;
use App\Livewire\Catalog\RegistrationPage;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Laravel\Jetstream\Http\Controllers\TeamInvitationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/auth/redirect/{provider}', RedirectController::class)
        ->name('auth.socialite.redirect')
        ->middleware('throttle:10,1');
    Route::get('/auth/callback/{provider}', CallbackController::class)
        ->name('auth.socialite.callback')
        ->middleware('throttle:10,1');

    Route::get('/login', function () {
        return redirect()->away(url()->getAppUrl('login'));
    })->name('login');

    Route::get('/register', function () {
        return redirect()->away(url()->getAppUrl('register'));
    })->name('register');

    Route::get('/forgot-password', function () {
        return redirect()->away(url()->getAppUrl('forgot-password'));
    })->name('password.request');
});

// Public article catalog replaces the marketing homepage. CATALOG_ENABLED=false
// is the kill switch that restores the static marketing page.
if (config('catalog.enabled', true)) {
    Route::get('/', CatalogHome::class)->name('catalog.home');
    Route::get('/quote-cart', QuoteCartPage::class)->name('catalog.cart');
    Route::get('/registration', RegistrationPage::class)->name('catalog.register');
} else {
    Route::get('/', HomeController::class);
}

Route::get('/terms-of-service', TermsOfServiceController::class)->name('terms.show');
Route::get('/privacy-policy', PrivacyPolicyController::class)->name('policy.show');

Route::redirect('/dashboard', url()->getAppUrl())->name('dashboard');

Route::get('/team-invitations/{invitation}', [TeamInvitationController::class, 'accept'])
    ->middleware(['signed', 'verified', 'auth', AuthenticateSession::class])
    ->name('team-invitations.accept');

// Community redirects
Route::get('/discord', function () {
    return redirect()->away(config('services.discord.invite_url'));
})->name('discord');

// User guide download (Settings -> General)
Route::get('/user-guide/download', UserGuideDownloadController::class)
    ->middleware(['web', 'auth'])
    ->name('user-guide.download');

// Buyer Quote PO file routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/buyer-quotes/{buyerQuote}/po/{media}', BuyerQuotePoDownloadController::class)
        ->name('buyer-quotes.po.download');

    // Shipment PDF download
    Route::get('/shipments/{shipment}/pdf', \App\Http\Controllers\ShipmentPdfController::class)
        ->name('shipment.pdf');

    Route::delete('/buyer-quotes/{buyerQuote}/po/{media}', BuyerQuotePoDeleteController::class)
        ->name('buyer-quotes.po.delete');

    // Supplier quote quotation file routes
    Route::get('/supplier-quotes/{supplierQuote}/quotation/{media}', SupplierQuoteQuotationDownloadController::class)
        ->name('supplier-quotes.quotation.download');
    Route::delete('/supplier-quotes/{supplierQuote}/quotation/{media}', SupplierQuoteQuotationDeleteController::class)
        ->name('supplier-quotes.quotation.delete');

    // Goods receive document delete (single media from a batch)
    Route::delete('/requests/{request}/goods-receive/{media}', RequestGoodsReceiveDeleteController::class)
        ->name('requests.goods-receive.delete');
});
