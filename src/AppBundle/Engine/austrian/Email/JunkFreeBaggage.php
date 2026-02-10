<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkFreeBaggage extends \TAccountChecker
{
    public $mailFiles = "";
    private static $detectors = [
        'de' => ["auf Ihrem morgigen Flug laden wir Sie ein, Ihr Handgepäck kostenlos am Check-in Schalter aufzugeben.", "Ihre Vorteile: Sie erreichen Ihr Gate entspannter und haben am Flug mehr Platz."],
        'en' => ["on your flight tomorrow, we invite you to check in your hand baggage at the check-in counter at no charge.", "Your benefits: You'll enjoy greater comfort on your way to your gate and additional room on your flight."],
    ];
    private static $dictionary = [
        'de' => [
            'Dear customer'         => ['Lieber Fluggast'],
            'Have a pleasant trip!' => ['Have a pleasant trip!'],
        ],
        'en' => [
            'Dear customer'         => ['Dear customer'],
            'Have a pleasant trip!' => ['Have a pleasant trip!'],
        ],
    ];
    private $lang;
    private $reFrom = 'austrian@smile.austrian.com';
    private $reSubject = [
        'Your flight',
    ];

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, 'austrian.com') === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('JunkFreeBaggage');
        $this->parseEmail($email);

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Dear customer"], $words["Have a pleasant trip!"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Dear customer'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Have a pleasant trip!'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        if (!self::detectBody()) {
            return false;
        }

        $email->setIsJunk(true);

        return true;
    }
}
