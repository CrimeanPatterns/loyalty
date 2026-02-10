<?php

namespace AwardWallet\Engine\chinasouthern\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserChinasouthern implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4498126,4783975

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Union\s*Pay|China\s*Guangfa\s*Bank|MasterCard)/i',
            'text'  => '/(?:Union\s*Pay|广发银行|\bCGBCHINA\.COM|MasterCard|GOLD\s*Credit\s*Card)/iu',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $frontSide->getText();

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($backSide) {
            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $backSide->getText();

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $textFront = $frontSide ? $frontSide->getText() : '';

        $backSide = $cardRecognitionResult->getBack();
        $textBack = $backSide ? $backSide->getText() : '';

        $textFull = $textFront . "\n" . $textBack;

        /**
         * @CardExample(accountId=4363729, cardUuid="b6ae880e-2d88-48f0-ab30-3974369bae81", groupId="format1")
         */
        $textFullConverted = preg_replace('/(\S)(ISU)/', "$1\n$2", $textFull);

        /**
         * @CardExample(accountId=4422178, cardUuid="c8816c89-bc0d-4785-a109-5035116e2319", groupId="format1")
         */
        $textFullConverted = str_replace(['I'], ['1'], $textFullConverted);

        /**
         * @CardExample(accountId=4422178, cardUuid="c8816c89-bc0d-4785-a109-5035116e2319", groupId="format1")
         * @CardExample(accountId=3997769, cardUuid="fe6e3996-79ed-4838-98d1-78a2005a9f49", groupId="format1")
         */
        $textFullConverted = preg_replace('/([^A-z]|\b)[Cc][27](\d{3})/', '$1CZ$2', $textFullConverted);

        /**
         * @CardExample(accountId=4023907, cardUuid="65a4de98-7e8b-40be-8b58-b4fca5ce9665", groupId="format1")
         */
        $textFullConverted = preg_replace('/[ ]*\d+[%]+[ ]*/', "\n", $textFullConverted);

        // FULL (with spaces)

        /**
         * @CardExample(accountId=4997663, cardUuid="317f6d38-18e5-4eea-b4d4-efb24300b193", groupId="format1")
         * @CardExample(accountId=4507775, cardUuid="43bf41b3-61c7-443d-8891-aa07b7767c23", groupId="format1")
         * @CardExample(accountId=2513200, cardUuid="67d39672-4b81-4a60-a22d-c5f290da7fff", groupId="format1")
         * @CardExample(accountId=3958506, cardUuid="0a986e51-9251-4933-ae9a-adc5dfa47a09", groupId="format1")
         * @CardExample(accountId=4099623, cardUuid="30338e4a-9641-46c7-96ef-c7369a920158", groupId="format1")
         */
        if (preg_match('/(?:^|\n|[Nn][Oo][: ]*|\b[Cc][Zz27]*|\b[Cc]*[Zz27])(?<number>\d{3} ?\d{3} ?\d{3} ?\d{3}|\d{4} ?\d{4} ?\d{4})(?: ?[-[:alpha:]]|\n|$)/u', $textFullConverted, $matches)) {
            // CZ 214 126 587 981    |    CZ 2141 2658 7981    |    CZ214126587981    |    No: 214126587981
            $result['Login'] = str_replace(' ', '', $matches['number']);
        }

        // FULL (without spaces)

        $textFullNoSpaces = str_replace(' ', '', $textFullConverted);

        if (empty($result['Login'])) {
            // Your Sky Pearl Number: 214126587981    |    Base Card 214126587981    |    CZ2 14126587981
            if (preg_match("/(?:^|\n|:|[-[:alpha:]]|[Cc][Zz27]*|[Cc]*[Zz27])(?<number>\d{12})(?:[-[:alpha:]]|\n|$)/u", $textFullNoSpaces, $matches)) {
                $result['Login'] = $matches['number'];
            }
        }

        if (empty($result['Login'])) {
            /**
             * @CardExample(accountId=4044844, cardUuid="7994159d-aca0-4c08-8280-bb694201e848", groupId="format1")
             */
            if (preg_match("/(?:[Cc][Zz]*|[Cc]*[Zz])(?<number1>\d{3,})\n+(?<number2>\d{3,})/", $textFullNoSpaces, $matches)
                && preg_match('/^\d{12}$/', $matches['number1'] . $matches['number2'])
                || preg_match("/(?<number2>\d{3,})\n+(?:[Cc][Zz]*|[Cc]*[Zz])(?<number1>\d{3,})/", $textFullNoSpaces, $matches)
                && preg_match('/^\d{12}$/', $matches['number1'] . $matches['number2'])
            ) {
                $result['Login'] = $matches['number1'] . $matches['number2'];
            }
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member No
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
