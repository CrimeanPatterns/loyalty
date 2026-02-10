<?php

namespace AwardWallet\Engine\aeroplan\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAeroplan implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4332380

        $ccDetect = new CreditCardDetectionResult();

        $front = $cardRecognitionResult->getFront();
        $back = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/(?:Visa)/i',
            'text'  => '/(?:\bVISA\b|www\.tdcanadatrust\.com)/i',
        ];

        // FRONT

        if ($front) {
            $frontLogos = $front->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
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

        if ($back) {
            $backLogos = $back->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
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

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $front = $cardRecognitionResult->getFront();
        $textFront = $front ? $this->normalizeText($front->getText()) : '';

        $back = $cardRecognitionResult->getBack();
        $textBack = $back ? $this->normalizeText($back->getText()) : '';

        if (!$textFront && !$textBack) {
            return [];
        }

        if ($result = $this->parseFormat_1($textFront, $textBack)) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // number
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 25, 90, 50), new Rectangle(30, 75, 40, 20)])
            ->setBack($rects);
    }

    private function parseFormat_1($textFront = '', $textBack = '')
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFull = $textFront . "\n" . $textBack;

        /*
         * @CardExample(accountId=3831863, cardUuid="40d1a8e7-b318-4224-ac33-e13c36738fa6", groupId="format1")
         */
        $pattern = '/'
            . '^(?<number>\d{3}\s+\d{3}\s+\d{3})$' // Number
            . '/m';

        if (preg_match($pattern, $textFull, $matches)) {
            return [
                'Login' => str_replace([' ', "\n"], '', $matches['number']),
            ];
        }

        /*
         * @CardExample(accountId=3847004, cardUuid="a95a2df8-0877-40f3-b4fe-b629a3e64b05", groupId="format1")
         */
        $textConverted = preg_replace('/(\d{6})(\d{3}[ ]*\d{3}[ ]*\d{3})/', '$1 $2', $textFull); // 627421518 197 553 -> 627421 518 197 553

        /*
         * @CardExample(accountId=3735832, cardUuid="3ca66396-49e8-4006-ad66-e3e60afc9220", groupId="format1")
         */
        $textConverted = preg_replace('/((?:^|\D)\d+)[ ]+(\d{3}[ ]+\d{3}[ ]+\d{3}[ ]+\d{3})/', '$1$2', $textConverted); // 627 421 773 571 732 -> 627421 773 571 732

        /*
         * @CardExample(accountId=3888449, cardUuid="6179536c-2e37-435a-bd45-8746910df23b", groupId="format1")
         */
        $textConverted = preg_replace('/(.*?)AN(?:\s*CONTACT)?\s*CENTRE.*/is', '$1', $textConverted); // Aeroplan Centre    |    AEROPLAN CONTACT CENTRE
        $textConverted = preg_replace('/(.*?)PRIORITY\s*CONTACTS.*/is', '$1', $textConverted); // PRIORITY CONTACTS

        /*
         * @CardExample(accountId=3856676, cardUuid="20a9106d-9e29-4dbd-99f3-4a23b2f215f3", groupId="format1")
         * @CardExample(accountId=3884208, cardUuid="55f9de23-cd24-480f-875f-e759ad425015", groupId="format1")
         */
        $pattern = '/'
            . '\b(?<number>\d{3}[ ]+\d{3}[ ]+\d{3})(?:$|[ ]*\d\b|\D)' // Number
            . '/m';

        if (preg_match($pattern, $textConverted, $matches)) {
            return [
                'Login' => str_replace(' ', '', $matches['number']),
            ];
        }

        /*
         * @CardExample(accountId=3945767, cardUuid="fe3a908e-5b91-412d-9cfc-3eb733ed7849", groupId="format1")
         * @CardExample(accountId=3847461, cardUuid="0e007ff4-890b-4b6f-acbd-68450afa2d70", groupId="format1")
         */
        $pattern = '/'
            . '\b\d{6}(?<number>\d{9})\d{1,2}\b' // Number
            . '/';

        if (preg_match($pattern, $textFull, $matches)) {
            return [
                'Login' => $matches['number'],
            ];
        }

        /*
         * @CardExample(accountId=3408877, cardUuid="", groupId="format1")
         */
        $pattern = '/'
            . '^\d{6}$\s*^(?<number>\d{3}\s*\d{3}\s*\d{3})$' // Number
            . '/m';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => str_replace([' ', "\n"], '', $matches['number']),
            ];
        }

        return $properties;
    }

    private function normalizeText($string = '')
    {
        $string = str_replace(['.', ')', '('], '', $string);

        /*
         * @CardExample(accountId=3921920, cardUuid="88684095-ae26-472f-ada0-d887e0bf1690")
         */
        $string = preg_replace('/.*?(\d.*)/s', '$1', $string);

        /*
         * @CardExample(accountId=3849889, cardUuid="ea613210-fb64-470b-b076-af53d2839b7f")
         */
        $string = preg_replace('/([A-z])(\d)/', '$1 $2', $string); // TAR ALLANGE599 262 102 -> TAR ALLANGE 599 262 102
        $string = preg_replace('/(\d)([A-z])/', '$1 $2', $string); // 599 262 102TAR ALLANGE -> 599 262 102 TAR ALLANGE

        return $string;
    }
}
