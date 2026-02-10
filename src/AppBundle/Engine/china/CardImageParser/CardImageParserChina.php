<?php

namespace AwardWallet\Engine\china\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserChina implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            //            'image' => '//i',
            //            'text' => '//i',
        ];

        // FRONT

        if ($frontSide) {
//            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
//            $frontLogoValues = array_values( array_filter($frontLogos) );
//            if ( !empty($frontLogoValues[0]) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
//
//            $textFront = $frontSide->getText();
//            if ( preg_match($patterns['text'], $textFront) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
        }

        // BACK

        if ($backSide) {
//            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
//            $backLogoValues = array_values( array_filter($backLogos) );
//            if ( !empty($backLogoValues[0]) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
//
//            $textBack = $backSide->getText();
//            if ( preg_match($patterns['text'], $textBack) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        $textFront = $frontSide ? $frontSide->getText() : '';

        /**
         * @CardExample(accountId=5263699, cardUuid="9d6a3eb6-61e3-4b33-9cd0-52cac49cbe71", groupId="format1")
         * @CardExample(accountId=4253704, cardUuid="1cc6e9b1-fdaf-4537-a04e-20c0ce8b3b20", groupId="format1")
         */
        $pattern = '/'
            . '^\s*(?<n1>[A-Za-z]{2})(?<n2>(?: ?[\dSbo]){7})\s*$' // Number
            . '/m';

        if (preg_match($pattern, $textFront, $matches)
        ) {
            $matches['n2'] = str_replace(['S', 'b', 'o'], ['5', '6', '0'], $matches['n2']);
            $result['Login'] = str_replace(' ', '', strtoupper($matches['n1']) . $matches['n2']);
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
