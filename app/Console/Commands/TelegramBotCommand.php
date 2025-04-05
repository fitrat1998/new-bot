<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\TelegramController;

class TelegramBotCommand extends Command
{
    protected $signature = 'telegram:run';
    protected $description = 'Telegram botni polling rejimida ishga tushirish';

    public function handle()
    {
        $this->info('Telegram bot polling rejimida ishga tushdi...');

        $controller = app(TelegramController::class);

        // Calling the polling method
        $controller->handlePolling();
    }
}
