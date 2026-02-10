<?php

namespace AwardWallet\Engine\azul\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAzul implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=2627172, cardUuid="e745333e-77fc-435d-bf74-ce5a7e167382")
         * @CardExample(accountId=1799579, cardUuid="203356dd-c083-43e5-b907-7bb939da4249")
         * @CardExample(accountId=3318969, cardUuid="64e63a8c-8c49-4cba-b8d5-aee56d70de01")
         * @CardExample(accountId=3859735, cardUuid="a69afe68-fd16-4ce2-a40a-3301f125ce9b")
         * @CardExample(accountId=3889654, cardUuid="fa6455ac-67f2-4c4b-b2f4-db869ef2ecbe")
         * @CardExample(accountId=2482498, cardUuid="fd48e98d-2010-4ef5-a1d7-51fe88acde83")
         * @CardExample(accountId=3754349, cardUuid="d94dddbb-08c1-477a-9494-079b3158dea3")
         * @CardExample(accountId=3881734, cardUuid="bf83828d-6783-419f-a112-3a78bab7043b")
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

        $textFront = $this->frontSide ? $this->frontSide->getText(3) : ''; // deviation: 1-7

        $textBack = $this->backSide ? $this->backSide->getText(3) : ''; // deviation: just as $textFront

        $textFull = $textFront . "\n" . $textBack;

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFull);

        if (preg_match('/(?:\b|\D)(\d{10})(?:\b|\D)/', $textFrontConverted, $matches) || preg_match('/(?:\b|\D)(\d{11})(?!\s*\()(?:\n|$)/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }
}
