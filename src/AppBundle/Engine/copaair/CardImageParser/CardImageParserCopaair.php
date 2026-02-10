<?php

namespace AwardWallet\Engine\copaair\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserCopaair implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        if (!$frontSide && !$backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=4596453, cardUuid="")
         * @CardExample(accountId=4614605, cardUuid="")
         * @CardExample(accountId=4007968, cardUuid="")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        if (preg_match('/\s*(\d{8,9})\s*/', $textFull, $matches)
            || preg_match('/\s*(\d{3}\s\d{3}\s\d{3})\s*/', $textFull, $matches)
            || preg_match('/\n([A-Z]{2}\d{6})\nVálido/', $textFull, $matches)
        ) {
            $result['Login'] = preg_replace('/\s+/', '', $matches[1]);
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member ID
        ];
    }
}
