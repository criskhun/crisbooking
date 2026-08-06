<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\FacebookAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UnitController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

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

        return redirect()->route('dashboard')->with('status', 'Your email address has been verified.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent to your email address.');
    })->middleware('throttle:6,1')->name('verification.send');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/units/{unit}/wifi-qr', [UnitController::class, 'wifiQr'])->name('units.wifi-qr');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    Route::patch('/bookings/{booking}/change-request', [BookingController::class, 'requestChange'])->name('bookings.change-request');
    Route::patch('/bookings/{booking}/change-request/review', [BookingController::class, 'reviewChange'])->name('bookings.change-request.review');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profiles/{profile}', [ProfileController::class, 'show'])->name('profiles.show');
    Route::get('/profiles/{profile}/government-id', [ProfileController::class, 'document'])->name('profiles.document');
    Route::get('/inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::post('/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
    Route::get('/inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::get('/inquiries/{inquiry}/messages', [InquiryController::class, 'messages'])->name('inquiries.messages.index');
    Route::post('/inquiries/{inquiry}/messages', [InquiryController::class, 'message'])->name('inquiries.messages.store');
    Route::post('/inquiries/{inquiry}/typing', [InquiryController::class, 'typing'])->name('inquiries.typing');
    Route::get('/inquiry-attachments/{message}', [InquiryController::class, 'attachment'])->name('inquiries.attachments.show');
});

Route::middleware(['auth', 'active', 'verified', 'host'])->group(function () {
    Route::resource('units', UnitController::class)->except('show');
});

Route::middleware(['auth', 'active', 'verified', 'admin'])->group(function () {
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
});
