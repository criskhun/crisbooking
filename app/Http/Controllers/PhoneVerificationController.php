<?php

namespace App\Http\Controllers;

use App\Services\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class PhoneVerificationController extends Controller
{
    public function send(Request $request, SmsSender $sms): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:40']]);
        $phone = PhoneNumber::normalize($request->string('phone')->toString());

        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number, including its country code.']);
        }

        $code = (string) random_int(100000, 999999);
        $request->session()->put('phone_verification', [
            'phone' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
        ]);

        try {
            $sms->send($phone, "Your CrisBooking verification code is {$code}. It expires in 10 minutes.");
        } catch (Throwable $exception) {
            $request->session()->forget('phone_verification');

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $response = ['message' => 'A 6-digit verification code was sent to '.$phone.'.'];

        if (config('services.sms.driver') === 'log' && app()->environment(['local', 'testing'])) {
            $response['debug_code'] = $code;
            $response['message'] = 'Development mode: use the code shown below. Configure Twilio to send a real SMS.';
        }

        return response()->json($response);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'code' => ['required', 'digits:6'],
        ]);
        $phone = PhoneNumber::normalize($validated['phone']);
        $verification = $request->session()->get('phone_verification');

        if (! $phone || ! $verification || $verification['phone'] !== $phone) {
            throw ValidationException::withMessages(['code' => 'Request a new code for this mobile number.']);
        }

        if ($verification['expires_at'] < now()->timestamp) {
            $request->session()->forget('phone_verification');
            throw ValidationException::withMessages(['code' => 'This code has expired. Request a new one.']);
        }

        if ($verification['attempts'] >= 5) {
            $request->session()->forget('phone_verification');
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($validated['code'], $verification['code'])) {
            $verification['attempts']++;
            $request->session()->put('phone_verification', $verification);
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }

        $request->session()->forget('phone_verification');
        $request->session()->put('phone_verified_for', $phone);

        $user = $request->user();
        if (PhoneNumber::normalize((string) $user->phone) === $phone) {
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        }

        return response()->json(['message' => 'Mobile number verified.', 'phone' => $phone]);
    }
}
