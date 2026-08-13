<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            Log::error('Password reset email could not be sent.', [
                'email_hash' => hash('sha256', strtolower($request->string('email')->toString())),
                'exception' => $exception->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send the reset email right now. Please try again shortly or contact support.']);
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
