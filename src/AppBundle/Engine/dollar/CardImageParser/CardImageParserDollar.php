<?php

namespace AwardWallet\Engine\dollar\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserDollar implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $textFront = $frontSide ? $frontSide->getText() : '';

        $backSide = $cardRecognitionResult->getBack();
        $textBack = $backSide ? $backSide->getText() : '';

        $textFull = $textFront . "\n" . $textBack;

        $textFullNoSpaces = str_replace(' ', '', $textFull);

        /**
         * @CardExample(accountId=4722259, cardUuid="f56e0ba3-ec1f-43de-a072-18eb280570f1", groupId="format1")
         * @CardExample(accountId=4039939, cardUuid="5c83270a-c926-4990-8568-e9eb048fb2e4", groupId="format1")
         * @CardExample(accountId=1602705, cardUuid="148c3076-6548-4b3f-99f2-0ec52dc4d64d", groupId="format1")
         * @CardExample(accountId=1205849, cardUuid="35aba488-ba59-43fd-95bd-2bb3728ecd2c", groupId="format1")
         */
        if (preg_match('/#(?<number>\d{10})(?:\b|\D)/', $textFullNoSpaces, $matches)
            || preg_match('/(?:\b|\D)(?<number>\d{10})(?:\b|\D)/', $textFullNoSpaces, $matches)
        ) {
            // #0141275690
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Express ID
        ];
    }
}
