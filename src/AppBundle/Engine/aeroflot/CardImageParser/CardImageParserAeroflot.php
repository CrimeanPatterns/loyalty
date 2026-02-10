<?php

namespace AwardWallet\Engine\aeroflot\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserAeroflot implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4094778

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/(?:Visa|Sberbank)/i',
            'text'  => '/(?:\bVISA\b|\bSBERBANK\b)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $frontSide->getText();

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($backSide) {
            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $backSide->getText();

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        $textFront = $frontSide ? $frontSide->getText() : ''; // deviation: default

        /**
         * @CardExample(accountId=4447374, cardUuid="dc41d114-0f9e-40ff-b0cf-d65ab807997e", groupId="format1dot")
         * @CardExample(accountId=4149031, cardUuid="9a779168-c9b1-45e4-b6f6-4f3a47bde7e8", groupId="format1doubleQuotes")
         */
        $textFrontConverted = str_replace(['.', '"', ' '], '', $textFront);

        /**
         * @CardExample(accountId=4114059, cardUuid="ea244f00-09cf-4075-8c22-102147a42dc8", groupId="format1BONUS")
         */
        $textFrontConverted = str_replace('BONUS', '', $textFrontConverted);

        /**
         * @CardExample(accountId=4146119, cardUuid="9a3f6fbc-7bf3-4503-9e3e-0026393295d2", groupId="format1badSimbols")
         * @CardExample(accountId=3501308, cardUuid="55174aae-dc76-48e4-9640-fc3fcef5994d", groupId="format1badSimbols")
         * @CardExample(accountId=4067869, cardUuid="530a0eea-e6d2-4731-8378-0ac50c8ad08b", groupId="format1badSimbols")
         */
        $in = ['J', 'l', 'L', 't', 'S', 'b'];
        $out = ['1', '1', '1', '4', '5', '6'];
        $textFrontConverted = str_replace($in, $out, $textFrontConverted);

        /**
         * @CardExample(accountId=4289804, cardUuid="c2c41e4c-d279-433b-aed3-846f561cea68", groupId="format1")
         * @CardExample(accountId=3479433, cardUuid="cb06917a-6156-4a26-b7c6-65e8c86a4287", groupId="format1")
         * @CardExample(accountId=4326072, cardUuid="81cc1f3d-9b93-443b-b9a1-25d3cc385505", groupId="format1")
         * @CardExample(accountId=4289339, cardUuid="2086c153-77a8-44a1-ac36-c723ff371a3c", groupId="format1")
         * @CardExample(accountId=4135916, cardUuid="5188b7ac-1d97-41fa-893d-43b31831efd7", groupId="format1")
         * @CardExample(accountId=4065192, cardUuid="b5a86988-58fe-4555-ab5c-907a30e79433", groupId="format1")
         */
        if (
            preg_match('/(?:\b|\D)(?<number>\d{10})(?:\b|\D)/', $textFrontConverted, $m)
            || preg_match('/(?:\b|\D)(?<number>\d{9})(?:\b|\D)/', $textFrontConverted, $m)
            || preg_match('/(?:\b|\D)(?<number>\d{8})(?:\b|\D)/', $textFrontConverted, $m)
            || preg_match('/(?:\b|\D)(?<number>\d{7})(?:\b|\D)/', $textFrontConverted, $m)
            || preg_match('/(?:\b|\D)(?<number>\d{6})(?:\b|\D)/', $textFrontConverted, $m)
        ) {
            $result['Login'] = $m['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Card number
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(25, 70, 50, 30)])
            ->setBack($rects);
    }
}
