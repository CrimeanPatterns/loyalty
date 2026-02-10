<?php

namespace AwardWallet\Engine\airmilesca\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAirmilesca implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=3909867, cardUuid="ce07b4ef-8cde-4872-9e2a-53d6f5a5bd9e")
         * @CardExample(accountId=3900063, cardUuid="261a3b07-f228-4d7d-8560-b11683a71464")
         * @CardExample(accountId=3912110, cardUuid="96efe5be-6bc4-4c8b-b797-78c9b61d8fd0")
         * @CardExample(accountId=3906954, cardUuid="eae589a7-f199-4801-ad63-67a8d4fc21bd")
         */
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

        $patterns = [
            'number' => '/(?:\b|\D)(\d{11})(?:\b|\D)/',
        ];

        // Number (back)

        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        $in = ['O', 'o'];
        $out = ['0', '0'];
        $textBackConverted = str_replace($in, $out, $textBack);

        $textBackConverted = str_replace(['.', ' ', '"'], '', $textBackConverted);

        if (preg_match($patterns['number'], $textBackConverted, $matches)) {
            $properties['Login'] = $matches[1];

            return $properties;
        }

        // Number (front)

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        $in = ['O', 'o', 'b'];
        $out = ['0', '0', '6'];
        $textFrontConverted = str_replace($in, $out, $textFront);

        $textFrontConverted = str_replace(['.', ' '], '', $textFrontConverted);

        if (preg_match($patterns['number'], $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }
}
