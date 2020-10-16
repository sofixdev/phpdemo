<?php


namespace App\Services\QrCode\Parse;

use Illuminate\Support\Facades\Log;

trait QrCodesLoggingTrait {

    protected function log($message, $context = [], $method = 'info')
    {
        Log::channel('qr_codes')->$method($message, $context);
    }

}
