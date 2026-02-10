<?php

namespace AwardWallet\Engine\asia\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserAsia implements CardImageParserInterface, CreditCardDetectorInterface
{
    /** @var ImageRecognitionResult */
    protected $frontSide;
    /** @var ImageRecognitionResult */
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        if ($result = $this->parseFormat_1()) {
            return $result;
        }

        // Other Formats
        if ($result = $this->parseFormat_999()) {
            return $result;
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Membership # or Username
        ];
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        if ($this->detectCC_1()) {
            $this->hideCCNumber_1($ccDetectionResult);

            return $ccDetectionResult;
        }

        return $ccDetectionResult;
    }

    protected function parseFormat_1()
    {
        $properties = [];

        /**
         * @CardExample(accountId=2586071, cardUuid="0e426577-624d-4699-baa9-0d6c3a85a1d8", groupId="Type1")
         * @CardExample(accountId=3903283, cardUuid="c46bc73f-4975-4e5d-906a-d0839d663ee7", groupId="Type1")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText(5) : ''; // deviation: 2-9

        if (preg_match('/(?:\n|^)\s*(\d{3}[ ]*\d{3}[ ]*\d{4})\s*(?:\n|$)/', $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[1]);

            return $properties;
        }

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // if card rotated

        if (preg_match('/(?:\n|^)\s*(\d{3}[ ]*\d{3}[ ]*\d{4})\s*(?:\n|$)/', $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[1]);

            return $properties;
        }

        /**
         * @CardExample(accountId=2661592, cardUuid="b6369b7c-c582-4427-a3c9-98f388d2fea5", groupId="Type2")
         */
        $textBack = $this->backSide ? $this->backSide->getText(5) : ''; // deviation: 2-9

        if (preg_match('/(?:\n|^)\s*(\d{3}[ ]*\d{3}[ ]*\d{4})\s*(?:\n|$)/', $textBack, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[1]);

            return $properties;
        }

        return $properties;
    }

    protected function parseFormat_999()
    {
        /**
         * @CardExample(accountId=4647728, cardUuid="f0bbdfa6-e00f-486c-b9a0-d72cde99f868", groupId="format999")
         */
        $result = [];

        $textFull = ($this->frontSide ? $this->frontSide->getText() . "\n" : '') . ($this->backSide ? $this->backSide->getText() : '');
        $textFullConverted = str_replace([' ', '.', ',', ':'], '', $textFull);

        if (preg_match('/(?:\b|\D)(?<number>\d{10})(?:\b|\D)/', $textFullConverted, $matches)) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 4663578

        $patterns = [
            'image' => '/(?:^VISA$|MasterCard|Taishin International Bank)/i',
            'text'  => '/(?:\bVISA\b|MasterCard|AMERICAN\s*EXPRESS?|americanexpress?\.com\b|credit\s*card)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
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
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
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

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront([new Rectangle(0, 45, 100, 45)])
            ->setBack([new Rectangle(0, 30, 100, 50)]);
    }
}
