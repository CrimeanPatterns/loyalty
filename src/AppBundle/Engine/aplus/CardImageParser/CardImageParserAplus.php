<?php

namespace AwardWallet\Engine\aplus\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\RegexpUtils as R;

class CardImageParserAplus implements CardImageParserInterface
{
    protected $devMode = 0;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $front = $cardRecognitionResult->getFront();
        $back = $cardRecognitionResult->getBack();

        if ($front === null) {
            $front = $cardRecognitionResult->getBack();
        }

        if ($front === null) {
            return [];
        }

        $textFront = $front === null ? '' : $front->getText();
        $textFront = str_replace('.', '', $textFront);

        if (preg_match('/\bMembership[ ]+Card[ ]+Number\b/i', $textFront)) { // Membership Card Number
            if ($properties = $this->parseFormat_1($textFront)) {
                return $properties;
            }
        }

        if ($result = $this->parseFormat_2($textFront)) {
            return $result;
        }

        $textBack = $back === null ? '' : $back->getText();
        $textBack = str_replace('.', '', $textBack);

        if ($result = $this->parseFormat_2($textBack)) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }
        $properties = [];

        /**
         * @CardExample(accountId=3788543, cardUuid="8a5c66b4-0374-4b5f-bdf5-c07988681cd0")
         */
        $pattern = '/'
            . '^.*Membership[ ]+Card[ ]+Number.*$'	// Membership Card Number
            . '\s+^[ ]*((?:' . R::digitsPattern() . '| ){10,})[ ]*$'		// Number
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            if (is_numeric($number = R::digitize(str_replace(' ', '', str_replace('.', '', $matches[1]))))) {
                return [
                    'Login' => $number,
                ];
            }
        }

        return $properties;
    }

    protected function parseFormat_2($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }
        $properties = [];

        /**
         * @CardExample(accountId=3840191, cardUuid="05b4c9c7-996b-4e93-ba89-de626a431764")
         * @CardExample(accountId=3884763, cardUuid="5cbb58f2-b7a5-49b2-9d2e-40d35e64f2e4")
         * @CardExample(accountId=3885971, cardUuid="509d05cf-d11a-4aff-a264-9bf46d09c053")
         * @CardExample(accountId=3845887, cardUuid="8e80ffa0-d438-421f-be3f-732ddcb98e6b")
         * @CardExample(accountId=4991103, cardUuid="c018b05e-3789-4a33-9e90-1bc5c7791d65")
         * @CardExample(accountId=4993700, cardUuid="6c6e9b73-3116-468f-b8d4-f8a1b852c10b")
         */
        $pattern = '/'
            . '^[ ]*[A-z][-\'A-z ]+[ ]*$'					// Name & Last Name
            . '(?:\s+^[ ]*\d{1,2}[ ]*\/[ ]*\d{2,4}[ ]*$)?'	// 12/2017
            . '\s+^[\- ]*((?:' . R::digitsPattern() . '| |\.|\-){10,})[ ]*$'				// Number
            . '/mui';

        if (preg_match($pattern, $textFront, $matches)) {
            if (is_numeric($number = R::digitize(str_replace(' ', '', str_replace('-', '', str_replace('.', '', $matches[1])))))) {
                return [
                    'Login' => $number,
                ];
            }
        }

        $pattern = '/'
            . '^[ ]*(?:[A-z][-\'A-z ]+|\w+\s+\w+)[ ]*$'                    // Name & Last Name
            . '\s*^[\- ]*((?:' . R::digitsPattern() . '| |\.|\-){10,}|\d+ \d+ \d)\s*\d{2}\s*\/\s*\d{4}\s*$'                // Number 12/2017
            . '/miu';

        if (preg_match($pattern, $textFront, $matches)) {
            if (is_numeric($number = R::digitize(str_replace(' ', '', str_replace('-', '', str_replace('.', '', $matches[1])))))) {
                return [
                    'Login' => $number,
                ];
            }
        }

        $pattern = '/(?:^|[ :])(308\d{4}[ ]?\d{7}[A-Z\d][ ]?[A-Z\d])(?:\s+|$)/mu'; //3081032 02684747 4 | 3081032 0268474A T | 3081032026847474

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => str_replace(' ', '', $matches[1]),
            ];
        }

        $pattern = '/\s+^nr\s([\d\s]+)$/miu'; //Nr. 3081 0336 7696 5608

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => str_replace(' ', '', $matches[1]),
            ];
        }

        $pattern = '/ '
            . '\s+^([\d\s]+)$' //3081034 23706479 0
            . '\s+^exp' //EXP. 12/2020
            . '/miu';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => str_replace(' ', '', $matches[1]),
            ];
        }

        return $properties;
    }
}
