<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email'    => ['required', 'email'],
                'password' => ['required'],
            ]);

            $throttleKey = Str::lower($credentials['email']) . '|' . $request->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);

                throw ValidationException::withMessages([
                    'email' => "Too many sign-in attempts. Try again in {$seconds} seconds.",
                ]);
            }

            // Only active users can sign in (users.is_active).
            if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
                RateLimiter::clear($throttleKey);
                $request->session()->regenerate();

                return redirect()->intended(route('dashboard'));
            }

            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Login failed unexpectedly: ' . $e->getMessage());

            return back()->withErrors(['email' => 'Sign-in is unavailable right now. Try again.'])->onlyInput('email');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}