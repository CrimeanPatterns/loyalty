<?php

namespace AwardWallet\Engine\chinaeastern\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserChinaeastern implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $textFront = $frontSide ? $frontSide->getText() : '';

        $backSide = $cardRecognitionResult->getBack();
        $textBack = $backSide ? $backSide->getText() : '';

        $textFull = $textFront . "\n" . $textBack;

        $textFullConverted = str_replace(['.', '$'], ['', '1'], $textFull);

        $textFullNoSpaces = str_replace(' ', '', $textFullConverted);

        /**
         * @CardExample(accountId=4771333, cardUuid="51854d0e-ae26-4fb7-a529-fbd70ba66020", groupId="format1")
         * @CardExample(accountId=4638499, cardUuid="804cb0c1-77d3-4032-95c7-6938606eb029", groupId="format1")
         * @CardExample(accountId=5022546, cardUuid="a2e244a3-d1e9-4d6c-a391-e6f08614539a", groupId="format1")
         * @CardExample(accountId=4870062, cardUuid="7a26bc73-579d-4331-a5ce-5f6fcf71472b", groupId="format1")
         * @CardExample(accountId=4826885, cardUuid="2a46668c-31b7-46a2-87c0-67054487ccd2", groupId="format1")
         * @CardExample(accountId=4105891, cardUuid="de3002b2-5797-436d-986e-80c6e658d65a", groupId="format1")
         * @CardExample(accountId=4300068, cardUuid="18e53569-adbe-48ee-a613-063ad6ed30d2", groupId="format1")
         * @CardExample(accountId=4280984, cardUuid="86e9accf-6888-4f1e-978e-1224ccbc3dd2", groupId="format1")
         */
        if (preg_match('/[Nn][Oo][:]*(?<number>\d{12})(?:\b|\D)/', $textFullNoSpaces, $matches)
            || preg_match('/(?:\b|\D)(?<number>\d{12})(?:\b|\D)/', $textFullNoSpaces, $matches)
        ) {
            // NO. 643012579461    |    643012579461
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // member card number
        ];
    }
}
