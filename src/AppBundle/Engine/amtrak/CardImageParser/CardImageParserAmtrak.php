<?php

namespace AwardWallet\Engine\amtrak\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAmtrak implements CardImageParserInterface, CreditCardDetectorInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $front = $cardRecognitionResult->getFront();

        if (!$front) {
            return [];
        }

        $textFront = $front->getText();

        if (empty($properties = $this->parseFormat_1($textFront))) {
            if (empty($properties = $this->parseFormat_2($textFront))) {
                if (empty($properties = $this->parseFormat_3($textFront))) {
                    if (empty($properties = $this->parseFormat_4($textFront))) {
                        $properties = $this->parseOther($textFront);
                    }
                }
            }
        }

        return $properties;
    }

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

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1($textFront)
    {
        /**
         * @CardExample(accountId=93174, cardUuid="32284440-85c3-4112-9010-c45e58b94975")
         */
        if (preg_match("#\nGuest Rewards\nSELECT\n[^\n]+\n(\d{9,10})(?:\n|$)#", $textFront, $m)) {
            return [
                'Login' => $m[1],
            ];
        }

        return [];
    }

    protected function parseFormat_2($textFront)
    {
        /**
         * @CardExample(accountId=3865039, cardUuid="74a03298-5454-4ae3-b6e2-2037dee8d5cc")
         * @CardExample(accountId=818050, cardUuid="35deb635-449c-4a09-b5a8-6ca129ab160e")
         */
        if (preg_match("#\n(\d{9,10})\nMEMBER SINCE#", $textFront, $m)) {
            return [
                'Login' => $m[1],
            ];
        }

        return [];
    }

    protected function parseFormat_3($textFront)
    {
        /**
         * @CardExample(accountId=2654760, cardUuid="cb282591-539c-4444-893b-1e31c35d6796")
         */
        if (preg_match("#\nGuest Rewards\n[^\n]+\n(\d{9,10})\nSELECT#", $textFront, $m)) {
            return [
                'Login' => $m[1],
            ];
        }

        return [];
    }

    protected function parseFormat_4($textFront)
    {
        /**
         * @CardExample(accountId=4010132, cardUuid="0c5aa6ed-c182-4424-8a8f-98981aabdab7")
         */
        if (preg_match("#\nMember No\.\s+(\d{9,10})(?:\n|$)#", $textFront, $m)) {
            return [
                'Login' => $m[1],
            ];
        }

        return [];
    }

    protected function parseOther($textFront)
    {
        /**
         * @CardExample(accountId=3877296, cardUuid="f51c1b96-5ace-43c4-b090-43da7e615340")
         * @CardExample(accountId=3913923, cardUuid="4735efd1-5a62-4b54-b093-8013bd807578")
         * @CardExample(accountId=3832275, cardUuid="abd98d81-4d02-43c1-a14d-6bed35b2ef1d")
         */
        if (preg_match("#\n\#?(\d{9,10})(?:\n|$|-)#", $textFront, $m)) {
            return [
                'Login' => $m[1],
            ];
        }

        return [];
    }

    protected function detectCC_1(): bool
    {
        /**
         * @CardExample(accountId=3870845, cardUuid="54cd793d-c3a2-4d9a-8adb-227f6de5be66")
         * @CardExample(accountId=3748315, cardUuid="657311d3-ab0b-4beb-b8ee-958f2463949f")
         */
        if ($this->frontSide) {
            $textFront = $this->frontSide->getText();

            if (stripos($textFront, 'MasterCard') !== false
                    || stripos($textFront, "\nVISA\n") !== false
                    || stripos($textFront, "Valid Thru:") !== false
                    ) {
                return true;
            }
        }

        if ($this->backSide) {
            $textBack = $this->backSide->getText();

            if (stripos($textBack, 'Bank of America') !== false
                    || stripos($textBack, "bankofamerica.com") !== false
                    || stripos($textBack, "AUTHORIZED SIGNATURE") !== false
                    ) {
                return true;
            }
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 60)])
            ->setBack($rects);
    }
}
