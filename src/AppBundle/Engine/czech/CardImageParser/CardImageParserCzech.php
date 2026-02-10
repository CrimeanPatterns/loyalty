<?php

namespace AwardWallet\Engine\czech\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserCzech implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $textFront = $frontSide !== null ? $frontSide->getText() : '';

        $backSide = $cardRecognitionResult->getBack();
        $textBack = $backSide !== null ? $backSide->getText() : '';

        $textFull = $textFront . "\n\n" . $textBack;

        /**
         * @CardExample(accountId=5885471, cardUuid="547023b6-e9a3-4f31-a264-1db1ba9d0c91", groupId="format1")
         */
        if (preg_match('/number\s*(?<number>\d{8})(?:\b|\D)/i', $textFull, $matches)) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // OK Plus Number
        ];
    }
}
