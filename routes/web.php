<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminHostApplicationController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\FacebookAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarIntegrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HostApplicationController;
use App\Http\Controllers\HostStorefrontController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ListingSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileLocationController;
use App\Http\Controllers\PriceProposalController;
use App\Http\Controllers\PublicListingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/listings/{unit}', [PublicListingController::class, 'show'])->name('listings.show');
Route::get('/hosts/{host}', HostStorefrontController::class)->name('hosts.show');
Route::get('/calendar/feed/{user}/{token}.ics', [CalendarIntegrationController::class, 'feed'])->name('calendar.feed');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');

    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::get('/auth/facebook', [FacebookAuthController::class, 'redirect'])->name('auth.facebook.redirect');
    Route::get('/auth/facebook/callback', [FacebookAuthController::class, 'callback'])->name('auth.facebook.callback');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->intended(route('dashboard'))->with('status', 'Your email address has been verified.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent to your email address.');
    })->middleware('throttle:6,1')->name('verification.send');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/search/listings', ListingSearchController::class)->name('listing-search.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/push-subscriptions', [NotificationController::class, 'subscribe'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [NotificationController::class, 'unsubscribe'])->name('push-subscriptions.destroy');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/listings/{unit}/inquire', [PublicListingController::class, 'inquire'])->name('listings.inquire');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar/integration', [CalendarIntegrationController::class, 'refresh'])->name('calendar.integration.refresh');
    Route::get('/bookings/{booking}/calendar.ics', [CalendarIntegrationController::class, 'booking'])->name('bookings.calendar');
    Route::get('/units/{unit}/wifi-qr', [UnitController::class, 'wifiQr'])->name('units.wifi-qr');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    Route::post('/bookings/{booking}/payment-proof', [BookingController::class, 'submitPaymentProof'])->name('bookings.payment-proof.store');
    Route::get('/bookings/{booking}/payment-proof', [BookingController::class, 'paymentProof'])->name('bookings.payment-proof.show');
    Route::patch('/bookings/{booking}/change-request', [BookingController::class, 'requestChange'])->name('bookings.change-request');
    Route::patch('/bookings/{booking}/change-request/review', [BookingController::class, 'reviewChange'])->name('bookings.change-request.review');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/images', [ProfileController::class, 'storeImage'])->name('profile-images.store');
    Route::patch('/profile/images/{profileImage}', [ProfileController::class, 'selectImage'])->name('profile-images.select');
    Route::delete('/profile/images/{profileImage}', [ProfileController::class, 'destroyImage'])->name('profile-images.destroy');
    Route::get('/profile/locations/provinces', [ProfileLocationController::class, 'provinces'])->name('profile.locations.provinces');
    Route::get('/profile/locations/cities', [ProfileLocationController::class, 'cities'])->name('profile.locations.cities');
    Route::get('/profile/locations/barangays', [ProfileLocationController::class, 'barangays'])->name('profile.locations.barangays');
    Route::get('/profiles/{profile}', [ProfileController::class, 'show'])->name('profiles.show');
    Route::get('/profiles/{profile}/government-id/view', [ProfileController::class, 'documentPreview'])->name('profiles.document.preview');
    Route::get('/profiles/{profile}/government-id', [ProfileController::class, 'document'])->name('profiles.document');
    Route::get('/inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::post('/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
    Route::get('/inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::get('/inquiries/{inquiry}/messages', [InquiryController::class, 'messages'])->name('inquiries.messages.index');
    Route::post('/inquiries/{inquiry}/messages', [InquiryController::class, 'message'])->name('inquiries.messages.store');
    Route::post('/inquiries/{inquiry}/typing', [InquiryController::class, 'typing'])->name('inquiries.typing');
    Route::post('/inquiries/{inquiry}/price-proposals', [PriceProposalController::class, 'store'])->name('inquiries.price-proposals.store');
    Route::patch('/price-proposals/{proposal}', [PriceProposalController::class, 'review'])->name('price-proposals.review');
    Route::get('/inquiry-attachments/{message}', [InquiryController::class, 'attachment'])->name('inquiries.attachments.show');
    Route::get('/host-application', [HostApplicationController::class, 'show'])->name('host-applications.show');
    Route::post('/host-application', [HostApplicationController::class, 'store'])->name('host-applications.store');
    Route::get('/host-application/{hostApplication}/business-document', [HostApplicationController::class, 'businessDocument'])->name('host-applications.business-document');
    Route::get('/host-application/{hostApplication}/identity-image/{type}', [HostApplicationController::class, 'identityImage'])->whereIn('type', ['face', 'id'])->name('host-applications.identity-image');
    Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::post('/affiliates', [AffiliateController::class, 'store'])->name('affiliates.store');
    Route::get('/affiliates/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
    Route::patch('/affiliates/{affiliate}', [AffiliateController::class, 'review'])->name('affiliates.review');
    Route::post('/affiliates/{affiliate}/messages', [AffiliateController::class, 'message'])->name('affiliates.messages.store');
    Route::post('/affiliates/{affiliate}/reviews', [ReviewController::class, 'affiliate'])->name('affiliates.reviews.store');
    Route::post('/bookings/{booking}/reviews', [ReviewController::class, 'booking'])->name('bookings.reviews.store');
    Route::get('/workspace/clients', [WorkspaceController::class, 'clients'])->name('workspace.clients');
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
});

Route::middleware(['auth', 'active', 'verified', 'host'])->group(function () {
    Route::post('/unit-drafts', [UnitController::class, 'saveDraft'])->name('unit-drafts.store');
    Route::delete('/unit-drafts/{draft}', [UnitController::class, 'destroyDraft'])->name('unit-drafts.destroy');
    Route::patch('/units/{unit}/availability', [UnitController::class, 'updateAvailability'])->name('units.availability');
    Route::resource('units', UnitController::class)->except('show');
});

Route::middleware(['auth', 'active', 'verified', 'admin'])->group(function () {
    Route::get('/admin/host-applications', [AdminHostApplicationController::class, 'index'])->name('admin.host-applications.index');
    Route::get('/admin/host-applications/{hostApplication}', [AdminHostApplicationController::class, 'show'])->name('admin.host-applications.show');
    Route::patch('/admin/host-applications/{hostApplication}', [AdminHostApplicationController::class, 'review'])->name('admin.host-applications.review');
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
});
