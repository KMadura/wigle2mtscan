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

function showUsageAndExit($warning = null) {
    if ($warning !== null) {
        echo $warning."\n\n";
    }

    echo "Usage: \n\n./wigle2mtscan.php <1> <2> <3> <4> or php -f wigle2mtscan.php <1> <2> <3> <4>\n";
    echo "1 argument (required): input file name\n";
    echo "2 argument (required): output file name with .mtscan or .mtscan.gz extension\n";
    echo "3 argument (optional): band type, options: 2, 2.4, 5, 5.0\n";
    echo "4 argument (optional): starting date in YYYY-MM-DD format or anything else if you want to ignore starting date\n";
    echo "5 argument (optional): ending date in YYYY-MM-DD format\n";

    exit;
}

function frequencyConversion($channel) {
    $channel = (int)$channel;

    $frequencyChart = array(
        1 => 2412, 2 => 2417,
        3 => 2422, 4 => 2427,
        5 => 2432, 6 => 2437,
        7 => 2442, 8 => 2447,
        9 => 2452, 10 => 2457,
        11 => 2462, 12 => 2467,
        13 => 2472, 14 => 2484,
        32 => 5160, 36 => 5180,
        40 => 5200, 44 => 5220,
        48 => 5240, 52 => 5260,
        56 => 5280, 60 => 5300,
        64 => 5320, 68 => 5340,
        96 => 5480, 100 => 5500,
        104 => 5520, 108 => 5540,
        112 => 5560, 116 => 5580,
        120 => 5600, 124 => 5620,
        128 => 5640, 132 => 5660,
        136 => 5680, 140 => 5700,
        144 => 5720, 149 => 5745,
        153 => 5765, 157 => 5785,
        161 => 5805, 165 => 5825,
        169 => 5845, 173 => 5865,
        177 => 5885
    );

    if (isset($frequencyChart[$channel])) return $frequencyChart[$channel];
    return 0;
}

//
// Arguments validation
//

// Input file
if (!isset($argv[1])) showUsageAndExit("Missing input file");
$inputFile = $argv[1];
if (!file_exists($inputFile)) showUsageAndExit("File doesn't exist");

// Output file
if (!isset($argv[2])) showUsageAndExit("Missing output file");
$outputFile = $argv[2];
$fileCompression = MTSCAN_UNCOMPRESSED;
if (preg_match('/\.mtscan$/', $outputFile)) {
    $fileCompression = MTSCAN_UNCOMPRESSED;
} else
if (preg_match('/\.mtscan\.gz/', $outputFile)) {
    $fileCompression = MTSCAN_COMPRESSED;
} else {
    showUsageAndExit("Unknown output file type");
}
if (file_exists($outputFile)) unlink($outputFile);

// Band type 2.4 or 5GHz
$bandType = MTSCAN_BAND_ALL;
if (isset($argv[3])) switch($argv[3]) {
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
        $endingTime = strtotime($argv[5])+86400;
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

while($data = fgetcsv($f)) {
    if(sizeof($data) < 10) {
        $ignoredBadRow++;
        continue;
    }

    // Ignoring BT, BLE and mobile phone stations
    if($data[10] !== 'WIFI') {
        $ignoredNonWifi++;
        continue;
    }

    // Band selection
    if($bandType == MTSCAN_BAND_24) {
        if((int)$data[4] > 14) {
            $ignoredWifiRows++;
            continue;
        }
    } else
    if($bandType == MTSCAN_BAND_50) {
        if((int)$data[4] < 15) {
            $ignoredWifiRows++;
            continue;
        }
    }

    // Time validation
    $firstTime = strtotime($data[3]);

    if($firstTime < $startingTime) {
        $ignoredWifiRows++;
        continue;
    }

    if($endingTime > 0 && $firstTime > $endingTime) {
        $ignoredWifiRows++;
        continue;
    }

    // Network parameters
    $mac = strtoupper($data[0]);
    $ssid = utf8_encode($data[1]);
    $frequency = frequencyConversion($data[4]);
    $signal = (int)$data[5];

    if (isset($networks[$mac])) {
        $networks[$mac]['last'] = $firstTime;
        if($signal > $networks[$mac]['s']) $networks[$mac]['s'] = $signal;

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

    // TODO koordynaty?
    $row = array();
    $row['freq'] = $frequency;
    $row['chan'] = '20'; // TODO
    $row['mode'] = 'n'; // TODO
    $row['ss'] = 0; // TODO
    $row['ssid'] = $ssid;
    $row['name'] = ''; // TODO
    $row['s'] = $signal;
    $row['priv'] = 0; // TODO
    $row['ros'] = ''; // TODO
    $row['ns'] = 0; // TODO
    $row['tdma'] = 0; // TODO
    $row['wds'] = 0; // TODO
    $row['br'] = 0; // TODO
    $row['airmax'] = 0; // TODO
    $row['airmax-ac-ptp'] = 0; // TODO
    $row['airmax-ac-ptmp'] = 0; // TODO
    $row['airmax-ac-mixed'] = 0; // TODO
    $row['first'] = $firstTime;
    $row['last'] = $firstTime;
    $row['signals'] = [[
        't' => $firstTime,
        's' => $signal
    ]];

    $networks[$mac] = $row;

    $foundWifiRows++;
}

fclose($f);

if($ignoredBadRow > 0) echo "Found bad rows: $ignoredBadRow\n";
if($ignoredNonWifi > 0) echo "Ignored non wifi rows: $ignoredNonWifi\n";
if($ignoredWifiRows > 0) echo "Ignored wifi rows: $ignoredWifiRows\n";
echo "Found wifi rows: $foundWifiRows\n";

if($fileCompression == MTSCAN_COMPRESSED) {
    file_put_contents($outputFile, gzcompress(json_encode($networks)));
} else {
    file_put_contents($outputFile, json_encode($networks));
}