<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CallbackController;
use App\Http\Controllers\Auth\RedirectController;
use App\Http\Controllers\BuyerQuotePoDownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\TermsOfServiceController;
use App\Models\BuyerQuote;
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

Route::get('/', HomeController::class);

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
        
        if (!$isValidModelType || (int) $media->model_id !== (int) $buyerQuote->id) {
            abort(404);
        }
        
        if ($media->collection_name !== 'buyer_po') {
            abort(404);
        }
        
        // Check authorization - user must be authenticated
        if (!auth()->check()) {
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
                    'message' => 'Failed to delete file: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to delete file');
        }
    })->name('buyer-quotes.po.delete');
});
