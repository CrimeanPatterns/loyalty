<?php

namespace AwardWallet\Engine\amazongift\CardImageParser;

use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAmazongift implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

//    private function parseFormat_1()
//    {
//        if ($this->devMode)
//            echo ' --> ' . __FUNCTION__ . "\n";
//
//        $properties = [];
//
//        //
//
//        return $properties;
//    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Email
            'Login2', // Region
        ];
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4061352,4068347,3997497,3871331

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/(?:Visa|American\s*Express)/i',
            'text'  => '/(?:\bVISA\b|\bAMEX\b|americanexpress[ ]*\.[ ]*com|chase[ ]*\.[ ]*com|\bCHASE(?:o|c)?\b|Synchrony\s*Bank|syncbank[ ]*\.[ ]*com)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($backSide) {
            $backLogos = $backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBackBottom = $backSide->getDOM(2)->getTextRectangle(0, 25, 40, 10); // deviation: 0-4

            if (preg_match('/^\d{4} \d{4} \d{4} \d{4}$/m', $textBackBottom)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront([new Rectangle(5, 50, 65, 40)])
            ->setBack([new Rectangle(5, 30, 65, 30), new Rectangle(5, 60, 55, 30)]);
    }
}
