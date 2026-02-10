<?php

namespace AwardWallet\Engine\airnewzealand\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAirnewzealand implements CardImageParserInterface, CreditCardDetectorInterface
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
            'image' => '/MasterCard/i',
            'text'  => '/(?:issued by Bank of|MasterCard)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top > 50]/@alt', null, $patterns['image']);
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
            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top > 50]/@alt', null, $patterns['image']);
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

        $textFront = $frontSide ? $frontSide->getText() : '';
        $textBack = $backSide ? $backSide->getText() : '';

        $convertFrom = ['O'];
        $convertTo = ['0'];

        $textFrontNoSpaces = str_replace($convertFrom, $convertTo, str_replace(' ', '', $textFront));
        $textFull = $textFront . "\n" . $textBack;
        $textFullNoSpaces = str_replace($convertFrom, $convertTo, str_replace(' ', '', $textFull));

        /**
         * @CardExample(accountId=4738428 , cardUuid="7f657991-4cd0-43ff-af56-8b7c6a70c261", groupId="format1")
         * @CardExample(accountId=4488101 , cardUuid="adafdc81-e95a-4684-85bf-2ec860252e4c", groupId="format1")
         */
        $pattern = '/(?:^|\s+)(?:N(?:O|0)\.)?'
            . '(?<number>\d{6,8})' // Number
            . '(?:\s+|$)/i';

        if (preg_match($pattern, $textFront, $matches)
                || preg_match($pattern, $textFrontNoSpaces, $matches)
                || preg_match($pattern, $textFullNoSpaces, $matches)
        ) {
            $result['Login'] = $matches['number'];
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
            ->setFront($rects = [new Rectangle(5, 30, 90, 55)])
            ->setBack($rects);
    }
}
