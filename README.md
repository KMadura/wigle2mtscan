# wigle2mtscan
This is a [WiGLE](https://github.com/wiglenet/wigle-wifi-wardriving) CSV database file converter which outputs [MTscan](https://github.com/kkonradpl/mtscan) compatible files, both in uncompressed or compressed form. You can use this tool to convert your phone generated scans into MTscan format, however there are some small drawbacks I'll explain further in this document.

Normally for day to day scanning I'm using custom modified raspberry pi with PoE output, mikrotik groove device with an antenna and power bank to supply power for this setup. This is a neat and tidy solution, however there are moments I can't take this equipment with me.

Occasionally I'm on a various trips around europe, and taking modified 5GHz equipment on a plane could create some problems with security staff which could view it as something dangerous or hazardous, and for sure you wouldn't risk being falsely accused of something illegal. Obviously we know WiFi scanning itself without actually hacking into WiFi networks is absolutely legal and common (for example Google Streetview cars do that).

### Differences between WiGLE CSV and MTscan

MTscan based scanner fetches multiple parameters ignored by WiGLE. As you can see in the code WiGLE only scans for MAC address, SSID, Channel, Signal and stores additional information about encryption, time and location. Meanwhile MTscan also provides information about signal bandwidth, non-standard channels, RadioName, routeros version, used protocols, etc.

If you want to mix scans done by MTscan and WiGLE please modify your scripts so WiGLE data rows do not overwrite your MTscan entries. Mixing is also an option if you are sure WiGLE made scan is done in a distant place (for example another country away from your local scans).

### Usage

* `-i` required - Input file, for example wigle.csv
* `-o` required - Output file, for example wigle.mtscan or wigle.mtscan.gz
* `-df` optional - Date from in ISO-8601 like format, for example YYYY-MM-DD
* `-dt` optional - Date to in ISO-8601 like format
* `-b` optional - Band type, 2 or 2.3-2.5 for 2.4GHz and 5 or 5.0-5.9 for 5GHz
* `-h` Info page

Example: `./wigle2mtscan.phar -i wigle.csv -o wigle.mtscan.gz -df 2022-10-25 -b 5`

### Compilation

To compile `wigle2mtscan.phar` just run `./pharCompile.php` from a console. Resulting script should be compatible with PHP 7.0.