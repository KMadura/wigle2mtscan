#!/usr/bin/php
<?php

/*
 * Author: Krzysztof Madura
 * License: MIT
 *
 * This is a wigle .csv file converter which makes wigle scans
 * compatible with mtscan program. This script generates either
 * .mtscan or .mtscan.gz file format
 */

const MTSCAN_UNCOMPRESSED = 0;
const MTSCAN_COMPRESSED = 1;
const MTSCAN_BAND_ALL = 0;
const MTSCAN_BAND_24 = 24;
const MTSCAN_BAND_50 = 50;

function showUsageAndExit($warning = null)
{
    if ($warning !== null) {
        echo $warning . "\n\n";
    }

    echo "Usage: \n\n./wigle2mtscan.php <1> <2> <3> <4> or php -f wigle2mtscan.php <1> <2> <3> <4>\n";
    echo "1 argument (required): input file name\n";
    echo "2 argument (required): output file name with .mtscan or .mtscan.gz extension\n";
    echo "3 argument (optional): band type, options: 2, 2.4, 5, 5.0\n";
    echo "4 argument (optional): starting date in YYYY-MM-DD format or anything else if you want to ignore starting date\n";
    echo "5 argument (optional): ending date in YYYY-MM-DD format\n";

    exit;
}

function frequencyConversion($channel)
{
    $channel = (int)$channel;

    if($channel >= 1 && $channel <= 14) {
        return 2407+($channel*5);
    }

    if($channel >= 32 && $channel <= 180) {
        return 5000+($channel*5);
    }

    return 0;
}

function encryptionColumnDecoder($column) {
    $encryption = (object)array(
        'wep' => false,
        'wpa' => false,
        'wpa2' => false,
        'wpa3' => false,
        'on' => false
    );

    if(!preg_match_all('/\[([^\]]+)]/', $column, $matches)) return $encryption;

    foreach($matches[1] as $value) {
        if(preg_match('/^WEP/', $value)) {
            $encryption->wep = true;
        } else
        if(preg_match('/^WPA(?:\D|$)/', $value)) {
            $encryption->wpa = true;
        } else
        if(preg_match('/^WPA2/', $value)) {
            $encryption->wpa2 = true;
        } else
        if(preg_match('/^WPA3/', $value)) {
            $encryption->wpa3 = true;
        }
    }

    $encryption->on = $encryption->wep || $encryption->wpa || $encryption->wpa2 || $encryption->wpa3;

    return $encryption;
}

//
// Arguments validation
//

// Input file
if (!isset($argv[1])) {
    showUsageAndExit("Missing input file");
}
$inputFile = $argv[1];
if (!file_exists($inputFile)) {
    showUsageAndExit("File doesn't exist");
}

// Output file
if (!isset($argv[2])) {
    showUsageAndExit("Missing output file");
}
$outputFile = $argv[2];
$fileCompression = MTSCAN_UNCOMPRESSED;
if (preg_match('/\.mtscan$/', $outputFile)) {
    $fileCompression = MTSCAN_UNCOMPRESSED;
} else {
    if (preg_match('/\.mtscan\.gz/', $outputFile)) {
        $fileCompression = MTSCAN_COMPRESSED;
    } else {
        showUsageAndExit("Unknown output file type");
    }
}
if (file_exists($outputFile)) {
    unlink($outputFile);
}

// Band type 2.4 or 5GHz
$bandType = MTSCAN_BAND_ALL;
if (isset($argv[3])) switch ($argv[3]) {
    case '2':
    case '2.4':
        $bandType = MTSCAN_BAND_24;
        break;
    case '5':
    case '5.0':
        $bandType = MTSCAN_BAND_50;
        break;
}

// Starting time
$startingTime = 0;
if (isset($argv[4])) {
    if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $argv[4])) {
        $startingTime = strtotime($argv[4]);
    }
}

// Ending time
$endingTime = 0;
if (isset($argv[5])) {
    if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $argv[5])) {
        $endingTime = strtotime($argv[5]) + 86400;
    }
}

