<?php

namespace AwardWallet\Engine\avinode\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "avinode/it-4.eml, avinode/it-5.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Date' => 'Date',
            'PAX'  => 'PAX',
            'ETD'  => 'ETD',
        ],
    ];

    private $detectSubject = [
        // en
        // Fw: Accepted: CU7YD2, 11 Dec 2022 KGSO >> KTTD, 45 North Flight
        'Accepted: ',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@avinode.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.avinode.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Date']) && !empty($dict['PAX']) && !empty($dict['ETD'])
                && $this->http->XPath->query("//tr[not(.//tr)][*[{$this->eq($dict['Date'])}] and *[{$this->eq($dict['PAX'])}] and *[{$this->eq($dict['ETD'])}]]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        // Flight
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t("Trip "))}]",
                null, true, "/Trip\s+([A-Z\d]{5,})\s*$/"));

        // Segments
        $node = implode(" ", $this->http->FindNodes("//*[self::td or self::th][{$this->eq($this->t('Aircraft'))}]/following::*[self::td or self::th][normalize-space()][1]//text()[normalize-space()]"));

        if (preg_match("/^\D+ (\d[\d,]+) ?USD (.+?)(?:\(|$)/", $node, $m)) {
            if (preg_match('/^(.+),\s*([A-Z\d]+)$/', $m[2], $ma)) {
                $aircraft = $ma[1];
                $regNum = $ma[2];
            } else {
                $aircraft = $m[2];
            }
            $f->price()
                ->total(str_replace(',', '', $m[1]))
                ->currency('USD');
        }

        $xpath = "//tr[not(.//tr)][*[{$this->eq($this->t('Date'))}] and *[{$this->eq($this->t('PAX'))}] and *[{$this->eq($this->t('ETD'))}]]/ancestor::table[1]//tr[not(.//tr) and not(*[{$this->eq($this->t('Date'))}])][normalize-space()]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->noName()
                ->noNumber()
            ;

            $date = $this->http->FindSingleNode("*[1]", $root);

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("*[2]", $root, true, "/,\s*([A-Z]{3})\s*$/"))
                ->date($this->normalizeDate($date . ', ' . $this->http->FindSingleNode("*[5]", $root, null, "/^(.+?)\(/")))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("*[3]", $root, true, "/,\s*([A-Z]{3})\s*$/"))
                ->date($this->normalizeDate($date . ', ' . $this->http->FindSingleNode("*[6]", $root, null, "/^(.+?)\(/")))
            ;

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("*[7]", $root));

            if (isset($aircraft)) {
                $s->extra()->aircraft($aircraft);
            }

            if (isset($regNum)) {
                $s->extra()->regNum($regNum);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
