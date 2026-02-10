<?php

namespace AwardWallet\Engine\airfrance\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAirfrance implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    private $frontSide;
    private $backSide;

    private $namePrefixes = ['HERRN', 'MEVR', 'HERR', 'FRAU', 'MISS', 'MRS', 'DON', 'DHR', 'SRA', 'MME', 'SIG', 'SR', 'MS', 'MR', 'M'];

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4657078,4697044

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/(?:^Visa$|mastercard|Bank\s*Of\s*America)/i',
            'text'  => '/(?:\bVISA\b|mastercard|Bank\s*Of\s*Ame[ry]ica)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $this->frontSide->getText();

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $this->backSide->getText();

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

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

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Number or username
            'Login2', // last name
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 70, 55)])
            ->setBack($rects);
    }

    private function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        // FRONT

        if ($this->frontSide) {
            $textFront = $this->frontSide->getText();

            /**
             * @CardExample(accountId=4077171, cardUuid="41cedb64-deda-41b4-8979-c526a5ecc9e2", groupId="format1DotInNumber")
             */
            $textFrontConverted = str_replace([' ', '.', ':'], '', $textFront);

            if (preg_match('/(?:^|\D)(?<number>\d{10})$/m', $textFrontConverted, $m)) {
                $number = $m['number'];
                $result['Login'] = $number;
            }

            if (!empty($result['Login'])
                && preg_match("/^[,. ]*([[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]])\n" . implode(' *', str_split($result['Login'])) . "/mu", $textFront, $m)
                && stripos($textFront, 'Petroleum') === false // check 'Petroleum' -- for accs like 1458507
            ) {
                $mas = array_values(array_filter(preg_split('/[ ]+/', $m[1])));
                $result['Login2'] = str_replace([',', '.'], '', $mas[count($mas) - 1]);
            }

            if (
                empty($result['Login2'])
                || (!empty($result['Login2']) && stripos($result['Login2'], 'balance') !== false)
            ) {
                unset($result['Login2']);

                if (preg_match('/^(?:' . implode('|', $this->namePrefixes) . ')\.?[ ]+(?<fullName>.+)$/mi', $textFront, $m)) {
                    /**
                     * @CardExample(accountId=4157950, cardUuid="6105cd19-d6e1-4926-b6be-e0e24db86c31", groupId="format1Month")
                     */
                    $fullNameNormal = preg_replace('/^([A-z][-\'A-z ]*[A-z])[ ]+(?:Jul|[0-9]{1,2})[\/ ]+(?:19|20)\d{2}$/iu', '$1', $m['fullName']);

                    $mas = array_values(array_filter(preg_split('/[ ]+/', $fullNameNormal)));
                    $result['Login2'] = str_replace([',', '.'], '', $mas[count($mas) - 1]);
                }
            }
        }

        // BACK

        if ($this->backSide) {
            $textBack = $this->backSide->getText();

            if (empty($result['Login'])) {
                $textBackConverted = str_replace([' ', '.', ':'], '', $textBack);

                if (preg_match('/^(?:^|\D)(?<number>\d{10})$/m', $textBackConverted, $m)) {
                    $result['Login'] = $m['number'];
                }
            }

            if (empty($result['Login2'])
                && !empty($result['Login'])
                && preg_match("/^(?:explorer|gold|petroleum|platinum)$[\s\S]+^[,. ]*([[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]])\n" . implode(' *', str_split($result['Login'])) . "/imu", $textBack, $m)
            ) {
                /**
                 * @CardExample(accountId=4668925, cardUuid="f2ffdd1b-f523-4833-81dd-763e0054ff09", groupId="format1StatusOnMiddle")
                 */
                $mas = array_values(array_filter(preg_split('/[ ]+/', $m[1])));
                $result['Login2'] = str_replace([',', '.'], '', $mas[count($mas) - 1]);
            }
        }

        return $result;
    }
}
