<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Upgrade extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-191763906.eml, eurobonus/it-191987155.eml";

    public $detectFrom = "no-reply@flysas.com";
    public $detectSubject = [
        'SAS Upgrade - ',
    ];

    public $detectBody = [
        'en' => [
            'We have received your bid(s) on an upgrade for SAS booking',
            'You have won a bid on an upgrade for SAS booking',
            'Unfortunately, we could not process your bid for SAS flight',
        ],
        'da' => [
            'Du har vundet et bud på en opgradering af din SAS-booking',
        ],
        'sv' => [
            'På begäran, har vi nu återkallat ditt/dina bud på uppgradering av din SAS-bokning',
        ],
        'no' => [
            'Som forespurt, har vi kansellert ditt/dine bud på en oppgradering for SAS bestilling',
            'Vi har mottatt ditt/dine bud på en oppgradering for SAS bestilling',
            'Du har vunnet et bud på en oppgradering for SAS bestilling',
            'Dessverre har noen gitt et høyere bud enn du på en oppgradering for SAS bestilling',
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure Date'  => 'Date',
            'for SAS booking' => 'for SAS booking',
            //            'Flight' => '',
            //            'Hi ' => '',
        ],
        'da' => [
            'Departure Date'  => 'DATO',
            'for SAS booking' => 'din SAS-booking',
            'Flight'          => ['FLY', 'FLIGHT'],
            'Hi '             => 'Hej ',
        ],
        'sv' => [
            'Departure Date'  => 'DATUM',
            'for SAS booking' => 'din SAS-bokning',
            'Flight'          => 'FLIGHT',
            'Hi '             => 'Hej ',
        ],
        'no' => [
            'Departure Date'  => 'DATO',
            'for SAS booking' => 'for SAS bestilling',
            'Flight'          => 'FLIGHT',
            'Hi '             => 'Hej ',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.flysas.com')]")) {
            foreach ($this->detectBody as $lang => $detectBody) {
                if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $detectSubject) {
                if (stripos($headers["subject"], $detectSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): bool
    {
        $r = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('for SAS booking'))}]/following::text()[normalize-space()!=''][1]",
            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        $r->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
                null, true, "/^\s*{$this->opt($this->t('Hi '))}([[:alpha:]\W]+),$/"))
        ;

        $xpath = "//tr[*[4][{$this->contains($this->t('Departure Date'))}] and *[1][{$this->contains($this->t('Flight'))}]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()][not(*[{$this->eq($this->t('Flight'))}])]";
        $roots = $this->http->XPath->query($xpath);
        $columns = [
            'flight'    => 1,
            'departure' => 2,
            'arrival'   => 3,
            'date'      => 4,
        ];

        $this->logger->debug('Segments root: ' . $xpath);

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $airline = $this->http->FindSingleNode('*[' . $columns['flight'] . ']', $root);

            if (preg_match('/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d{1,5})\s*$/', $airline, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->departure()
                ->code($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/.+\s+([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/(.+?)\s+[A-Z]{3}\s*$/"))
                ->noDate()
                ->day($this->normalizeDate($this->http->FindSingleNode('*[' . $columns['date'] . ']', $root)))
            ;

            $s->arrival()
                ->code($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/.+\s+([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/(.+?)\s+[A-Z]{3}\s*$/"))
                ->noDate()
            ;
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Departure Date'], $words['for SAS booking'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departure Date'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['for SAS booking'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //            "#^\s*(\d{2})/([^\W\d]+)/(\d{4})\s*$#", // 14/Oct/2019
            //            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#", // 04/05/2019
        ];

        $out = [
            //            '$1 $2 $3',
            //            '$2.$1.$3',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
