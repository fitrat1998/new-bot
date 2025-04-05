<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;

class TelegramServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Nutgram::class, function ($app) {
            $bot = new Nutgram(config('services.telegram.bot_token'));

            // Bu yerda bot handlerlarini ro'yxatdan o'tkazishingiz mumkin
            $bot->onCommand('start', function (Nutgram $bot) {
                $bot->sendMessage('Salom! Bu Laravelda yaratilgan Telegram bot!');
            });

            return $bot;
        });
    }

    public function boot()
    {
        //
    }
}
