<?php

namespace ROCKS;

use Exception;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;
use TelegramBot\Api\InvalidArgumentException;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class telegramBot
{
    public BotApi $bot;
    public Client $client;

    public function __construct()
    {
        $this->bot = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
        $this->client = new Client($_ENV['TELEGRAM_BOT_TOKEN']);

    }

    public function sendNotification($from, $gig, $message, $url): bool
    {
        try{
            $keyboard = new InlineKeyboardMarkup(
                [
                    [
                        ['text' => 'View and Reply', 'url' => $url],
                    ]
                ]
            );


            $this->bot->sendMessage($_ENV['TELEGRAM_USER_ID'], "New E-mail Notification 🚨 \r\n from: $from \r\n gig: $gig \r\n message: $message", null, true, null, $keyboard);

            return true;
        }catch(\TelegramBot\Api\Exception $e){
            print($e->getMessage());
            return false;
        }
    }
}