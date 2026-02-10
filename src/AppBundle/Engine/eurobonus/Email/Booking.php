<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-61071881.eml";

    private $lang = '';
    private $reFrom = [
        '@flysas.com',
    ];
    private $reProvider = ['flysas.com', '.sas.dk', 'SAS Customer Service'];
    private $detectLang = [
        'en' => [
            've made an adjustment to our flight schedule that impacts your SAS trip with booking reference',
            'Due to the current situation we have to inform you of a change of schedule that has unfortunately impacted your original booking',
        ],
        'da' => [
            'Vi har foretaget ændringer i vores tidsplan, som påvirker din rejse med bookingreference',
        ],
        'no' => [
            'Vi har gjort en justering i vår tidtabell som påvirker din reise med bestillingsreferanse',
        ],
    ];
    private $reSubject = [
        'Flight schedule change',
        'IMPORTANT INFORMATION ABOUT YOUR SAS BOOKING',
        // da
        'Vigtig information angående din rejse med SAS',
        // no
        'Viktig informasjon om din SAS-bestilling SAS:',
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->alert("Can't determine a language");

            return $email;
        }
        $f = $email->add()->flight();
        $f->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference'))}]", null, false,
                "/{$this->opt($this->t('Booking Reference'))}\s+(\w+)/")
        );
        $nodes = $this->http->XPath->query('//tr[contains(., "Flight/Date")]/following-sibling::tr');

        foreach ($nodes as $node) {
            $s = $f->addSegment();
            $td = join("\n", $this->http->FindNodes("./td[1]//text()", $node));
            // SK 1415/07JUL20
            if (preg_match("#([A-Z]{2})\s*(\d{2,4})\s*/\s*(\w{6,})#", $td, $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
                $date = $m[3];
            }
            // OPERATED BY CITYJET
            if (preg_match("/OPERATED BY (.+)/", $td, $m)) {
                $s->airline()->operator($m[1]);
            }

            $td = join("\n", $this->http->FindNodes("./td[2]//text()", $node));
            // Stockholm (Arlanda) - København (Kastrup)
            if (preg_match("/^(.+?) - (.+)/", $td, $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }
            $depTime = $this->http->FindSingleNode("./td[3]//text()", $node, false, '/\d+:\d+/');
            $arrTime = $this->http->FindSingleNode("./td[4]//text()", $node, false, '/\d+:\d+/');

            if (isset($date)) {
                $s->departure()->date(strtotime("{$date}, {$depTime}"));
                $s->arrival()->date(strtotime("{$date}, {$arrTime}"));

                if ($s->getDepDate() > $s->getArrDate()) {
                    $s->arrival()->date(strtotime('+1 day', $s->getArrDate()));
                }
            }
        }

        if ($this->http->XPath->query("//td[{$this->eq($this->t('Itinerary Cancellation'), 'normalize-space(text())')}]")->length == 1) {
            $f->general()->status($this->t('Cancellation'));
            $f->general()->cancelled();
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
}
