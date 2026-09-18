<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Mail\Markdown;
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

        // Имя, название задачи и комментарий подставляются в Markdown письма.
        // Без экранирования [Войти](https://evil.example) становился ссылкой
        // от имени сервиса. Фреймворк экранирует сам и в HTML, и в тексте,
        // в отличие от ручной замены, которая оставляла в письме \ и &lt;
        Markdown::withSecuredEncoding();
    }

    /**
     * Ограничение частоты запросов к API.
     *
     * В Laravel 13 группа `api` лимитера по умолчанию не содержит, и без него
     * на `/api/login` можно было гонять подбор пароля с той скоростью, какую
     * выдержит железо. Считаем по пользователю, а если он не представился:
     * по адресу: иначе один человек с бесконечным токеном занимал бы лимит
     * всей своей сети.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Вход и регистрация: 10 попыток в минуту на почту, с каких бы адресов
        // ни шли, чтобы подбор пароля к одному аккаунту не размазывался по
        // ботнету, и 20 на адрес, чтобы с одного адреса не перебирали пароли
        // по списку почт. 20, а не 10: за общим офисным прокси сидят многие
        RateLimiter::for('auth', function (Request $request) {
            // Почта массивом отбивается валидацией, лимитер не должен падать раньше
            $email = User::normalizeEmail($request->input('email'));
            $email = is_string($email) ? $email : '';

            return [
                Limit::perMinute(10)->by('auth-email:'.$email),
                Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
            ];
        });
    }
}
