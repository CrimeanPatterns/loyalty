<?php

namespace AwardWallet\Engine\asiana\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAsiana implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        /**
         * @CardExample(accountId=3848658, cardUuid="7c7381c4-ac3f-445d-8eae-43e42b9c89eb")
         * @CardExample(accountId=3873151, cardUuid="4643dd81-1042-4a25-ba62-19f5ccb0ea31")
         * @CardExample(accountId=3904581, cardUuid="6f72f6f8-4f53-4785-9218-ab8c2edbbfa4")
         * @CardExample(accountId=2650569, cardUuid="2b6c2b32-e0ae-4c4f-9e12-f60d6310bf1e")
         * @CardExample(accountId=3817515, cardUuid="a393203c-8bf8-42c5-b312-3253f5d3f3b3")
         * @CardExample(accountId=3833956, cardUuid="c51ee7aa-1cae-4678-99a1-3ed98338d18f")
         * @CardExample(accountId=3735185, cardUuid="98441333-4be6-47cd-b35f-5b4f01793d99")
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

    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: Account 4964791

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        $patterns = [
            'image' => '/(?:VISA|Visa)/i',
            'text'  => '/(?:\bVISA\b|Bank\s*of\s*America|Security\n?\s?Code)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 60 or @top < 90]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide->getText(3); // deviation: 1-6

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (preg_match('/([O0o][Z2z]|[Z2z]|\b)(\d{9,10})(?:\D|\b)/', $textFrontConverted, $matches)) {
            $prefix = str_replace(['0', '2'], ['O', 'Z'], $matches[1]);
            $properties['Login'] = strtoupper($prefix) . $matches[2];
        }

        return $properties;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(30, 70, 40, 25)])
            ->setBack($rects);
    }
}
