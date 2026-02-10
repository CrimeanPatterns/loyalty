<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-58191267.eml";

    private $lang = '';
    private $reFrom = ['aeroplan.com'];
    private $reProvider = ['Aeroplan'];
    private $detectLang = [
        'en' => [
            'Flight reward refund confirmation',
        ],
    ];
    private $reSubject = [
        'Flight Reward Refund Confirmation',
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $f = $email->add()->flight();
        $f->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]/ancestor::td[1]/following-sibling::td[1]"),
            $this->t('Booking Reference')
        );
        $nodes = $this->http->XPath->query("//tr[{$this->contains($this->t('Passenger(s) Name(s)'))}]/following::tr[1]//table//tr[count(./td)=2]");

        foreach ($nodes as $n) {
            if ($p = $this->http->FindSingleNode('./td[1]', $n)) {
                $f->general()->traveller($p);
            }

            if ($an = $this->http->FindSingleNode('./td[2]', $n, false, '/^\s*([\w\d\-]{5,})\s*$/')) {
                $f->program()->account($an, false);
            }
        }
        $nodes = $this->http->XPath->query("//tr[{$this->contains($this->t('Cancelled Itinerary'))}]/following::tr[1]//table//tr[count(./td)=4]");

        foreach ($nodes as $n) {
            $s = $f->addSegment();
            $s->departure()->name($this->http->FindSingleNode('./td[1]', $n));
            $s->arrival()->name($this->http->FindSingleNode('./td[3]', $n));
            $s->departure()->date2($this->http->FindSingleNode('./td[4]', $n));
        }
        $f->general()->status($this->t('Refund'));
        $f->general()->cancelled();

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

        if ($this->assignLang() && $this->http->XPath->query("//td[{$this->eq($this->t('Cancelled Itinerary'), 'normalize-space(text())')}]")->length === 1) {
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
