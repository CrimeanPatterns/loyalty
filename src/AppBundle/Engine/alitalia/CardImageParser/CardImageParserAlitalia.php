<?php

namespace AwardWallet\Engine\alitalia\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAlitalia implements CardImageParserInterface
{
    private $devMode = 0;

    private $frontSide;
    private $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        /**
         * @CardExample(accountId=4040197, cardUuid="aab635fb-0d23-4eda-a688-ebacf4bf8363", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // MilleMiglia code or Username
        ];
    }

    private function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        /**
         * @CardExample(accountId=4057495, cardUuid="8ba3da16-bc31-40cb-bf8d-0d33afd9b313", groupId="format1")
         * @CardExample(accountId=4067958, cardUuid="1c71a1b7-fa8d-47a5-a458-1ae41362b836", groupId="format1")
         * @CardExample(accountId=3977312, cardUuid="e65820a9-84dd-4dc1-832d-39bdbb89e2e6", groupId="format1")
         * @CardExample(accountId=3913522, cardUuid="bd8388f9-b0d5-44d9-ae2b-11ddf74bbf0f", groupId="format1")
         */
        $in = ['O', 'o', 'D', 'l', '나', 'S', 'b', 'T', 'B'];
        $out = ['0', '0', '0', '1', '4', '5', '6', '7', '8'];
        $textFullConverted = str_replace($in, $out, $textFront);

        $textFullConverted = str_replace(['.', ':', ' '], '', $textFullConverted);

        if (preg_match('/(?:\b|\D)(?<number>\d{8}|\d{10})(?:\b|\D)/', $textFullConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        } elseif (preg_match('/\n(?<number>\d{7})\n/', $textFullConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }
}
