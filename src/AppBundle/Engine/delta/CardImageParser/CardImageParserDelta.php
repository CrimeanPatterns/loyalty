<?php

namespace AwardWallet\Engine\delta\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserDelta implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    // https://www.delta.com/profile/enrolllanding.action
    protected $namePrefixes = [
        'Mr', 'Mrs', 'Ms', 'Mst', 'Abp', 'Dr', 'Rev', 'Adm', 'Ald', 'Amb',
        'Bp', 'Bro', 'Cdt', 'Col', 'Cp', 'Cpl', 'Fr', 'Gen', 'Gov', 'Hon',
        'Lt', 'Maj', 'Pvt', 'Rep', 'Sen', 'Sgt', 'Smn', 'Sr',
    ];

    private $statuses = [
        'Silver',
        'Gold',
        'Platinum',
        'Diamond',
    ];

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        if (!$this->frontSide) {
            $this->frontSide = $cardRecognitionResult->getFront();
        }

        if (!$this->backSide) {
            $this->backSide = $cardRecognitionResult->getBack();
        }

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=4089859, cardUuid="ee07c1b7-b222-460a-a716-a7298e99afa4", groupId="format1")
         * @CardExample(accountId=4096177, cardUuid="b1627347-5592-412c-96ab-571bb4eadcac", groupId="format1")
         * @CardExample(accountId=3837638, cardUuid="896aff7d-b79d-4e49-8261-7a0b060a35de", groupId="format1")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText(4) : ''; // deviation: 1-6
        $textBack = $this->backSide ? $this->backSide->getText(4) : ''; // deviation: 1-6

        if (preg_match('/MEMBER[ ]+SKYMILES[ ]+NUMBER/i', $textFront . "\n" . $textBack)) { // MEMBER    SKYMILES NUMBER
            if ($properties = $this->parseFormat_1()) {
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
            'Login', // Account Number
            'Login2', // Last Name
            'Status', // Membership Level
        ];
    }

    /**
     * @Detector(version="7")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
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

            $frontLogos = $domFront->findNodes('/img[@left > 50 or @top < 50]/@alt', null, '/(Express|PayPal|VISA|MasterCard)/i');
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (
                !empty($frontLogoValues[0])
                || preg_match('/(?:America|Express|PayPal|\bVISA\b|MasterCard)/i', $textFront)
            ) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->backSide) {
            $textBack = $this->backSide->getText();

            if (preg_match('/(?:American\s*Express)/i', $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    protected function parseFormat_1()
    {
        // example accounts: 4089859,4096177,3837638

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number & Last Name

        $textFront = $this->frontSide ? $this->frontSide->getText(4) : ''; // deviation: 2-6
        $textBack = $this->backSide ? $this->backSide->getText(4) : ''; // deviation: 2-6

        $textFull = $textFront . "\n" . $textBack;

        $textFullConverted = str_replace(['.', ':'], '', $textFull);

        if (preg_match('/^([A-z][-\'A-z ]*\b)[ ]+(\d{10,15})$/m', $textFullConverted, $matches)) {
            $names = $this->parsePersonName($matches[1]);
            $properties['Login2'] = $names['lastname'];
            $properties['Login'] = $matches[2];
        }

        // Status

        $textFront = $this->frontSide ? $this->frontSide->getText(4) : ''; // deviation: 1-14

        $textFrontConverted = str_replace(['.', ':'], '', $textFront);

        if (preg_match('/S[ ]*I[ ]*L[ ]*V[ ]*E[ ]*R\s+M[ ]*E[ ]*D[ ]*A[ ]*L[ ]*L[ ]*I[ ]*O[ ]*N/i', $textFrontConverted)) { // SILVER\nMEDALLION
            $properties['Status'] = 'Silver';
        } elseif (preg_match('/^[ ]*S[ ]*K[ ]*Y[ ]*M[ ]*I[ ]*L[ ]*E[ ]*S.*$\s^[ ]*M[ ]*E[ ]*M[ ]*B[ ]*E[ ]*R\b/mi', $textFrontConverted)) { // SKYMILES\nM E M B E R
            $properties['Status'] = 'Member';
        }

        return $properties;
    }

    protected function parseFormat_999()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        // FRONT

        if ($this->frontSide) {
            $cardText = $this->frontSide->getText();

            if (null !== ($number = $cardText->findPreg('/(\b\d{10}\b|skymiles\d{10}|\d{5} \d{5})/msi'))) {
                if (null !== ($login2 = $this->frontSide->getDOM(5)->findSingleNode("//div[contains(., '{$number}')]/preceding-sibling::div[not(contains(., 'PROFILE')) and not(contains(., 'Delta')) and not(contains(., 'Member Since')) and not(contains(., 'SkyMiles'))][1]", null, '/([A-Z\.\'\s\-\d]+)/i'))) {
                    $login2 = preg_replace(['/ent\s*[\.]*\s*/', '/FLIGHT PREFER/i', '/\d+/'], ['', '', ''], $login2);
                    $nameParts = explode(' ', trim($login2));
                    $lastName = trim(implode(' ', array_slice($nameParts, 1)));

                    if (!preg_match('/\w+/', $lastName)) {
                        $lastName = trim(preg_replace(['/\b[a-zA-Z]\s*\b/', '/\d+[a-z ]+\d+/'], ['', ''], $lastName));
                    }

                    if (preg_match('/(?:^[a-zA-Z-\.\']{2,}$|\w+\s+\w+)/', $lastName)) {
                        $result['Login2'] = $lastName;
                    }
                }

                if (empty($result['Login2']) && $lastName = $cardText->findPreg("/\n\s*\w+\s+([A-Z ]+)\s*\n\s*{$number}/i")) {
                    $result['Login2'] = $lastName;
                }

                $result['Login'] = str_ireplace('SKYMILES', '', $number);
            }

//            if ( null !== ($status = $cardText->findPreg('/([a-z]+)\s+medallion/ims')) ) {
//                $result['Status'] = beautifulName($status) . ' Medallion';
//            }

            if (empty($result['Status'])) {
                foreach ($this->statuses as $status) {
                    if ($cardText->findPreg("/(Medallion\s+{$status}|{$status}\s+Medallion)/i") || stripos($cardText->getText(), $status) !== false) {
                        $result['Status'] = $status . ' Medallion';
                    }
                }
            }
        }

        // BACK

        if ($this->backSide) {
            $cardText = $this->backSide->getText();

            if (empty($result['Login']) && empty($result['Login2']) && preg_match('/(\w+)\s+(\d{10})/i', $cardText, $m)) {
                $result['Login'] = $m[2];
                $result['Login2'] = $m[1];
            }
        }

        return $result;
    }

    protected function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 40)])
            ->setBack($rects);
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
}