//
// Main logic
//

$f = fopen($inputFile, 'r');

// Ignoring header
fgetcsv($f);

// Ignoring column names
fgetcsv($f);

$ignoredBadRow = 0;
$ignoredNonWifi = 0;
$foundWifiRows = 0;
$ignoredWifiRows = 0;
$mergedWifiRows = 0;

$networks = array();

while ($data = fgetcsv($f)) {
    if (sizeof($data) < 10) {
        $ignoredBadRow++;
        continue;
    }

    // Ignoring BT, BLE and mobile phone stations
    if ($data[10] !== 'WIFI') {
        $ignoredNonWifi++;
        continue;
    }

    // Band selection
    if ($bandType == MTSCAN_BAND_24) {
        if ((int)$data[4] > 14) {
            $ignoredWifiRows++;
            continue;
        }
    } else {
        if ($bandType == MTSCAN_BAND_50) {
            if ((int)$data[4] < 15) {
                $ignoredWifiRows++;
                continue;
            }
        }
    }

    // Time validation
    $firstTime = strtotime($data[3]);

    if ($firstTime < $startingTime) {
        $ignoredWifiRows++;
        continue;
    }

    if ($endingTime > 0 && $firstTime > $endingTime) {
        $ignoredWifiRows++;
        continue;
    }

    // Network parameters
    $mac = preg_replace('/[^0-9A-F]/', '', strtoupper($data[0]));
    $ssid = utf8_encode($data[1]);
    $frequency = frequencyConversion($data[4]);
    $encryption = encryptionColumnDecoder($data[2]);
    $signal = (int)$data[5];

    if (isset($networks[$mac])) {
        $networks[$mac]['last'] = $firstTime;
        if ($signal > $networks[$mac]['s']) {
            $networks[$mac]['s'] = $signal;
        }

        $networks[$mac]['signals'][] = array(
            't' => $firstTime,
            's' => $signal
        );

        $mergedWifiRows++;
        continue;
    }

    if ($frequency == 0) {
        $ignoredBadRow++;
        continue;
    }

    $row = array();
    $row['freq'] = $frequency;
    $row['chan'] = '20'; // wigle csv doesn't provide this information
    $row['mode'] = ''; // wigle csv doesn't provide this information
    $row['ssid'] = $ssid;
    $row['name'] = ''; // wigle csv doesn't provide this information
    $row['s'] = $signal;
    $row['priv'] = $encryption->on ? 1 : 0;
    $row['first'] = $firstTime;
    $row['last'] = $firstTime;
    $row['lat'] = (float)$data[6];
    $row['lon'] = (float)$data[7];
    $row['signals'] = [
        [
            't' => $firstTime,
            's' => $signal
        ]
    ];

    /*
     * missing columns due to a lack of implementation in wigle:
     * ros, airmax, airmax-ac-ptp, airmax-ac-ptmp, airmax-ac-mixed
     * ss - streams
     * tdma
     * ns - nstreme
     * wds
     * br - bridge
     */

    /*
     * unnecessary columns:
     * azi - azimuth
     */

    $networks[$mac] = $row;

    $foundWifiRows++;
}

fclose($f);

if ($ignoredBadRow > 0) {
    echo "Found bad rows: $ignoredBadRow\n";
}
if ($ignoredNonWifi > 0) {
    echo "Ignored non wifi rows: $ignoredNonWifi\n";
}
if ($ignoredWifiRows > 0) {
    echo "Ignored wifi rows: $ignoredWifiRows\n";
}
if ($mergedWifiRows > 0) {
    echo "Merged wifi rows: $mergedWifiRows\n";
}
echo "Found wifi rows: $foundWifiRows\n";

if ($fileCompression == MTSCAN_COMPRESSED) {
    file_put_contents($outputFile, gzencode(json_encode($networks)));
} else {
    file_put_contents($outputFile, json_encode($networks));
}