<?php
/**
 * Created by PhpStorm.
 * User: rshakirov.
 */

namespace AwardWallet\Engine\frontierairlines\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserFrontierairlines implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $props = [];
        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }
        $textFront = $frontSide->getText();

        if (preg_match('/\b(?:EarlyReturns|EARLYRETVINS)\b/i', $textFront)) {
            $props = $this->parseFormat_1($textFront);
        }

        if (false !== stripos($textFront, 'AVAILABLE MILES') && preg_match('/Member\s*\#\s*:\s*/i', $textFront)) {
            $props = $this->parseFormat_2($textFront);
        }

        return $props;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Number
        ];
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetect = new CreditCardDetectionResult();

        $front = $cardRecognitionResult->getFront();
        $back = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/(?:MasterCard|Credit\s*card|Barclay\s*card|BPAY)/i',
            'text'  => '/(?:Barclays\s*Bank|license by MasterCard)/i',
        ];

        // FRONT

        if ($front instanceof ImageRecognitionResult) {
            $frontLogos = $front->getDOM()->findNodes('/img[@left > 50 or @top > 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetect);
            }

            $textFront = $front->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetect);
            }
        }

        // BACK

        if ($back instanceof ImageRecognitionResult) {
            $backLogos = $back->getDOM()->findNodes('/img[@left > 50 or @top > 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetect);
            }

            $textBack = $back->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetect);
            }
        }

        return $ccDetect;
    }

    /**
     * @CardExample(accountId=4171274, cardUuid="79ebf1f8-1a8b-4b5b-8a8e-35d016be0ea2", groupId="format1")
     * @CardExample(accountId=4115088, cardUuid="acf6b60d-51dc-462a-adaa-ac5b0b0f0747", groupId="format1")
     */
    private function parseFormat_1(string $text = ''): array
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }
        $res = [];

        if (empty($text)) {
            return [];
        }

        if (preg_match('/[a-z\.\s]+\n\b(\d{11})\b/i', $text, $m)) {
            $res['Login'] = $m[1];
        }

        return $res;
    }

    /**
     * @CardExample(accountId=3945063, cardUuid="9feba7d5-b607-4146-b4da-d0d4b75fa1bf", groupId="format2")
     */
    private function parseFormat_2(string $text = ''): array
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }
        $res = [];

        if (empty($text)) {
            return [];
        }

        if (preg_match('/Member\s*\#\s*:\s*\b(\d{11})\b/i', $text, $m)) {
            $res['Login'] = $m[1];
        }

        return $res;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 25, 100, 50)])
            ->setBack($rects);
    }
}
