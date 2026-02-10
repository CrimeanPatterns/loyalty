<?php

namespace AwardWallet\Engine\aegean\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAegean implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number (Type 1)
        /**
         * @CardExample(accountId=3931941, cardUuid="435654df-cf2d-43d0-a039-e8a553c9dc6d")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText(5) : ''; // deviation: 2-9

        if (preg_match('/\b(\d{9})\b/', $textFront, $matches)) {
            $properties['Login'] = $matches[1];

            return $properties;
        }

        // Number (Type 2)
        /**
         * @CardExample(accountId=3871305, cardUuid="20ae2a2b-75dc-40db-8d18-caffa246c011")
         */
        if (preg_match('/\b(\d{3} \d{3} \d{3})\b/', $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[1]);

            return $properties;
        }

        // Number (Type 3)
        /**
         * @CardExample(accountId=3699431, cardUuid="951b833b-ee6e-46f0-88ee-1298876d426f")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        $in = ['O', 'o', 'U', 'l', 'b'];
        $out = ['0', '0', '0', '1', '6'];
        $textFrontConverted = str_replace($in, $out, $textFront);

        if (preg_match('/\b([\d ]{9,})\b/', $textFrontConverted, $matches)) {
            $matches[1] = preg_replace("#\s+#", '', $matches[1]);

            if (strlen($matches[1]) == 9) {
                $properties['Login'] = $matches[1];

                return $properties;
            }
        }

        return $properties;
    }
}
