<?php

namespace AwardWallet\Engine\airchina\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAirchina implements CardImageParserInterface
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
         * @CardExample(accountId=4038859, cardUuid="4b54fe5b-c84e-4cbb-a096-7a17e8f275bf", groupId="format1")
         * @CardExample(accountId=4131722, cardUuid="b25c0b8d-18b6-462d-9c62-831c21bba32c", groupId="format1")
         * @CardExample(accountId=4826878, cardUuid="737a6c28-9a12-4683-b11d-399a2e9ca35e", groupId="format1")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        $textFull = str_replace(['o', 'L', 'S', 'b'], ['0', '1', '5', '6'], $textFull);

        if (preg_match('/N?O?\s*?\n(?<number>(?:\d{12}|[\d{4}\s\-]+))\.?\nN?O?/', $textFull, $matches)) {
            // NO \n 012059071134    |    00539887-3013    |    012059071134 \n NO
            $result['Login'] = str_replace([' ', '-'], ['', ''], $matches['number']);
        } elseif (preg_match('/(?:ALUANGE|CHiNA|CARD|Card|ALUANCE|CA)\s*\n(?<number>(?:[\d\s]+|\d{12}))\s/', $textFull, $matches)) {
            // word \n 012059071134    |   word \n 0120 5907 1134
            $result['Login'] = str_replace([' '], [''], $matches['number']);
        } elseif (preg_match('/\nN?O?1?\s?(?<number>(?:[\d\s]+|\d{12}))\n/', $textFull, $matches)) {
            //NO 012059071134  |  NO 0120 5907 1134 |  NO1 012059071134 |  NO1 0120 5907 1134
            $result['Login'] = str_replace([' '], [''], $matches['number']);
        }

        if (!empty($result['Login'])) {
            $result['Login'] = $this->re('/^(\d{12})$/', $result['Login']);
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Card No.
        ];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
