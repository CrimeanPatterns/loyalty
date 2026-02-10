<?php

namespace AwardWallet\Engine\aa\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

// TODO: safely remove methods `parseFormat_1` and `parseFormat_2`

class CardImageParserAa implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $detectedFormat = '';

    /** @var ImageRecognitionResult */
    protected $frontSide;
    /** @var ImageRecognitionResult */
    protected $backSide;

    protected $statusVariants = ['\bEXECUTIVE[ ]*PLATINUM', 'PLATINUM\b', 'PLATINUA\b', '\bGOLD\b'];

    // https://www.americanairlines.com.ru/loyalty/enrollment/enroll
    protected $namePrefixes = [
        'MASTER', 'MISS', 'MRS.', 'MR.', 'MS.', 'DR.', // en
        'SRTA.', 'SRA.', 'SR.', // es
    ];

    private static $titles = ['MASTER', 'MISS', 'MRS\.?', 'MR\.?', 'MS\.?', 'DR\.?', // en
        'SRTA\.?', 'SRA\.?', 'SR\.?', 'DR\.?',				 // es
        'JR\.', ];

    private $patterns = [];

    /*
     * @Detector(version="8")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        if ($this->detectCC_1($this->frontSide, $this->backSide) || $this->detectCC_1($this->backSide, $this->frontSide)) {
            $this->hideCCNumber($ccDetectionResult);

            return $ccDetectionResult;
        }

        if ($this->detectCC_2()) {
            $this->hideCCNumber($ccDetectionResult);

            return $ccDetectionResult;
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        $this->patterns['prefix'] = '(?:' . implode('|', $this->namePrefixes) . ')';
        $this->patterns['prefixClear'] = '(?:' . implode('|', array_map(function ($item) { return rtrim($item, '.'); }, $this->namePrefixes)) . ')';

        $textFront = $this->frontSide ? $this->frontSide->getText() : '';
        $textBack = $this->backSide ? $this->backSide->getText() : '';

        /**
         * @CardExample(accountId=3963117, cardUuid="f1b87fa0-c505-44dd-b6cf-bc9bba09fd01", groupId="formatCC1")
         * @CardExample(accountId=3881456, cardUuid="6b8fd687-24f9-4aa4-9e86-f757ec763edc", groupId="formatCC1")
         */
        if ($this->detectCC_1($this->frontSide, $this->backSide) || $this->detectCC_1($this->backSide, $this->frontSide)) { // Detect Credit Card (Format 1)
            if (!empty($properties = $this->parseFormat_1()) && !empty($properties['Login'])) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=3840036, cardUuid="8078e1b4-7acb-48cb-b011-95bfd7e0827f", groupId="formatCC2")
         */
        if ($this->detectCC_2()) { // Detect Credit Card (Format 2)
            if (!empty($properties = $this->parseFormat_2()) && !empty($properties['Login'])) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=4918880, cardUuid="d489b8ec-09db-4d40-a7b9-c09b952d5f52", groupId="format3")
         * @CardExample(accountId=4949594, cardUuid="6a515212-06f9-4873-93b6-5ce0dcd38001", groupId="format3")
         */
        if (preg_match('/Your[ ]*key[ ]*to[ ]*reward/i', $textFront) > 0) {
            if (!empty($properties = $this->parseFormat_3($textFront)) && !empty($properties['Login'])) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=3981784, cardUuid="3d71db90-ed12-4503-806a-a0e610ca0fcb", groupId="format4")
         */
        $textFrontMiddle = $this->frontSide ? $this->frontSide->getDOM(6)->getTextRectangle(40, 0, 25, 25) : ''; // deviation: 1-12
        $condition1 = preg_match('/(' . implode('|', $this->statusVariants) . ')/', $textFrontMiddle) > 0;

        if ($condition1) {
            if (!empty($properties = $this->parseFormat_4()) && !empty($properties['Login'])) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=3976108, cardUuid="27b863d2-eaee-428f-962c-8923f4595329", groupId="format5")
         * @CardExample(accountId=3852078, cardUuid="1b675400-90b8-42a4-ae5c-984f0b5213bf", groupId="format5")
         * @CardExample(accountId=4965223, cardUuid="ce6be0b9-373d-468c-80e0-8624e99dccc2", groupId="format5")
         * @CardExample(accountId=4181082, cardUuid="ffff77a2-ca69-42d3-84b6-0a80d12b7cd5", groupId="format5invalidSymbol")
         */
        $xpathFragment1 = '@left < 30 and @top < 40';
        $logoTop = $this->frontSide ? $this->frontSide->getDOM(0)->findSingleNode('/img[' . $xpathFragment1 . ' and (contains(@alt,"AAdvantage") or contains(@alt,"American Airlines Group"))]') : null;
        $textTop = $this->frontSide ? $this->frontSide->getDOM(0)->findSingleNode('/div/span[' . $xpathFragment1 . ' and (contains(.,"AAdvantage") or contains(.,"A\'Advantage"))]') : null;
        $condition1 = $logoTop !== null || $textTop !== null;
        $textFrontMiddle = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(0, 0, 25, 20) : ''; // deviation: 0-4
        $textFrontMiddle = $this->normalizeText($textFrontMiddle);
        $condition2 = preg_match('/AAdvantage[ ]*#/i', $textFrontMiddle) > 0;
        $textFrontBottom = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(0, 0, 65, 0) : ''; // deviation: just as $textFrontMiddle
        $condition3 = preg_match('/Your[ ]*AAdvantage[ ]*Number[ ]*When[ ]*Making/i', $textFrontBottom) > 0;
        $condition4 = preg_match('/Or[ ]*contact[ ]*your[ ]*local[ ]*American[ ]*Airlines[ ]*Reservations[ ]*Office/i', $textBack) > 0;

        if ($condition1 || $condition2 || $condition3 || $condition4) {
            if (!empty($properties = $this->parseFormat_5($textFrontMiddle)) && !empty($properties['Login'])
                || !empty($properties = $this->parseFormat_5($textFront)) && !empty($properties['Login'])
            ) {
                return $properties;
            }
        }

        // Other Formats
        if (!empty($properties = $this->parseFormat_999()) && !empty($properties['Login'])) {
            return $properties;
        }

        // etc.

        return [];
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // AAdvantage Number
            'Login2', // Last Name
        ];
    }

    /**
     * @deprecated credit cards not parse
     *
     * @return array
     */
    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        if ($this->backSide) {
            // Number

            $textBack = $this->backSide->getDOM(10)->getTextRectangle(35, 35, 40, 35); // deviation: 8-12

            $textBackConverted = str_replace('.', '', $textBack);

            $textBackConverted = preg_replace('/[A-z]*ADVANTAGE/i', '', $textBackConverted);

            $textBackConverted = preg_replace('/\b[A-z]{3,}\b/i', '', $textBackConverted);

            if (preg_match_all('/\b([A-Z\d]{7})\b/', $textBackConverted, $numberMatches)) {
                $numberMatches[1] = array_reverse($numberMatches[1]);

                foreach ($numberMatches[1] as $number) {
                    if (preg_match('/[A-Z]/', $number) && preg_match('/\d/', $number)) {
                        $properties['Login'] = $number;

                        break;
                    }
                }
            }

            // Name & Last Name

            $textBack = $this->backSide->getDOM(4)->getTextRectangle(0, 50, 55, 20); // deviation: 1-9

            $textBackConverted = str_replace('.', '', $textBack);

            $textBackConverted = str_replace('$', 'S', $textBackConverted);

            if (preg_match_all('/\b([A-Z][-\'A-Z]*[A-Z])\b/', $textBackConverted, $nameMatches)) {
                $lastMatch = $nameMatches[1][count($nameMatches[1]) - 1];
                $properties['Login2'] = $lastMatch;
            }
        }

        return $properties;
    }

    /**
     * @deprecated credit cards not parse
     *
     * @return array
     */
    protected function parseFormat_2()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        if ($this->frontSide) {
            // Name & Last Name

            $textFrontBottom = $this->frontSide->getDOM(3)->getTextRectangle(0, 35, 70, 0); // deviation: 1-6

            $textFrontBottomConverted = str_replace('.', '', $textFrontBottom);

            $textFrontBottomConverted = preg_replace('/\b(VALID|THRU|EXPIRES|FROM|END|Member|Since)\b/i', '', $textFrontBottomConverted);

            if (preg_match_all('/^[ ]*([A-z][-\'A-z ]*)[ ]*$/m', $textFrontBottomConverted, $nameMatches)) {
                $lastMatch = $nameMatches[1][count($nameMatches[1]) - 1];
                $names = $this->parsePersonName($lastMatch);

                if (!empty($names['lastname'])) {
                    $properties['Login2'] = $names['lastname'];
                }
            }
        }

        return $properties;
    }

    protected function parseFormat_4()
    {
        // example accounts: 3046642,3981784,1548136,1384083,3988901,3990054,3995148,4005062,3964789,3964267,3923269,3968274,3902730,3971104,3073392,3712480,3459151,3530251,3485654,3465799,2457672,3725384,1422770,1475965,1393462,1384674,1378548,1370809,2328215,2060275,1882382,3883382,3883268,3875534,3783616,3829846,3827875,3826389,3822517,3812860

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $badSimbols = ['.', ',', '/', '(', ')', ':'];

        $patterns['nameLastName'] = '$\s+^.*?([A-z][-\'A-z ]+)[ ]*$';

        $textFrontBottom = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(20, 0, 65, 0) : ''; // deviation: 0-5

        $textFrontMiddle_1 = $this->frontSide ? $this->frontSide->getDOM(4)->getTextRectangle(20, 0, 25, 25) : ''; // deviation: 1-5

        // Number

        $textFrontBottomConverted = str_replace($badSimbols, '', $textFrontBottom);

        $textFrontBottomConverted = str_replace(' ', '', $textFrontBottomConverted);

        preg_match_all('/\b([A-Z\d]{7})$/m', $textFrontBottomConverted, $numberMatches);

        foreach ($numberMatches[1] as $number) {
            if (preg_match('/\d/', $number)) {
                $properties['Login'] = $number;

                break;
            }
        }

        // Name & Last Name

        $textFrontMiddle_1Converted = str_replace($badSimbols, '', $textFrontMiddle_1);

        if (preg_match('/(?:' . implode('|', $this->statusVariants) . ')' . $patterns['nameLastName'] . '/m', $textFrontMiddle_1Converted, $matches)) {
            $names = $this->parsePersonName($matches[1]);

            if (!empty($names['lastname'])) {
                $properties['Login2'] = $names['lastname'];
            }
        }

        if (empty($properties['Login2'])) {
            /**
             * @CardExample(accountId=3883268, cardUuid="fc18a4ff-094f-45cf-b898-bf7ec71a6f63", groupId="format4")
             */
            $textFrontMiddle_2 = $this->frontSide ? $this->frontSide->getDOM(6)->getTextRectangle(20, 0, 25, 25) : ''; // deviation: 6
            $textFrontMiddle_2Converted = str_replace($badSimbols, '', $textFrontMiddle_2);

            if (preg_match('/(?:' . implode('|', $this->statusVariants) . ')' . $patterns['nameLastName'] . '/m', $textFrontMiddle_2Converted, $matches)) {
                $names = $this->parsePersonName($matches[1]);

                if (!empty($names['lastname'])) {
                    $properties['Login2'] = $names['lastname'];
                }
            }
        }

        if (empty($properties['Login2']) && !empty($properties['Login'])) {
            /**
             * @CardExample(accountId=3968274, cardUuid="d26373d1-9ae1-4a26-b3df-581a5176a152", groupId="format4")
             */
            if (preg_match('/' . $properties['Login'] . $patterns['nameLastName'] . '/m', $textFrontBottom, $matches)) {
                $names = $this->parsePersonName($matches[1]);

                if (!empty($names['lastname'])) {
                    $properties['Login2'] = $names['lastname'];
                }
            }
        }

        return $properties;
    }

    protected function parseFormat_5($textFrontMiddle)
    {
        // example accounts: 3881580,3931425,3784358,3821384,3858820,3809923,3984712,3976108,1540481,4000281,4001373,4007948,3888293,3993023,3895833,3930150,3987207,3891113,3934295,4001626,3942451,3961759,3958213,3919338,3961347,3908266,3914895,3976882,3913790,3173404,2933170,2875972,3712615,3711977,3664167,3614806,1413455,1434520,2402884,1957617,2348448,1752214,1752210,3716178,3727481,3849240,3852078,3849245,3849243,3848839,715476,3840083,3882569,3868699,3834211,3767145,3772298,3769569,3769561,3769555,3764557,3794189,3830610,3823382,3814718,3810427,3804521,3804009

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFrontMiddleConverted = str_ireplace(['AAdvantage', "A'Advantage", 'Advantage', 'AAdvatage', 'American', 'Airlines'], '', $textFrontMiddle);

        $textFrontMiddleConverted_1 = str_replace(['.', ',', '/', '(', ')', ':'], '', $textFrontMiddleConverted);

        // Number

        $patterns = [
            'number1' => '/#(?<number>[A-z\d]{7})\b/i', // AAdvantage#M2206H4
            'number2' => '/'
                . '^[ ]*[A-z][-\'A-z]+[ ]*$'
                . '\s+^(?<number>[A-z\d]{7})$'
                . '/m',
        ];

        $textFrontMiddleConverted_2 = str_replace(' ', '', $textFrontMiddleConverted_1);

        if (preg_match($patterns['number1'], $textFrontMiddleConverted_2, $matches)) {
            $properties['Login'] = strtoupper($matches['number']);
        }

        if (empty($properties['Login'])) {
            if (preg_match($patterns['number2'], $textFrontMiddleConverted_2, $matches)) {
                $properties['Login'] = strtoupper($matches['number']);
            }
        }

        if (empty($properties['Login'])) {
            /**
             * @CardExample(accountId=4237956, cardUuid="37e4a1ae-cc6c-4e82-9f10-2badaeba44b6", groupId="format5")
             */
            $textFrontMiddleConverted_2 = preg_replace('/(\b|[^1])11([^1]|\b)/', '$1H$2', $textFrontMiddleConverted_2); // M2206114    ->    M2206H4

            if (preg_match($patterns['number1'], $textFrontMiddleConverted_2, $matches)) {
                $properties['Login'] = strtoupper($matches['number']);
            }
        }

        // Name & Last Name

        /**
         * @CardExample(accountId=2536735, cardUuid="38adfa10-ea3b-4c3c-b1cd-391991c91762", groupId="format5prefixClear")
         */
        if (preg_match("/^[ ]*(?:.+?[ ]+)?(?<name>{$this->patterns['prefixClear']}[ ]+[A-z][-\'A-z ]+)[ ]*$\s+#/mi", $textFrontMiddleConverted_1, $m)
            || preg_match('/^[ ]*(?<name>[A-z][-\'A-z ]+)[ ]*$\s+#/m', $textFrontMiddleConverted_1, $m)
        ) {
            /**
             * @CardExample(accountId=4007948, cardUuid="bff8673b-8888-4ba3-a037-ca725b3904d4", groupId="format5")
             */
            $m['name'] = preg_replace('/(.+)[ ]*MD$/i', '$1', $m['name']);

            $names = $this->parsePersonName($m['name']);

            if (!empty($names['lastname'])) {
                $properties['Login2'] = $names['lastname'];
            }
        }

        if (empty($properties['Login2'])) {
            if (preg_match('/^[ ]*(?<name>[A-z][-\'A-z ]+)[ ]*$\s+^[A-z\d ]+$/m', $textFrontMiddleConverted_1, $m)) {
                $names = $this->parsePersonName($m['name']);

                if (!empty($names['lastname'])) {
                    $properties['Login2'] = $names['lastname'];
                }
            }
        }

        return $properties;
    }

    protected function parseFormat_999(): array
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        if ($this->frontSide) {
            $frontText = $this->frontSide->getText();

            $frontText = str_replace(['し'], ['L'], $frontText);

            if (preg_match('/(?:AAdvantage|AAdvatage|AAdvantage account)\s*#?\s*([A-Z\d ]{7,8})/', $frontText, $m)
                && preg_match('/\d/', $m[1])
            ) {
                // AAdvantage # 4A5 7N58

                /**
                 * @CardExample(accountId=3849243, cardUuid="3ea8c4dc-0b50-4d85-85aa-c7f774c581f9", groupId="format999")
                 */
                $clean = preg_replace("#[^A-Z\d]+#", "", $m[1]);

                if (mb_strlen($clean, 'UTF-8') == 7) {
                    $result['Login'] = $clean;
                }
            } elseif (preg_match('/(?:AAdvantage|AAdvatage|AAdvantage account|Aadvantage)\s*.+\s*([A-Z\d ]{3,8}w[A-Z\d]{1,5})[\s\n]?/u', $frontText, $m)) {
                $m[1] = str_replace(['w'], ['W'], $m[1]);
                $result['Login'] = $m[1];
            } elseif (preg_match("#\#\s*([A-Z\d ]{7,8})#", $frontText, $m) && preg_match("#\d+#", $m[1])) {
                // #029YT30

                /**
                 * @CardExample(accountId=3711977, cardUuid="d6c401c7-6618-4a89-af14-71b4ea30bdef", groupId="format999")
                 */
                $clean = preg_replace("#[^A-Z\d]+#", "", $m[1]);

                if (mb_strlen($clean, 'UTF-8') == 7) {
                    $result['Login'] = $clean;
                }
            } elseif (preg_match('/(?i)AAdvantage Number(?-i)\s*:?\s*([A-Z\d ]{7,8})\s/', $frontText, $m)
                && preg_match('/\d/', $m[1])
            ) {
                // AAdvantage Number: 9J71JK6

                /**
                 * @CardExample(accountId=3887660, cardUuid="3f813491-b8fb-4a9a-8bb3-418251922ef6", groupId="format999")
                 * @CardExample(accountId=5002420, cardUuid="7175d33e-fe53-40af-89cd-720ec0a1b17a", groupId="format999")
                 */
                $clean = preg_replace("#[^A-Z\d]+#", "", $m[1]);

                if (mb_strlen($clean, 'UTF-8') == 7) {
                    $result['Login'] = $clean;
                }
            } elseif (preg_match_all("#(?:\n|^)([A-Z\d\s]{7,8})(?:\s*/\s*|\s+)[^\s\d]+\s+\d+/\d+#ms", $frontText, $m)) {
                // 1EUD928/THRU 1/31/18    |    02T79 B8 THRU 1/31/19

                /**
                 * @CardExample(accountId=3846960, cardUuid="3c7cbadc-99c7-4f32-b78f-4434e16b4292", groupId="format999")
                 * @CardExample(accountId=3868931, cardUuid="587a53a7-146d-49da-a2e4-98092c1edbbc", groupId="format999")
                 */
                foreach ($m[1] as $num) {
                    $clean = preg_replace("#[^A-Z\d]+#", "", $num);

                    if (mb_strlen($clean, 'UTF-8') == 7 && preg_match("#\d+#", $clean)) {
                        $result['Login'] = $clean;

                        break;
                    }
                }
            } elseif (preg_match_all("#(?:\n|^)([A-Z\d\s]{7,8})(?:$|\s)#m", $frontText . "\n", $m)) {
                // T6131 TO    |    02RNF36

                /**
                 * @CardExample(accountId=1475965, cardUuid="e690a6e0-abd7-4404-9956-a51253d94177", groupId="format999")
                 * @CardExample(accountId=3853426, cardUuid="5f3f8a35-798a-47c0-b033-28be9f4bd935", groupId="format999")
                 */
                foreach ($m[1] as $num) {
                    $clean = preg_replace("#[^A-Z\d]+#", "", $num);

                    if (mb_strlen($clean, 'UTF-8') == 7 && preg_match("#\d+#", $clean)) {
                        $result['Login'] = $clean;

                        break;
                    }
                }
            }

            if (preg_match("#NAME\n((?:[A-Z]{2,}([^\S\n]|-|\b)){2,4})#", $frontText, $m)) {
                // NAME\nSARAH MCALISTER
                $name = trim($m[1]);
                $p = explode(" ", $name);
                $result['Login2'] = end($p);
            } elseif (preg_match("#(?:" . implode("|", self::$titles) . ") (([A-Z]+([^\S\n]|-|\b)){2,4}),?(?:\n|$)#i", $frontText, $m)) {
                // Miss Tristan Narraway    |    MS. MARTHA R FRIE DRICKS

                /**
                 * @CardExample(accountId=3849243, cardUuid="3ea8c4dc-0b50-4d85-85aa-c7f774c581f9", groupId="format999")
                 */
                $name = trim($m[1]);
                $p = explode(" ", $name);
                $result['Login2'] = end($p);
            } elseif (preg_match("#(([A-Z]+([^\S\n]|-|\b)){2,4}) (?:" . implode("|", self::$titles) . ")(?:\n|$)#i", $frontText, $m)) {
                // FRANCIS HANGARTER JR.
                $name = trim($m[1]);
                $p = explode(" ", $name);
                $result['Login2'] = end($p);
            } elseif (preg_match('/^[A-Z]+ [A-Z]\. ([A-Z]+)$/im', $frontText, $m)) {
                // Thomas B. Bauckman    |    James A. McCall
                $result['Login2'] = $m[1];
            } elseif (preg_match('/^[A-Z]+ [A-Z]\. [A-Z]+ ([A-Z]+)$/im', $frontText, $m)) {
                // Kim D. criswell KARR
                $result['Login2'] = $m[1];
            } elseif (preg_match('/^[ ]*(?<name>[A-z][-\'A-z ]+)[ ]*$\s+^.*Number.+$\s+Expires/im', $frontText, $m)) {
                /*
                    Kimberly Bruyere
                    AAdvantage number W72X584
                    Expires 6/30/2004
                */
                $names = $this->parsePersonName($m['name']);

                if (!empty($names['lastname'])) {
                    $result['Login2'] = $names['lastname'];
                }
            } elseif (preg_match('/^[A-Z]+ [A-Z] ([A-Z]+)$/m', $frontText, $m)) {
                // JOSEPH W REGENSBURG
                $result['Login2'] = $m[1];
            } elseif (preg_match('/^((?:[A-Z][a-z]*(?:[^\S\n]|-|\b)){2,4})$/m', $frontText, $m)
                && stripos($m[1], 'American') === false && stripos($m[1], 'Airlines') === false
                && stripos($m[1], 'Advantage') === false && strlen($m[1]) > 4
            ) {
                // Jeffrey Miller Gilbert    not American Airlines
                $name = trim($m[1]);
                $p = explode(" ", $name);
                $result['Login2'] = end($p);
            } elseif (preg_match('/^((?:[A-Z]{2,}([^\S\n]|-|\b)){2,4})$/m', $frontText, $m)
                && stripos($m[1], 'MILES') === false && stripos($m[1], 'THRU') === false && stripos($m[1], 'PLATINUM PRO') === false
            ) {
                // JEFFREY MILLER GILBERT    |    MARIE CLEM BUENAFLOR CARLOS    |    YUEH-LUN HSU  not AWARD MILES
                $name = trim($m[1]);
                $p = explode(" ", $name);
                $result['Login2'] = end($p);
            } elseif (preg_match('/^[A-Z] ([A-Z]{2,})$/m', $frontText, $m)) {
                // S CLAUSSNITZER
                $result['Login2'] = $m[1];
            } elseif (preg_match('/^[A-Z] [A-Z] ((?:[A-Z]{2,}([^\S\n]|-|\b)){2,3})$/m', $frontText, $m)) {
                // M J ANDERSON-DE REGIL
                $name = trim($m[1]);
                $p = explode(" ", $name);
                $result['Login2'] = end($p);
            } elseif (preg_match("/\n.+\n\w+\s+(\w+)\n/", $frontText, $m)
                && false === stripos($m[1], 'AmericanAirlines') && false === stripos($m[1], 'account')
            ) {
                // CLAUDE PETTIS
                $result['Login2'] = $m[1];
            }
        }

        if ($this->backSide) {
            $backText = $this->backSide->getText();

            if (preg_match('/(?i)AAdvantage Number(?-i)\s*:?\s*([A-Z\d ]{7,8})\s/', $backText, $m) // AAdvantage Number: 9J71JK6
                && preg_match('/\d/', $m[1])
            ) {
                /**
                 * @CardExample(accountId=3338073, cardUuid="3e03a5a7-64c7-4a98-a2d2-05821798dcf2", groupId="format999")
                 */
                $clean = preg_replace("#[^A-Z\d]+#", "", $m[1]);

                if (mb_strlen($clean, 'UTF-8') == 7) {
                    $result['Login'] = $clean;
                }
            }
        }

        return $result;
    }

    // TODO: merge methods `detectCC_1` and `detectCC_2`

    protected function detectCC_1($frontSide, $backSide): bool
    {
        // example accounts: 3875519,3764820,3733544,3765279,3929399,3916468,3916434,3775097,3778805,3713694,3569115,3264838,3938802,3483461,3932631,3712891,3669393,3672955,3929409,3786574,3864390,3887137,3855659,3881456,3859871,3867591,3879408,3848612,3821447,3796389,3812267,3908207,3900235,3888384,3896299,3893707,3840488,3940203,3941160,2423466,3955008,3955409,3946474,3981494,2790720,3984081,3963790,1385230,1385012,2373889,3963117,3900196,3897841,2760694,3943919,3769295,4463545,4469034,4469265

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        if ($this->detectedFormat === __FUNCTION__) {
            return true;
        } elseif ($this->detectedFormat) {
            return false;
        }

        /**
         * @CardExample(accountId=3775097, cardUuid="f02a1155-44eb-4359-976c-e5110b3c00c0", groupId="formatCC1")
         * @CardExample(accountId=3984081, cardUuid="46f0b2b1-f05e-4c2b-84a9-467e7ada16a0", groupId="formatCC1")
         */
        $patterns = [
            'image' => '/(?:Citibank|MasterCard|Bank\s*of\s*America|Citi)/i',
            'text'  => '/(?:\b1[-.( ]+888[-.) ]+766[-. ]2484\b|citi[ ]*\.[ ]*com|citibank[ ]*\.[ ]*com|MasterCard|\bastercar\b|\bCiti(?:bank)?\b[\s\S]+MasterCar?d?\b|Bank\s*of\s*America)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            $textFrontBottom = $frontSide->getDOM(5)->getTextRectangle(70, 0, 60, 0); // deviation: 5

            if (preg_match('/\bciti\b/i', $textFrontBottom)) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            $textFront = $frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }
        }

        // BACK

        if ($backSide) {
            $backLogos = $backSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            /**
             * @CardExample(accountId=3812267, cardUuid="516399bf-c4e9-4a9d-a5b5-8278348ab4ca", groupId="formatCC1")
             */
            $textBackTop = $backSide->getDOM(2)->getTextRectangle(0, 0, 0, 40); // deviation: 1-4

            if (preg_match('/[ce]iti\.com\/[ce]r[ce]dit[ce]ard/i', $textBackTop)) { // citi.com/creditcards
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            $textBackTopConverted = str_replace(['.', ' '], '', $textBackTop);

            if (preg_match('/\b\d{15,16}\b/', $textBackTopConverted)) { // 5446 1612 8177 0551
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            $textBack = $backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }
        }

        return false;
    }

    protected function detectCC_2(): bool
    {
        // example accounts: 3925238,3412347,3908557,3929486,3880022,3930603,3878171,3980079,3972005,3962518,3954254,3932169,3939859,3582170,3428956,3748327,3833645,3840036,3835125,3831537,3772968,4238453

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        if ($this->detectedFormat === __FUNCTION__) {
            return true;
        } elseif ($this->detectedFormat) {
            return false;
        }

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, '/(MasterCard|VISA|American\s*Express|Citibank|MBNA|Santander)/i');
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match('/(?:MasterCard|\bVISA\b|AMERICAN\s*EXPRESS|\bSantander\b)/i', $textFront)) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, '/(BPAY|Barclaycard|Citibank|MBNA)/i');
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            /**
             * @CardExample(accountId=3840036, cardUuid="546add4f-ce2e-410a-813c-4f199d6a3ecc", groupId="formatCC2")
             * @CardExample(accountId=3980079, cardUuid="07239a32-2eb4-40da-8ecb-bd0fcc03e3e7", groupId="formatCC2")
             */
            $textBackTop = $this->backSide->getDOM(6)->getTextRectangle(0, 0, 0, 75); // deviation: 1-12

            if (stripos($textBackTop, 'AviatorMasterCard')) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            /**
             * @CardExample(accountId=3932169, cardUuid="72719c1c-ffd6-4a89-938b-be4efc05dc1f", groupId="formatCC2")
             */
            $textBackBottom = $this->backSide->getDOM(1)->getTextRectangle(0, 0, 75, 0); // deviation: 0-1

            if (stripos($textBackBottom, 'mbna.co.uk')) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            /**
             * @CardExample(accountId=3428956, cardUuid="4463e302-7de4-41c9-9b4b-6d9e6d71a2ee", groupId="formatCC2")
             */
            if (preg_match('/citi\.com\/[ce]r[ce]dit[ce]ard/i', $textBackBottom)) { // citi.com/creditcards
                $this->detectedFormat = __FUNCTION__;

                return true;
            }

            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match('/(?:Barclaycard|Barclays\s*Bank|\bCiti\s*and\s*Arc|citicards\.com|\bCiti\s*Card|MasterCard\s*Global\s*Service)/i', $textBack)) {
                $this->detectedFormat = __FUNCTION__;

                return true;
            }
        }

        return false;
    }

    protected function parsePersonName(string $string): array
    {
        $result = ['prefix' => '', 'firstname' => '', 'middlename' => '', 'lastname' => ''];
        $nameParts = preg_split('/[ ]+/', trim($string));

        if (count($nameParts) > 1) {
            $namePartFirst = mb_strtolower($nameParts[0]);
            $namePartLast = mb_strtolower($nameParts[count($nameParts) - 1]);

            foreach ($this->namePrefixes as $prefix) {
                $prefix = mb_strtolower(str_replace('.', '', $prefix));

                if ($namePartFirst === $prefix) {
                    $result['prefix'] = array_shift($nameParts);
                } elseif ($namePartLast === $prefix) {
                    $result['prefix'] = array_pop($nameParts);
                }
            }
        }

        if (count($nameParts) === 1) {
            $result['firstname'] = $nameParts[0];
        } elseif (count($nameParts) === 2) {
            $result['firstname'] = $nameParts[0];
            $result['lastname'] = $nameParts[1];
        } elseif (count($nameParts) === 3) {
            $result['firstname'] = $nameParts[0];
            $result['middlename'] = $nameParts[1];
            $result['lastname'] = $nameParts[2];
        } elseif (count($nameParts) > 3) {
            $result['firstname'] = $nameParts[0];
            $result['lastname'] = $nameParts[count($nameParts) - 1];
        }

        return $result;
    }

    protected function normalizeText(string $string): string
    {
        return str_replace(['し'], ['L'], $string);
    }

    private function parseFormat_3(string $textFront): array
    {
        // example accounts: 4918868,4918880,4949594,4070131,4620682,4653792,4653310,4620689,4169025,4132132

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        if (preg_match('/(?i)AAdvantage account(?-i)\n+[# ]*(?<number>[A-Z\d]{7})$/m', $textFront, $matches)
            && preg_match('/\d/', $matches['number'])
        ) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 20, 100, 60)])
            ->setBack($rects);
    }
}
