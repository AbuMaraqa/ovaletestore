<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\AuthRedirectResponse;
use App\Models\User;
use App\Support\Seo\SeoData;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Spatie\Honeypot\Honeypot;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Routes are registered inside the locale prefix (routes/auth.php).
        Fortify::ignoreRoutes();

        // Staff → admin panel, customers → their account.
        $this->app->singleton(LoginResponse::class, AuthRedirectResponse::class);
        $this->app->singleton(RegisterResponse::class, AuthRedirectResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, AuthRedirectResponse::class);
    }

    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Deactivated accounts cannot sign in (same generic error as wrong credentials — no account enumeration).
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

            return $user && $user->is_active && Hash::check($request->string('password')->toString(), $user->password)
                ? $user
                : null;
        });

        Fortify::createUsersUsing(CreateNewUser::class);

        // Public auth pages use the storefront look (customers and staff share one login).
        $seo = fn (string $title) => SeoData::make($title)->noindex();
        Fortify::loginView(fn () => Inertia::render('Storefront/Auth/Login', ['status' => session('status'), 'seo' => $seo(__('common.login'))]));
        Fortify::registerView(fn () => Inertia::render('Storefront/Auth/Register', [
            'honeypot' => app(Honeypot::class)->toArray(),
            'seo' => $seo(__('common.register')),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Storefront/Auth/ForgotPassword', ['status' => session('status'), 'seo' => $seo(__('common.forgot_password'))]));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Storefront/Auth/ResetPassword', [
            'seo' => $seo(__('common.reset_password')),
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return [Limit::perMinute(5)->by($throttleKey), Limit::perMinute(20)->by($request->ip())];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
