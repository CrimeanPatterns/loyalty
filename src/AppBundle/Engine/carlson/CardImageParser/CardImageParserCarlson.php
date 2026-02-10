<?php

namespace AwardWallet\Engine\carlson\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserCarlson implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    /** @var ImageRecognitionResult */
    protected $frontSide;

    /** @var ImageRecognitionResult */
    protected $backSide;

    protected $namePrefixes = ['Miss', 'Mr.', 'Mrs.', 'Ms.', 'Dr.'];

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $this->ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $this->ccDetectionResult;
        }

        if ($this->detectCC()) {
            $this->hideCCNumber($this->ccDetectionResult);
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=4306436, cardUuid="3eb8a6f5-b985-4c23-921b-e7fdba5b44a3", groupId="format1")
         * @CardExample(accountId=4164315, cardUuid="4722457e-1359-45b4-919b-f8e06404e8ee", groupId="format1")
         * @CardExample(accountId=4309701, cardUuid="fab91e49-cdcd-49a3-b739-adb5e3a44b85", groupId="format1")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText(4) : ''; // deviation: 0-6
        $textBack = $this->backSide ? $this->backSide->getText(4) : ''; // deviation: 0-6

        if (preg_match('/\bACCOUNT NUMBER\b/i', $textFront . "\n" . $textBack)) { // ACCOUNT NUMBER
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
            'Login', // Number (Required)
            'Login2', // Last Name (Optional)
        ];
    }

    protected function detectCC(): bool
    {
        // example accounts: 4133825,1617703

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        /**
         * @CardExample(accountId=4133825, cardUuid="a1eaa5de-0651-47ee-8964-5254f41a797f", groupId="formatCC1")
         */
        $patterns = [
            'image' => '/(?:Visa|US\s*Bank)/i',
            'text'  => '/(?:\bVISA\b|U[,.]S[,.]\s*Bank\s*National)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
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
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(30, 70, 40, 25)])
            ->setBack($rects);
    }

    protected function parsePersonName($string = ''): array
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

    private function parseFormat_1()
    {
        // example accounts: 4306436,4309701,4164315

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFrontBottom = $this->frontSide ? $this->frontSide->getDOM(4)->getTextRectangle(0, 0, 60, 0) : ''; // deviation: 0-6
        $textBackBottom = $this->backSide ? $this->backSide->getDOM(4)->getTextRectangle(0, 0, 60, 0) : ''; // deviation: 0-6

        $textFullBottom = $textFrontBottom . "\n" . $textBackBottom;

        if (preg_match('/^(?<number>\d{16})\b/m', $textFullBottom, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }

    private function parseFormat_999()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        $validNamePattern = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]'; // Mr. Hao-Li Huang

        // stop words
        $yourAccountSpaces = implode(' *', str_split('YourAccount')); // YOUR ACCOUNT
        $membershipNumberSpaces = implode(' *', str_split('MembershipNumber')); // MEMBERSHIP NUMBER
        $goldpointsSpaces = implode(' *', str_split('goldpoints'));
        $carlsonLogoSpaces = implode(' *', str_split('Carlson'));
        $rewardsLogoSpaces = implode('\s*', str_split('REWARDS'));
        $statusValidSpaces = implode(' *', str_split('StatusValid')); // STATUS VALID

        // BACK

        $number = null;

        if ($this->backSide) {
            $textBack = $this->backSide->getText();
            $noSpaces = preg_replace("# +#", '', $textBack);

            if (preg_match('/^\s*(\d{16})\s*$/m', $noSpaces, $m)) {
                $result['Login'] = $m[1];
                $number = $m[1];
            }

            if (
                isset($number)
                && preg_match("#^\s*(.+)\n *?" . substr($number, 0, 4) . "#m", $textBack, $m)
                && preg_match("/^\s*{$validNamePattern}\s*$/u", $m[1])
                && !preg_match("/^{$carlsonLogoSpaces}[^\w]*x*$/i", $m[1])
                && !preg_match("/{$yourAccountSpaces}/i", $m[1])
                && !preg_match("/{$membershipNumberSpaces}/i", $m[1])
                && !preg_match("/{$statusValidSpaces}/i", $m[1])
                && !preg_match("/{$goldpointsSpaces}/i", $m[1])
            ) {
                $result['Login2'] = $m[1];
            }

            if (isset($result['Login2']) && strlen($result['Login2']) < 5) {
                unset($result['Login2']);
            }
        }

        // FRONT

        if ($this->frontSide) {
            $textFront = $this->frontSide->getText();

            $noSpaces = preg_replace("# +#", '', $textFront);

            $in = ['O', 'o', 'l', 'b', 's', ')'];
            $out = ['0', '0', '1', '6', '5', '1'];
            $noSpaces = str_replace($in, $out, $noSpaces);

            if (preg_match('/^\s*(\d{16})\s*$/m', $noSpaces, $m)) {
                $result['Login'] = $m[1];
                $number = $m[1];
            }

            if (isset($number) && preg_match("#^\s*(.+)\n *?" . substr($number, 0, 4) . "#m", $textFront, $m) && !preg_match("/{$rewardsLogoSpaces}/i", $m[1])) {
                $login = $m[1];
            } elseif (preg_match("#^{$number}\s*(?:HG)?\s*(.+)\n *?#m", $textFront, $m)) {
                $login = $m[1];
            }

            if (
                isset($login)
                && preg_match("/^\s*{$validNamePattern}\s*$/u", $login)
                && !preg_match("/{$yourAccountSpaces}/i", $login)
                && !preg_match("/{$membershipNumberSpaces}/i", $login)
                && !preg_match("/{$statusValidSpaces}/i", $login)
                && !preg_match("/{$goldpointsSpaces}/i", $login)
                && !preg_match("/^{$carlsonLogoSpaces}[^\w]*x*$/i", $login)
                && !preg_match("/{$rewardsLogoSpaces}/i", $login)
            ) {
                $result['Login2'] = $login;
            }

            if (isset($result['Login2']) && strlen($result['Login2']) < 5) {
                unset($result['Login2']);
            }
        }

        if (!empty($result['Login2'])) {
            $names = $this->parsePersonName($result['Login2']);
            $result['Login2'] = $names['lastname'];
        }

        return $result;
    }
}
