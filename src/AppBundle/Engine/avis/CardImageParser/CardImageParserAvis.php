<?php

namespace AwardWallet\Engine\avis\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserAvis implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $front = $cardRecognitionResult->getFront();

        if (!$front) {
            return [];
        }

        $textFront = $front->getText();
        $textFront = $this->normalizeText($textFront);

        if (!empty($result = $this->parseFormat_1($textFront))) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Number
        ];
    }

    private function parseFormat_1($textFront): array
    {
        $properties = [];

        /**
         * @CardExample(accountId=4299486, cardUuid="8eb50ef0-50f9-427a-94e8-161bd5ca2588", groupId="format1")
         */
        if (preg_match('/Customer\s*Number\s*(?<number>[A-z\d]{6})$/im', $textFront, $m)
            && preg_match('/[A-z]/', $m['number']) && preg_match('/\d/', $m['number'])
        ) {
            $properties['Login'] = strtoupper($m['number']);

            return $properties;
        }

        /**
         * @CardExample(accountId=483772, cardUuid="fb458cf5-fb7e-45f4-99ed-6ec21f85ac59", groupId="format1")
         */
        $pattern = '/'
            . '\b[A-Z\d][A-Z\d ]{4,5}[A-Z\d]\b' // Number
            . '/';
        preg_match_all($pattern, $textFront, $numberMatches);

        foreach ($numberMatches[0] as $number) {
            $number = str_replace(' ', '', $number);

            /**
             * @CardExample(accountId=3845380, cardUuid="9b5b3997-1194-483d-be05-9c2eb9cb4060", groupId="format1")
             */
            $number = substr($number, -6); // get only last 6 characters

            if (preg_match('/[A-Z]/', $number) && preg_match('/\d/', $number)) {
                $properties['Login'] = $number;

                return $properties;
            }
        }

        return $properties;
    }

    private function normalizeText($string): string
    {
        /**
         * @CardExample(accountId=3585177, cardUuid="1cf60eeb-e30f-49bc-9ae8-00b0ab56d58a")
         */
        $string = str_replace(['.', ')', '('], '', $string);

        /**
         * @CardExample(accountId=4993265, cardUuid="e2fb635e-1f52-493a-89ef-cabc75c52a59")
         */
        $string = str_replace(['Н', 'З'], ['H', '3'], $string);

        /**
         * @CardExample(accountId=3280439, cardUuid="e89279e5-0912-489e-921e-54cc50582508")
         * @CardExample(accountId=3364079, cardUuid="b2a1246a-1591-4914-8fe6-8acc2948e25f")
         */
        $patternPrefix = '(' . implode('|', ['BER', 'ber']) . ')';
        $string = preg_replace('/' . $patternPrefix . '([A-Z\d][A-Z\d ]{4,5}[A-Z\d])$/m', '$1 $2', $string); // NUMBERC9D977 -> NUMBER C9D977

        /**
         * @CardExample(accountId=3806307, cardUuid="602da4df-3997-4968-88d6-188fbb50575e")
         */
        $string = preg_replace('/\b([A-Z\d][A-Z\d ]{4,5})([A-Z\d])\2+\b/', '$1$2', $string); // 3MA45HH -> 3MA45H

        // 3757867 https://awardwallet.com/cardImage/proxy/37df8993-7c75-411a-a0a1-84656c593037
        //		$string = str_replace("\n", ' ', $string); // delete because accountId=4299486 https://awardwallet.com/cardImage/proxy/602da4df-3997-4968-88d6-188fbb50575e
        return $string;
    }
}
