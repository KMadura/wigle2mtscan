<?php

declare(strict_types=1);

namespace App;

class options
{
    const VALUE_NONE = 0;
    const VALUE_REQUIRED = 1;
    const VALUE_OPTIONAL = 2;

    private $optionsList = [];
    private $keyMapping = [];

    private $reservedKeys = [];
    private $unassignedValues = [];

    private $appDescription = '';

    public function __construct()
    {
    }

    public function setOption(
        string $shortName,
        string $longName,
        string $description,
        int $requestedValue = self::VALUE_NONE
    ) {
        $newKey = sizeof($this->optionsList);

        $this->keyMapping[$shortName] = $newKey;
        if (!empty($longName)) {
            $this->keyMapping[$longName] = $newKey;
        }

        $this->optionsList[$newKey] = (object)[
            'shortName' => $shortName,
            'longName' => $longName,
            'description' => $description,
            'exist' => false,
            'value' => null,
            'requestedValue' => $requestedValue
        ];
    }

    public function isOption(string $key): bool
    {
        if (!isset($this->keyMapping[$key])) {
            return false;
        }
        if (!isset($this->optionsList[$this->keyMapping[$key]])) {
            return false;
        }

        return $this->optionsList[$this->keyMapping[$key]]->exist ?? false;
    }

    public function setDescription(string $string)
    {
        $this->appDescription = $string;
    }

    public function getOption(string $key)
    {
        if (!isset($this->keyMapping[$key])) {
            return null;
        }
        return $this->optionsList[$this->keyMapping[$key]] ?? null;
    }

    public function getOptions(): array
    {
        return $this->optionsList;
    }

    public function getExistingOptions(): array
    {
        $temporaryArray = [];

        foreach ($this->optionsList as $option) {
            if ($option->exist) {
                $temporaryArray[] = $option;
            }
        }

        return $temporaryArray;
    }

    public function getValues(): array
    {
        return $this->unassignedValues;
    }

    public function getValueString(): string
    {
        return implode(' ', $this->unassignedValues);
    }

    public function printHelp()
    {
        output::stdOut($this->appDescription);
        output::stdOut("\nUsage:");
        $textBufferArray = [];
        $maxLineWidth = 0;
        foreach ($this->optionsList as $option) {
            if (isset($option->virtual)) {
                $textBufferArray[] = [null, $option->virtual];
                continue;
            }

            $nextParam = $option->requestedValue === self::VALUE_REQUIRED ? "(param) " : '';
            $textBufferLine = "  -{$option->shortName}, --{$option->longName} $nextParam";

            $currentLineWidth = strlen($textBufferLine);
            if ($maxLineWidth < $currentLineWidth) {
                $maxLineWidth = $currentLineWidth;
            }

            $textBufferArray[] = [$textBufferLine, $option->description];
        }

        foreach ($textBufferArray as $textBufferRow) {
            if ($textBufferRow[0] === null) {
                output::stdOut(str_pad('', $maxLineWidth, ' ') . "  " . $textBufferRow[1]);
            } else {
                output::stdOut(str_pad($textBufferRow[0], $maxLineWidth, ' ') . ": " . $textBufferRow[1]);
            }
        }
    }

    public function parse(array $argv)
    {
        unset($argv[0]);
        $skip = false;

        // Looping through options
        foreach ($argv as $key => $arg) {
            if ($skip) {
                $this->reservedKeys[$key] = true;
                $skip = false;
                continue;
            } else {
                $this->reservedKeys[$key] = false;
            }

            // Found a parameter
            if (preg_match('/^-+(.+)/', $arg, $matches)) {
                $suppliedArgument = $matches[1];
                $this->reservedKeys[$key] = true;

                if (!isset($this->keyMapping[$suppliedArgument])) {
                    // This argument is not registered, continue
                    continue;
                }

                $selectedOption = $this->optionsList[$this->keyMapping[$suppliedArgument]];
                $selectedOption->exist = true;
                if ($selectedOption->requestedValue === self::VALUE_REQUIRED && isset($argv[($key + 1)])) {
                    $selectedOption->value = $argv[($key + 1)];
                    $skip = true;
                } elseif ($selectedOption->requestedValue === self::VALUE_OPTIONAL && isset($argv[($key + 1)])) {
                    if (!preg_match('/^-/', $argv[($key + 1)])) {
                        $selectedOption->value = $argv[($key + 1)];
                        $skip = true;
                    }
                }
            }
        }

        // Selecting regular values
        foreach ($argv as $key => $arg) {
            if ($this->reservedKeys[$key]) {
                continue;
            }

            $this->unassignedValues[] = $arg;
        }
    }

    public function addAnotherLine(string $description)
    {
        // This is only used for help menu display purposes
        $randomKey = crc32((string)rand());

        $this->optionsList[$randomKey] = (object)[
            'virtual' => $description
        ];
    }
}