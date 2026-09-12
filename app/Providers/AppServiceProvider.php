<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Ограничение частоты запросов к API.
     *
     * В Laravel 13 группа `api` лимитера по умолчанию не содержит, и без него
     * на `/api/login` можно было гонять подбор пароля с той скоростью, какую
     * выдержит железо. Считаем по пользователю, а если он не представился —
     * по адресу: иначе один человек с бесконечным токеном занимал бы лимит
     * всей своей сети.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
