<?php

namespace AwardWallet\Engine\airindia\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAirindia implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 3895589

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Visa)$/i',
            'text'  => '/(?:\bVISA\b|\bSBI Card|www\.sbicard\.com|branch of State Bank of India)/i',
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

        if (!$frontSide && !$backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=4652086, cardUuid="a95f46fd-5767-42e2-ace0-599add19d564", groupId="format1")
         * @CardExample(accountId=4646236, cardUuid="667940d2-1fb6-4913-b367-47d6090c2525", groupId="format1")
         * @CardExample(accountId=4596249, cardUuid="963615d9-8410-4ccb-b111-7d12c6d77bec", groupId="format1")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');
        $textFullConverted = str_replace([' ', '.', ',', ':'], '', $textFull);

        if (preg_match('/(?:\b|\D)(?<number>\d{9})(?:\b|\D)/', $textFullConverted, $matches)) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Membership No
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
