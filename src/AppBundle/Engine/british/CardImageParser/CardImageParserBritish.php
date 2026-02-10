<?php

namespace AwardWallet\Engine\british\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserBritish implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    /** @var ImageRecognitionResult */
    protected $frontSide;

    /** @var ImageRecognitionResult */
    protected $backSide;

    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $this->ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $this->ccDetectionResult;
        }

        if ($this->detectCC_1()) {
            $this->hideCCNumber($this->ccDetectionResult);
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if ($this->frontSide) {
            $textFront = $this->frontSide->getText();
            $textBack = '';

            if ($this->backSide) {
                $textBack = $this->backSide->getText();
            }

            if (preg_match('/\b(\d{8})\b/', $textFront, $m) || preg_match('/\b(\d{8})\b/', $textBack, $m)) {
                $properties['Login'] = $m[1];
            } elseif (preg_match('/\n([\d\s]+)\n/', $textFront, $m)) {
                $num = preg_replace('/\D+/', '', $m[1]);

                if (strlen($num) === 8) {
                    $properties['Login'] = $num;
                }
            }
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 3933681,3928250,3655349,3728677,3733573,3857599,3867589,3859919,4049229

        /**
         * @CardExample(accountId=3933681, cardUuid="3460050a-c7b0-4714-89f3-7727ceea8ec4", groupId="formatCC1")
         * @CardExample(accountId=3928250, cardUuid="727d87bb-4a35-4274-a5c2-6955c902b96c", groupId="formatCC1")
         */
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'image' => '/(?:VISA|Chase|American Expres|Cartoes de Credito|mastercard)/i',
            'text'  => '/(?:\bVISA\b|chase.com|\bCHASEo?\b|AMERICAN\s*EXPRES|\bAMEX\b|Cardmember\s*Sign|mastercard|1-302-594-8200)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                return true;
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                return true;
            }

            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                return true;
            }
        }

        return false;
    }

    protected function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(30, 0, 40, 25), new Rectangle(0, 30, 100, 40), new Rectangle(30, 75, 40, 25)])
            ->setBack($rects);
    }
}
