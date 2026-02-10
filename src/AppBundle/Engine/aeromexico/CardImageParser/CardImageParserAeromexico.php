<?php

namespace AwardWallet\Engine\aeromexico\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAeromexico implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '');

        if (preg_match("/\n(?:\d{6}\s*|[A-Z\s]+|\d{1,2}\s*|\D)?(?<number>\d{9})(?:\s*\d|\s*\D)?\n/u", $textFull, $matches)) {
            $result['Login'] = str_replace(" ", "", $matches['number']);
        } elseif (preg_match("/\n(?:\d{6}\s*|[A-Z\s]+|\d{2}\s*)?(?<number>\d{4}\s*\d{4}\s*\d)\n/u", $textFull, $matches)) {
            $result['Login'] = str_replace(" ", "", $matches['number']);
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
