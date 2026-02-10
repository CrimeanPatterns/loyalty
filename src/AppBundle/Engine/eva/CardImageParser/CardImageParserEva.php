<?php

namespace AwardWallet\Engine\eva\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserEva implements CardImageParserInterface
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
         * @CardExample(accountId=4484072, cardUuid="d50fec82-6717-4dc1-ba7e-4cabe6118cde", groupId="format1")
         * @CardExample(accountId=3531222, cardUuid="026551ee-10eb-4fb6-ba89-db463201f9f6", groupId="format1")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        if (preg_match('/(?:\b|\D)(?<number>\d{10})(?:\b|\D)/', $textFull, $matches)) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Infinity MileageLands Number
        ];
    }
}
