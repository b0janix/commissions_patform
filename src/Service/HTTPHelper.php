<?php

declare(strict_types=1);

namespace App\Service;

class HTTPHelper
{
    public static function getRates(string $url)
    {
        $cURLConnection = curl_init();

        curl_setopt($cURLConnection, CURLOPT_URL, $url);
        curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

        $rates = curl_exec($cURLConnection);
        curl_close($cURLConnection);

        return json_decode($rates, true);
    }
}
