<?php

/*
 * Author: Krzysztof Madura
 * License: MIT
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\options;
use App\output;
use App\tools;

//
// Setting up console options
//

$options = new options();

$options->setOption('i', 'input', 'input file', options::VALUE_REQUIRED);
$options->setOption('o', 'output', 'output file', options::VALUE_REQUIRED);
$options->addAnotherLine('supported file types: .mtscan, .mtscan.gz');
$options->setOption('df', 'datefrom', 'parse rows only from this date', options::VALUE_OPTIONAL);
$options->setOption('dt', 'dateto', 'parse rows only to this date', options::VALUE_OPTIONAL);
$options->addAnotherLine('please specify a proper ISO-8601 like date, YYYY-MM-DD is sufficient');
$options->addAnotherLine('please also make sure "date to" is newer to "date from"');
$options->setOption('b', 'bandtype', '2 or 2.4 for 2.4GHz, 5 or 5.0-5.9 for 5GHz', options::VALUE_OPTIONAL);
$options->setOption('h', 'help', 'prints out this page');

$options->parse($argv);

if ($options->isOption('h')) {
    $options->printHelp();
    exit(0);
}

//
// Checking parameters validity
//

// Input file

$inputFile = $options->getOption('i')->value;

if (empty($inputFile)) {
    output::stdErr('Please insert a proper file name');
    exit(1);
}

if (!file_exists($inputFile)) {
    output::stdErr('File ' . $inputFile . ' does not exist');
    exit(1);
}

// Output file

$outputFile = $options->getOption('o')->value;

if (empty($outputFile)) {
    output::stdErr('Please insert a proper file name');
    exit(1);
}

$fileCompression = false;
$validOutputFileName = false;

if (preg_match('/\.mtscan$/', $outputFile)) {
    $validOutputFileName = true;
} else {
    if (preg_match('/\.mtscan\.gz$/', $outputFile)) {
        $validOutputFileName = true;
        $fileCompression = true;
    }
}

if (!$validOutputFileName) {
    output::stdErr('Improper output file name. Please specify .mtscan or .mtscan.gz extension');
    exit(1);
}

// Starting date (optional)

if (!$options->isOption('df')) {
    $dateFrom = 0;
} else {
    $dateFrom = strtotime($options->getOption('df')->value) ?: 0;
}

// Ending date (optional)

if (!$options->isOption('dt')) {
    $dateTo = 0;
} else {
    $dateToTmp = $options->getOption('dt')->value ?: '0000-00-00';

    $dateTo = strtotime($dateToTmp) ?: 0;

    if ($dateTo < $dateFrom) {
        output::stdErr('Please check if dates are properly specified');
        exit(1);
    }

    if ($dateTo > 0 && preg_match('/^\d{4}\D\d{2}\D\d{2}$/', $dateToTmp)) {
        // When "date to" is simplified, make sure user wants to cover rows from this whole day
        $dateTo += 86400;
    }
}

// Band

$bandType = tools::MTSCAN_BAND_ALL;

if ($options->isOption('b')) {
    $bandType = tools::parseBand($options->getOption('b')->value);
}

//
// Main logic
//

$f = fopen($inputFile, 'r');

// Ignoring header
fgetcsv($f);

// Ignoring column names
fgetcsv($f);

$statistics = (object)array(
    'ignoredBad' => 0,
    'ignoredNonWifi' => 0,
    'ignoredWifi' => 0,
    'mergedWifi' => 0,
    'foundWifi' => 0
);

$networks = array();

// Wigle const-column mapping
const WIGLE_MAC = 0;
const WIGLE_SSID = 1;
const WIGLE_ENCRYPTION = 2;
const WIGLE_TIME = 3;
const WIGLE_CHANNEL = 4;
const WIGLE_SIGNAL = 5;
const WIGLE_LAT = 6;
const WIGLE_LON = 7;
const WIGLE_TYPE = 10;

while ($data = fgetcsv($f)) {
    if (sizeof($data) < 10) {
        $statistics->ignoredBad++;
        continue;
    }

    // Ignoring BT, BLE and mobile phone stations
    if ($data[WIGLE_TYPE] !== 'WIFI') {
        $statistics->ignoredNonWifi++;
        continue;
    }

    // Band selection
    if ($bandType == tools::MTSCAN_BAND_24) {
        if ((int)$data[WIGLE_CHANNEL] > 14) {
            $statistics->ignoredWifi++;
            continue;
        }
    } else {
        if ($bandType == tools::MTSCAN_BAND_50) {
            if ((int)$data[WIGLE_CHANNEL] < 15) {
                $statistics->ignoredWifi++;
                continue;
            }
        }
    }

    // Time validation
    $firstTime = strtotime($data[WIGLE_TIME]);

    if ($firstTime < $dateFrom) {
        $statistics->ignoredWifi++;
        continue;
    }

    if ($dateTo > 0 && $firstTime > $dateTo) {
        $statistics->ignoredWifi++;
        continue;
    }

    // Network parameters
    $mac = tools::parseMAC($data[WIGLE_MAC]);
    $ssid = utf8_encode($data[WIGLE_SSID]);
    $frequency = tools::frequencyConversion($data[WIGLE_CHANNEL]);
    $encryption = tools::encryptionColumnDecoder($data[WIGLE_ENCRYPTION]);
    $signal = (int)$data[WIGLE_SIGNAL];

    if ($frequency == 0) {
        $statistics->ignoredBad++;
        continue;
    }

    $lat = (float)$data[WIGLE_LAT];
    $lon = (float)$data[WIGLE_LON];

    if (isset($networks[$mac])) {
        $networks[$mac]['last'] = $firstTime;

        if ($signal > $networks[$mac]['s']) {
            $networks[$mac]['s'] = $signal;
            $networks[$mac]['lat'] = $lat;
            $networks[$mac]['lon'] = $lon;
        }

        $networks[$mac]['signals'][] = array(
            't' => $firstTime,
            's' => $signal,
            'lat' => $lat,
            'lon' => $lon
        );

        $statistics->mergedWifi++;
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
    $row['lat'] = $lat;
    $row['lon'] = $lon;
    $row['signals'] = [
        [
            't' => $firstTime,
            's' => $signal,
            'lat' => $lat,
            'lon' => $lon
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

    $statistics->foundWifi++;
}

fclose($f);

if ($statistics->ignoredBad > 0) {
    echo "Found bad rows: {$statistics->ignoredBad}\n";
}
if ($statistics->ignoredNonWifi > 0) {
    echo "Ignored non wifi rows: {$statistics->ignoredNonWifi}\n";
}
if ($statistics->ignoredWifi > 0) {
    echo "Ignored wifi rows: {$statistics->ignoredWifi}\n";
}
if ($statistics->mergedWifi > 0) {
    echo "Merged wifi rows: {$statistics->mergedWifi}\n";
}
echo "Found wifi rows: {$statistics->foundWifi}\n";

if (file_exists($outputFile)) {
    unlink($outputFile);
}

if ($fileCompression) {
    file_put_contents($outputFile, gzencode(json_encode($networks)));
} else {
    file_put_contents($outputFile, json_encode($networks));
}
