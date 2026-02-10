<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Upgrade extends \TAccountChecker
{
    public $mailFiles = "airindia/it-171032698.eml";

    public $detectFrom = "upgrades@airindia.in";
    public $detectSubject = [
        // en
        'Get Upgraded on your Air India flight!',
    ];

    public $detectBody = [
        'en' => [
            'One or more of your upcoming flights is eligible for an upgrade to',
            'Your upcoming flight is eligible for an upgrade',
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departure Date' => 'Departure Date',
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
        if ($this->http->XPath->query("//img[contains(@src,'.airindia.in')] | //a[contains(@href,'.airindia.in')] | //img[contains(@alt,'Air India')]")) {
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

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()!=''][1]",
            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        $r->general()
            ->confirmation($conf);

        $xpath = "//tr[*[4][{$this->contains($this->t('Departure Date'))}] and *[1][{$this->contains($this->t('Flight'))}]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()]";
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
                ->code($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->noDate()
                ->day($this->normalizeDate($this->http->FindSingleNode('*[' . $columns['date'] . ']', $root)))
            ;

            $s->arrival()
                ->code($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/(.+?)\s*\([A-Z]{3}\)\s*$/"))
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
            if (isset($words['Departure Date'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departure Date'])}]")->length > 0) {
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
