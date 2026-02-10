<?php

namespace AwardWallet\Engine\etihad\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserEtihad implements CardImageParserInterface, CreditCardDetectorInterface
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

        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        if ($properties = $this->parseFormat_2_Skywards()) {
            return $properties;
        }

        return $properties;
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 3746500,4140185,3995902,1906468,4162965,4712111

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        // FRONT

        if ($this->frontSide) {
            $domFront = $this->frontSide->getDOM(0);
            $textFront = $this->frontSide->getText();

            $frontLogos = $domFront->findNodes('/img[@left > 50 or @top < 50]/@alt', null, '/(?:express|paypal|^Visa$|mastercard)/i');
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (
                !empty($frontLogoValues[0])
                || $textFront->findPreg('/(?:america|express|paypal|\bvisa\b|mastercard|Dhabi\s*Bank)/i') !== null
            ) {
                $this->hideCCNumber($ccDetectionResult, true, false);
            }
        }

        // BACK

        if ($this->backSide) {
            $textBack = $this->backSide->getText();

            if ($textBack->findPreg('/(?:americanexpress|american\s+express|\bvisa\b|\.BANKFAB\b|\bBANKFAB\.|Dhabi\s*Bank)/i') !== null) {
                $this->hideCCNumber($ccDetectionResult, false, true);
            }
        }

        return $ccDetectionResult;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Username / Etihad Guest Number
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3746500, cardUuid="896fbee0-1ade-4911-9ba5-fe86d3247169", groupId="format1")
         * @CardExample(accountId=3689205, cardUuid="a90feb6d-5b59-4a73-bb88-157378ea7f56", groupId="format1")
         * @CardExample(accountId=3650516, cardUuid="a2f9886e-9d44-4ff9-8384-4faa0625cca8", groupId="format1")
         * @CardExample(accountId=3913629, cardUuid="b0bdc03f-f265-4362-b6cd-9295e456f56f", groupId="format1")
         */
        $textFront = $this->getTextRectangle('front', 6, 0, 0, 15, 0); // deviation: 3-9

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (preg_match('/(?:^|\D|\b)(?<number>\d{12})(?:\b|\D|$)/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }

    protected function parseFormat_2_Skywards()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        // See parser: skywards/CardImageParserSkywards

        $properties = [];

        /**
         * @CardExample(accountId=4749049, cardUuid="e6af2aaa-cd1f-4d7a-a984-5ce3520b4a33", groupId="format2Skywards")
         */
        $textFront = $this->frontSide ? str_replace('.', '', $this->frontSide->getText()) : '';
        $textBack = $this->backSide ? str_replace('.', '', $this->backSide->getText()) : '';
        $textFull = $textFront . "\n" . $textBack;

        $patternPrefix = '(?<prefix>' . implode('|', ['EK']) . ')';
        $pattern = '/'
            . '\b' . $patternPrefix . '[ ]*(?<number>\d[\d ]{7,}\d)\b'
            . '/';

        if (preg_match($pattern, $textFull, $m)) {
            $properties['Login'] = $m['prefix'] . str_replace(' ', '', $m['number']);
        }

        return $properties;
    }

    protected function getTextRectangle(
        $side = 'front',
        $deviation = 7,
        $marginLeft = 0,
        $marginRight = 0,
        $marginTop = 0,
        $marginBottom = 0
    ) {
        if ($side === 'front' && $this->frontSide) {
            $dom = $this->frontSide->getDOM($deviation);
        } elseif ($side === 'back' && $this->backSide) {
            $dom = $this->backSide->getDOM($deviation);
        } else {
            return '';
        }

        $xpathFragment = '/span[@left >= ' . $marginLeft . ' and @left <= ' . (100 - $marginRight) . ' and @top >= ' . $marginTop . ' and @top <= ' . (100 - $marginBottom) . ']';

        $rowsText = [];

        $rowNumber = 1;
        $maxRows = 100;

        while ($rowNumber < $maxRows) {
            if ($dom->findSingleNode('/div[' . $rowNumber . ']') !== null) {
                $rowTexts = $dom->findNodes('/div[' . $rowNumber . ']' . $xpathFragment);
                $rowsText[] = implode('', $rowTexts);
            } else {
                break;
            }
            $rowNumber++;
        }

        return trim(implode("\n", $rowsText));
    }

    protected function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult, $front = false, $back = false)
    {
        $rects = [new Rectangle(5, 30, 90, 40), new Rectangle(5, 70, 65, 20)];

        if ($front) {
            $ccDetectionResult
                ->setFront($rects);
        }

        if ($back) {
            $ccDetectionResult
                ->setBack($rects);
        }
    }
}
