<?php

namespace AwardWallet\Engine\aerolineas\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAerolineas implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4244587,4283380,4891707

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Visa|MasterCard|Banco\s*Hipotecario)/i',
            'text'  => '/(?:\bVISA\b|MasterCard)/i',
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
         * @CardExample(accountId=4068804, cardUuid="e413abf7-1b06-4e6a-9e09-41540e1dd23a", groupId="format1replaced")
         * @CardExample(accountId=2822591, cardUuid="89b55108-bcdd-4dbc-a479-10137ded4aa2", groupId="format1replaced")
         * @CardExample(accountId=4390714, cardUuid="f8700a81-784c-42f6-b5c3-02e6bcea8898", groupId="format1replaced")
         */
        $textFullConverted = str_replace(['!', 'O', 'l', 'b', 'G'], ['', '0', '1', '6', '6'], $textFull);

        /**
         * @CardExample(accountId=4969676, cardUuid="b064311e-ec36-4d31-9b5f-c53e129dbec7", groupId="format1expiration")
         */
        // 31/1219999
        $textFullConverted = preg_replace('/((?:\b|\D)\d{2}\/\d{2}).{0,1}(9999(?:\b|\D))/', '$1/$2', $textFullConverted);
        // 3112 9999
        $textFullConverted = preg_replace('/((?:\b|\D)\d{2}[\/ ]{0,1}\d{2}).{0,1}(9999(?:\b|\D))/', '$1/$2', $textFullConverted);
        // 12/99    |    12/9999
        $textFullConverted = preg_replace('/(\b|\D)(\d{2}\/\d{2,4})(\b|\D)/', "$1\n$2\n$3", $textFullConverted);

        $textFullNoSpaces = str_replace(' ', '', $textFullConverted);

        /**
         * @CardExample(accountId=4501196, cardUuid="99748fc0-b868-4261-bf95-e2c463e3a9e7", groupId="format1count8")
         * @CardExample(accountId=4492850, cardUuid="5dc84041-cc18-4d87-b370-c4627c49edb8", groupId="format1count8")
         * @CardExample(accountId=4826958, cardUuid="08f3cc2d-a536-494c-8809-2371ca0838da", groupId="format1count8")
         * @CardExample(accountId=4079661, cardUuid="415420d5-b509-4e8f-b060-070c868a4808", groupId="format1count8")
         * @CardExample(accountId=2676681, cardUuid="2f67d027-6df8-44a7-af54-ac198c437f18", groupId="format1count7")
         */
        if (preg_match('/(?:\b|\D)(?<number>\d{8})(?:\b|\D)/', $textFullNoSpaces, $matches)
            || preg_match('/(?:\b|\D)(?<number>\d{7})(?:\b|\D)/', $textFullNoSpaces, $matches)
        ) {
            // 52740715    |    2390741
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Frequent Flyer Number
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
