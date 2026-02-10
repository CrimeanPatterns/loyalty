<?php

namespace AwardWallet\Engine\aviancataca\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAviancataca implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4777022

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Visa|PLUS)$/i',
            'text'  => '/(?:\bVISA\b|Credit Card Agree)/i',
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
        $backSide = $cardRecognitionResult->getBack();

        $textFront = $frontSide ? $frontSide->getText() : ''; // deviation: 1-5 or default
        $textBack = $backSide ? $backSide->getText() : '';
        $textFull = $textFront . "\n" . $textBack;

        /**
         * @CardExample(accountId=3244495, cardUuid="cf9eb7bb-8b0e-46cc-8678-6a26319e9f69")
         * @CardExample(accountId=3714803, cardUuid="dba4bb46-04f2-4a2f-aed1-39a04cc52de5")
         * @CardExample(accountId=3797124, cardUuid="aaa0d791-bbc9-48ab-80bd-c3522145cb30")
         * @CardExample(accountId=3963402, cardUuid="a5867498-15a3-4270-bada-3f5d7daaef95")
         * @CardExample(accountId=3835186, cardUuid="066f433a-21a5-4d6c-845c-ae991f47194e")
         * @CardExample(accountId=4161138, cardUuid="75b7f8ea-55d1-4d47-9ccd-cfe97c41705a")
         */
        $textFullConverted = str_replace(['.', ' '], '', $textFull);
        $textFullConverted = str_replace(['o', 'l', 'b'], ['0', '1', '6'], $textFullConverted);

        if (preg_match('/(?:\b|\D)(?<number>\d{11})(?:\b|\D)/', $textFullConverted, $m)) {
            $result['Login'] = $m['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // FF number
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $rect1 = new Rectangle(5, 70, 70, 25);
        $ccDetectionResult
            ->setFront([$rect1])
            ->setBack([new Rectangle(25, 35, 50, 20), $rect1]);
    }
}
