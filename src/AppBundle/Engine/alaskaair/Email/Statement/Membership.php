<?php

namespace AwardWallet\Engine\alaskaair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "alaskaair/statements/it-61473477.eml, alaskaair/statements/it-61502731.eml, alaskaair/statements/it-62935117.eml, alaskaair/statements/it-68625521.eml, alaskaair/statements/it-75668061.eml, alaskaair/statements/it-76499509.eml";
    private $lang = 'en';
    private $reFrom = ['mileage.plan@ifly.alaskaair.com'];
    private $reProvider = ['Mileage Plan'];
    private $reBody = [
        'en' => [
            [
                'Hello,',
                'Sign in',
            ],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Your Mileage Plan™ number:' => ['Your Mileage Plan™ number:', 'Mileage Plan™ Member:'],
            'Hello,' => ['Hello,', 'Hi,'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        $st = $email->add()->statement();

        $st->setMembership(true);
        $st->setNoBalance(true);

        $number = $this->http->FindSingleNode("//td[{$this->starts($this->t('Your Mileage Plan™ number:'))} and not(.//td)]",
            null, true, "/number:\s*(\d{6,})\s*$/");

        if (!empty($number)) {
            $st->setLogin($number);
            $st->setNumber($number);
        }
        if (empty($number)) {
            $number = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Your Mileage Plan™ number:'))}])[1]",
                null, true, "/:\s*x{3,}(\d{4,})\s*$/");
            if (!empty($number)) {
                $st->setLogin($number)->masked();
                $st->setNumber($number)->masked();
            }
        }


        $str = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello,'))}]/ancestor::td[1][{$this->contains($this->t('|'))}][1]");
        // Hello, Shane | Mileage Plan Member | Sign in
        if (preg_match('/Hello, ([A-Z][a-z\-]+) \| (\w+[\w ]*) \| .+$/', $str, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->addProperty('Status', preg_replace("/^\s*Mileage Plan\s+/", '', $m[2]));
        }

        // Candace Blust Sign in
        // xxxx7074 | Member
        $str = $this->http->FindSingleNode("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/following-sibling::tr[contains(.,'|')][count(.//text()[normalize-space()]) = 1]");

        if (preg_match('/^\s*x{3,}(\d{4}) +\| +([\w ]+)$/', $str, $m)) {
            $st->addProperty('Name', $this->http->FindSingleNode("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/descendant::text()[normalize-space()][1]"));
            $st->addProperty('Status', $m[2]);

            if (empty($st->getLogin())) {
                $st->setLogin($m[1])->masked();
                $st->setNumber($m[1])->masked();
            }
        }
        // Rachel Papp Sign in
        // xxxx3226
        $str = $this->http->FindSingleNode("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/following-sibling::tr[contains(.,'xxxx')][count(.//text()[normalize-space()]) = 1]");

        if (preg_match('/^\s*x{4}(\d{4})\s*$/', $str, $m)) {
            $st->addProperty('Name', $this->http->FindSingleNode("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/descendant::text()[normalize-space()][1]"));

            if (empty($st->getLogin())) {
                $st->setLogin($m[1])->masked();
                $st->setNumber($m[1])->masked();
            }
        }

        if (empty($st->getProperties()['Name'])) {
            $name = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Hello,'))}])[1]", null, true, "/{$this->opt($this->t('Hello,'))} ([A-Z][a-z\-]+( [A-Z][a-z\-]+){0,3})\s*$/");

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->arrikey($parser->getCleanFrom(), $this->reFrom) === false) {
            return false;
        }

        if (($this->arrikey($parser->getSubject(), ['Statement']) !== false
            || ($this->http->XPath->query("//a[" . $this->eq(['CONTACT US', 'Contact']) . "]")->length > 0 && $this->http->XPath->query("//*[contains(., 'Statement')][following::a[" . $this->eq(['CONTACT US', 'Contact']) . "]]")->length > 0)
            || ($this->http->XPath->query("//a[" . $this->eq(['CONTACT US', 'Contact']) . "]")->length == 0 && $this->http->XPath->query("//*[contains(., 'Statement')]")->length > 0)
            ) && $this->arrikey($parser->getSubject(), ['statement credit offer']) === false
        ) {
            $this->logger->debug("go to alaskaair/YourStatement");

            return false;
        }

        foreach ($this->reBody as $lang => $value) {
            foreach ($value as $val) {
                if ($this->http->XPath->query("//a[contains(@href, 'ifly.alaskaair.com') and .//img]/ancestor::tr[1][not(normalize-space())]/following-sibling::tr[normalize-space()][1]"
                        . "[{$this->starts($val[0])} and {$this->contains($val[0])} and count(*[contains(.,'|') = 2])]")->length > 0) {
                    // in header
                    // Hello, Shane  |  Mileage Plan Member  |  Sign in
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        if (
               $this->http->XPath->query("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/following-sibling::tr[contains(.,'|')][count(.//text()[normalize-space()]) = 1]")->length > 0
            || $this->http->XPath->query("//text()[normalize-space() = 'Sign in']/ancestor::tr[1][count(.//text()[normalize-space()]) = 2 and ./descendant::text()[normalize-space()][2][normalize-space() = 'Sign in']]/following-sibling::tr[contains(.,'xxxx')][count(.//text()[normalize-space()]) = 1]")->length > 0
        ) {
            // in header
            // Candace Blust Sign in
            // xxxx7074 | Member

            return true;
        }

        if ($this->http->XPath->query("//text()[" . $this->contains(['You may still receive transactional and trip-related emails from Alaska Airlines', 'You may still receive transactional messages from Alaska Airlines.']) . "]")->length > 0) {
            //in footer
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function arrkey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (strpos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (strpos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
