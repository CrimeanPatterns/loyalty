<?php

namespace AwardWallet\Engine\flybuys\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserFlybuys implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    protected $frontSide;
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
            $this->hideCCNumber_1($this->ccDetectionResult);
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        if (!$this->frontSide) {
            $this->frontSide = $cardRecognitionResult->getFront();
        }

        if (!$this->backSide) {
            $this->backSide = $cardRecognitionResult->getBack();
        }

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=3897895, cardUuid="916bac30-4720-4d7f-9d16-7d1371aca2c6")
         * @CardExample(accountId=3462378, cardUuid="5f7cb2ce-9b30-48a4-954d-0ed1f3829590")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default
        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        $textFull = $textBack . "\n" . $textFront;

        $in = ['O', 'o'];
        $out = '0';
        $textFullConverted = str_replace($in, $out, $textFull);

        $textFullConverted = str_replace(['.', ' '], '', $textFullConverted);

        $textFullConverted = preg_replace('/Service[ ]*Centre[ ]*\d+/i', '', $textFullConverted); // remove phone numbers

        if (preg_match('/(?:\b|\D)(\d{16})(?:\b|\D)/', $textFullConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }

    protected function detectCC_1(): bool
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        /**
         * @CardExample(accountId=3944554, cardUuid="5dbee320-674d-4451-a1f0-ca5a8c9d24da")
         */
        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        if (stripos($textBack, 'MasterCard') !== false) {
            return true;
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 40)])
            ->setBack($rects);
    }
}
