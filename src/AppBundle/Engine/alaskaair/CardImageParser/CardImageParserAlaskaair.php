<?php

namespace AwardWallet\Engine\alaskaair\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;
use AwardWallet\CardImageParser\RegexpUtils;

class CardImageParserAlaskaair implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    /** @var ImageRecognitionResult */
    protected $frontSide;

    /** @var ImageRecognitionResult */
    protected $backSide;

    /**
     * @Detector(version="4")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        if ($this->detectCC()) {
            $this->hideCCNumber($ccDetectionResult);
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        $textFront = $this->frontSide->getText();
        $textFront = str_replace('.', '', $textFront);

        if (preg_match('/^\s*(?:Me\s*mber\s+since|M[ ]*I[ ]*L[ ]*E[ ]*A[ ]*G[ ]*E[ ]+P[ ]*L[ ]*A[ ]*N)\b/mi', $textFront)) { // Member since    |    MILEAGE PLAN
            if ($properties = $this->parseFormat_1($textFront)) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=1308632, cardUuid="5d18e52e-9bff-4e49-ba03-5a775d2fed13", groupId="format2")
         */
        if (preg_match('/^[ ]*Member[ ]+number[ ]*$/mi', $textFront) && preg_match('/^[ ]*MEMBER[ ]+NAME[ ]*$/mi', $textFront)) { // Member number    &    MEMBER NAME
            if ($properties = $this->parseFormat_2($textFront)) {
                return $properties;
            }
        }

        // Other Formats
        if ($properties = $this->parseFormat_999()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Mileage Plan # or User ID
        ];
    }

    protected function parseFormat_1($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=1206882, cardUuid="41901776-9f76-4c9b-a0fd-0bc2324bc0c0", groupId="format1")
         * @CardExample(accountId=3862975, cardUuid="6945b802-7686-47db-98e9-5c14f87358cb", groupId="format1")
         */
        $pattern = '/'
            . '^[ ]*[A-z][-\'A-z ]*[ ]*$'	// Name & Last Name
            . '\s+^[ ]*(?<number>\d{8,})[ ]*$' // Number
            . '\s+^[ ]*Member[ ]+since'		// Member since
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => $matches['number'],
            ];
        }

        /**
         * @CardExample(accountId=3777507, cardUuid="8968597b-9d08-445d-8237-6d7867645109", groupId="format1")
         */
        $pattern = '/'
            . '^\s*(?<number>\d{8,})\s*$'   // Number
            . '\s*^\s*[A-z][-\'A-z\s]*\s*$'	// Name & Last Name
            . '\s*^\s*Me\s*mber\s+since'			// Member since
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => $matches['number'],
            ];
        }

        /**
         * @CardExample(accountId=2636924, cardUuid="6842445a-3c59-44b0-826e-4c375bbde98a", groupId="format1")
         */
        $pattern = '/'
            . '^[ ]*M[ ]*I[ ]*L[ ]*E[ ]*A[ ]*G[ ]*E[ ]+P[ ]*L[ ]*A[ ]*N[ ]*$'   // MILEAGE PLAN
            . '\s+^[ ]*[A-z][-\'A-z ]*[ ]*$'                                    // Name & Last Name
            . '\s+^[ ]*(?<number>\d{8,}|[\d\s]{8,})[ ]*$'                       // Number
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => preg_replace('/\s+/', '', $matches['number']),
            ];
        }

        /**
         * @CardExample(accountId=1354580, cardUuid="b2ef94fa-28a7-474b-b240-19f9c91ec984", groupId="format1")
         * @CardExample(accountId=2892562, cardUuid="ae4f187a-2e43-4b2e-bf8f-7ac5d4dce766", groupId="format1")
         */
        $pattern = '/'
            . '^[ ]*M[ ]*I[ ]*L[ ]*E[ ]*A[ ]*G[ ]*E[ ]+P[ ]*L[ ]*A[ ]*N[ ]*$'   // MILEAGE PLAN
            . '.*?'
            . '\s+^[ ]*(?<number>\d{8,})[ ]*$'                                  // Number
            . '\s+^[ ]*[A-z][-\'A-z ]*[ ]*$'                                    // Name & Last Name
            . '/mis';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => $matches['number'],
            ];
        }

        /**
         * @CardExample(accountId=1658424, cardUuid="f884478f-af7c-43de-9df3-1c78216d54be", groupId="format1")
         */
        $pattern = '/'
            . '^[ ]*(?<number>[\db]{8,})[ ]*$'                                                        // Number
            . '\s+^[ ]*(?:Me\s*mber\s+since|M[ ]*I[ ]*L[ ]*E[ ]*A[ ]*G[ ]*E[ ]+P[ ]*L[ ]*A[ ]*N)\b'   // MILEAGE PLAN
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => RegexpUtils::digitize($matches['number']),
            ];
        }

        return $properties;
    }

    protected function parseFormat_2($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $pattern = '/'
            . '^[ ]*(\d{5,})[ ]*$'				// Number
            . '\s+^[ ]*Member[ ]+number[ ]*$'	// Member number
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => $matches[1],
            ];
        }

        return $properties;
    }

    protected function parseFormat_999()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        /**
         * @CardExample(accountId=4664034, cardUuid="7e2f07fb-8b6e-4b5b-b528-ed3c3f9595a9", groupId="format999")
         */
        $result = [];

        $textFull = ($this->frontSide ? $this->frontSide->getText() . "\n" : '') . ($this->backSide ? $this->backSide->getText() : '');
        $textFullConverted = str_replace([' ', ',', ':'], '', $textFull);

        if (preg_match('/(?:\b|\D)(?<number>\d{8,9})(?:\b|\D)/', $textFullConverted, $matches)) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    protected function detectCC(): bool
    {
        // example accounts: 3828993,4678409

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        /**
         * @CardExample(accountId=3828993, cardUuid="5340fefc-80b9-4811-af9f-dbebc70467bc", groupId="formatCC1")
         */
        $patterns = [
            'image' => '/(?:^Visa$|MasterCard)/i',
            'text'  => '/(?:\bVISA\b|americanexpress|\bBank\s*Of\s*Ame[ry]ica\b|mastercard)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            /**
             * @CardExample(accountId=4437156, cardUuid="55b6e160-2107-4b24-a05b-ed3386ed482e", groupId="formatCC2")
             */
            if (preg_match('/VALID\s*THRU\s*\d{1,2}[\/]\d{1,2}/isu', $textFront) && preg_match('/\bSi[ag]nature\b/', $textFront)) {
                return true;
            }

            /**
             * @CardExample(accountId=4425420, cardUuid="831ea9ed-7c18-4b31-b902-91b9856ab123", groupId="formatCC2")
             */
            $str = '';

            if (preg_match('/\n([\da-z ]+)\n/', $textFront, $m)) {
                $str = RegexpUtils::digitize($m[1]);
            }

            if (false !== stripos($textFront, 'visa') && preg_match('/\d{4}[ ]?\d{4}[ ]?\d{4}[ ]?\d{4}[ ]?/', $str)) {
                return true;
            }

            if (preg_match($patterns['text'], $textFront)) {
                return true;
            }

            if (preg_match('/\n\d{4} \d{4} \d{4} \d{4}\n/', $textFront)) {
                return true;
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
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

    protected function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 45)])
            ->setBack($rects);
    }
}
