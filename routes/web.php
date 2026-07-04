<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CallbackController;
use App\Http\Controllers\Auth\RedirectController;
use App\Http\Controllers\BuyerQuotePoDownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\SupplierQuoteQuotationDownloadController;
use App\Http\Controllers\TermsOfServiceController;
use App\Http\Controllers\UserGuideDownloadController;
use App\Livewire\Catalog\ArticleDetail;
use App\Livewire\Catalog\CatalogHome;
use App\Livewire\Catalog\QuoteCartPage;
use App\Livewire\Catalog\RegistrationPage;
use App\Models\BuyerQuote;
use App\Models\GoodsReceiveBatch;
use App\Models\Request;
use App\Models\SupplierQuote;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Laravel\Jetstream\Http\Controllers\TeamInvitationController;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
    Route::get('/articles/{article}', ArticleDetail::class)->name('catalog.article');
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

    Route::delete('/buyer-quotes/{buyerQuote}/po/{media}', function (BuyerQuote $buyerQuote, Media $media) {
        // Verify ownership
        // Check both morph alias and full class name (Spatie stores it as morph alias)
        $isValidModelType = $media->model_type === BuyerQuote::class ||
                           $media->model_type === 'buyer_quote' ||
                           $media->model_type === 'App\\Models\\BuyerQuote';

        if (! $isValidModelType || (int) $media->model_id !== (int) $buyerQuote->id) {
            abort(404);
        }

        if ($media->collection_name !== 'buyer_po') {
            abort(404);
        }

        // Check authorization - user must be authenticated
        if (! auth()->check()) {
            abort(403);
        }

        try {
            $media->delete();

            // Return JSON response for AJAX requests
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully',
                ]);
            }

            return redirect()->back()->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete file: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete file');
        }
    })->name('buyer-quotes.po.delete');

    // Supplier quote quotation file routes
    Route::get('/supplier-quotes/{supplierQuote}/quotation/{media}', SupplierQuoteQuotationDownloadController::class)
        ->name('supplier-quotes.quotation.download');
    Route::delete('/supplier-quotes/{supplierQuote}/quotation/{media}', function (SupplierQuote $supplierQuote, Media $media) {
        $isValidModelType = $media->model_type === SupplierQuote::class
            || $media->model_type === 'supplier_quote'
            || $media->model_type === 'App\\Models\\SupplierQuote';
        if (! $isValidModelType || (int) $media->model_id !== (int) $supplierQuote->id) {
            abort(404);
        }
        if ($media->collection_name !== 'quotation') {
            abort(404);
        }
        if (! auth()->check()) {
            abort(403);
        }
        try {
            $media->delete();
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'File deleted successfully']);
            }

            return redirect()->back()->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete file: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete file');
        }
    })->name('supplier-quotes.quotation.delete');

    // Goods receive document delete (single media from a batch)
    Route::delete('/requests/{request}/goods-receive/{media}', function (Request $request, Media $media) {
        if ($media->model_type !== Request::class || (int) $media->model_id !== (int) $request->id) {
            abort(404);
        }
        if ($media->collection_name !== 'goods_receive') {
            abort(404);
        }
        if (! auth()->check()) {
            abort(403);
        }
        $batch = GoodsReceiveBatch::query()
            ->where('request_id', $request->id)
            ->whereJsonContains('media_ids', $media->id)
            ->first();
        try {
            $media->delete();
            if ($batch !== null) {
                $mediaIds = array_values(array_filter($batch->media_ids ?? [], fn ($id) => (int) $id !== (int) $media->id));
                if ($mediaIds === []) {
                    $batch->delete();
                } else {
                    $batch->update(['media_ids' => $mediaIds]);
                }
            }
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'File deleted successfully']);
            }

            return redirect()->back()->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete file: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete file');
        }
    })->name('requests.goods-receive.delete');
});
