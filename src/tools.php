<?php

declare(strict_types=1);

namespace App;

class tools
{
    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    const MTSCAN_BAND_ALL = 0;
    const MTSCAN_BAND_24 = 2;
    const MTSCAN_BAND_50 = 5;

    public static function verifyRegex(string $regex): bool
    {
        return !(@preg_match($regex, '') === false);
    }

    public static function stripOffPhar(string $directory): string
    {
        return preg_replace('/(?:phar:\/\/|[^\/]+\.phar.*$)/', '', $directory);
    }

    public static function frequencyConversion(string $channel): int
    {
        $channel = (int)$channel;

        if ($channel >= 1 && $channel <= 14) {
            return 2407 + ($channel * 5);
        }

        if ($channel >= 32 && $channel <= 180) {
            return 5000 + ($channel * 5);
        }

        return 0;
    }

    public static function encryptionColumnDecoder(string $column): object
    {
        $encryption = (object)array(
            'wep' => false,
            'wpa' => false,
            'wpa2' => false,
            'wpa3' => false,
            'on' => false
        );

        if (!preg_match_all('/\[([^\]]+)]/', $column, $matches)) {
            return $encryption;
        }

        foreach ($matches[1] as $value) {
            if (preg_match('/^WEP/', $value)) {
                $encryption->wep = true;
                continue;
            }
            if (preg_match('/^WPA(?:\D|$)/', $value)) {
                $encryption->wpa = true;
                continue;
            }
            if (preg_match('/^WPA2/', $value)) {
                $encryption->wpa2 = true;
                continue;
            }
            if (preg_match('/^WPA3/', $value)) {
                $encryption->wpa3 = true;
            }
        }

        $encryption->on = $encryption->wep || $encryption->wpa || $encryption->wpa2 || $encryption->wpa3;

        return $encryption;
    }

    public static function parseMAC(string $column) {
        return preg_replace('/[^0-9A-F]/', '', strtoupper($column));
    }

    public static function parseBand(string $band) {
        switch($band) {
            case '2':
            case '2.4':
                return self::MTSCAN_BAND_24;
            case '5':
            case '5.0':
            case '5.7':
            case '5.8':
                return self::MTSCAN_BAND_50;
            case '2.3':
            case '2.5':
                return self::MTSCAN_BAND_24;
            case '5.1':
            case '5.2':
            case '5.3':
            case '5.4':
            case '5.5':
            case '5.6':
            case '5.9':
                return self::MTSCAN_BAND_50;
        }

        return self::MTSCAN_BAND_ALL;
    }
}