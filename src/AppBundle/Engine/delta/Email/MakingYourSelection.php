<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class MakingYourSelection extends \TAccountChecker
{
    public $mailFiles = "delta/it-209546542.eml, delta/it-44357081.eml, delta/it-44848643.eml";

    public $reFrom = ["delta.com"];
    public $reBody = [
        'en' => [
            'We want you to have the best experience on board',
            'Choose A Premium Experience',
        ],
    ];
    public $reSubject = [
        'Thank You For Making Your Selection',
        ', Select Your Meal Before Your',
        ', Move Up Front On Your Flight To',
    ];
    public $emailDate;
    public $emailSubject;
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flight details for' => 'Flight details for',
            'Hello,'             => 'Hello,',
            'Flight #:'             => 'Flight #:',
            'Your flight on' => 'Your flight on',
        ],
    ];
    private $keywordProv = 'Delta';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $detectedByHeader = $this->detectEmailByHeaders($parser->getHeaders());
        $type = '';
        foreach (self::$dict as $lang => $words) {
            // Flight details for DL2997 on 09/04/2019  |  AUS > BOS
            if (isset($words['Flight details for'], $words['Hello,'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight details for'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Hello,'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->eq($this->t('Flight details for'))}]/following::text()[normalize-space()!=''][2][{$this->eq($this->t('on'))}]")->length > 0
                ) {
                    $this->lang = $lang;
                    $type = '1';
                    $this->parseEmail1($email);
                    break;
                }
            }
            // Flight #:          JFK → MAD        07:30 PM
            //  DL126                               August 17
            if ($detectedByHeader == true && isset($words['Flight #:'])) {
                if ($this->http->XPath->query("//tr[count(*[normalize-space()]) = 3][*[normalize-space()][1][{$this->starts($words['Flight #:'])}] and *[normalize-space()][2][contains(., '→')]]")->length > 0) {
                    $this->lang = $lang;
                    $type = '2';
                    $this->emailDate = strtotime($parser->getDate());
                    $this->emailSubject = $parser->getSubject();
                    $this->parseEmail2($email);
                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'SKYMILES') or contains(@src,'.delta.com')] | //a[contains(@href,'.delta.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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

    private function parseEmail1(Email $email)
    {
        $r = $email->add()->flight();

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SkyMiles'))}]/following::text()[normalize-space()!=''][position()<3][{$this->starts('#')}]",
            null, false, "#\#\s*(\d+)#");

        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]", null, false,
            "#{$this->opt($this->t('Hello,'))}\s*(.+)#");
        $r->general()
            ->noConfirmation()
            ->traveller($traveller, strpos($traveller, ' ') !== false);

        $s = $r->addSegment();
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight details for'))}]/following::text()[normalize-space()!=''][1]");

        if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight details for'))}]/following::text()[normalize-space()!=''][3]");
        $s->departure()
            ->noDate()
            ->day(strtotime($date));

        $s->arrival()->noDate();

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight details for'))}]/following::text()[normalize-space()!=''][4]");

        if (preg_match("#\b([A-Z]{3}) > ([A-Z]{3})$#", $node, $m)) {
            $s->departure()->code($m[1]);
            $s->arrival()->code($m[2]);
        }

        return true;
    }

    private function parseEmail2(Email $email)
    {
        $r = $email->add()->flight();

        // Program
        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight #:'))}]/preceding::text()[normalize-space()!=''][1][{$this->starts('#')}]",
            null, false, "/^\s*#\s*(\d{5,})\s*$/");

        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        // General
        $r->general()
            ->noConfirmation()
        ;
        if (preg_match("/^\s*([\w \-]+), .+/", $this->emailSubject, $m)) {
            $r->general()->traveller($m[1], false);
        }

        // Segments
        $s = $r->addSegment();

        // Airline
        $xpath = "//tr[count(*[normalize-space()]) = 3][*[normalize-space()][1][{$this->starts($this->t('Flight #:'))}] and *[normalize-space()][2][contains(., '→')]]";
        $node = $this->http->FindSingleNode($xpath. "/*[normalize-space()][1]");
        if (preg_match("#:\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        // Departure, Arrival
        $year = date('Y', $this->emailDate);

        $dateText = $this->http->FindSingleNode($xpath. "/*[normalize-space()][3]");
        if (!empty($dateText)) {
            $dateText .= ' '. $year;
            $date = strtotime($dateText);
        } else {
            $date = null;
        }

        if (!empty($date)) {
            if (abs($date - $this->emailDate) > 60 * 60 * 24 * 30 * 6) {
                if ($date - $this->emailDate > 0) {
                    $date = strtotime("-1 year", $date);
                } else {
                    $date = strtotime("+1 year", $date);
                }
            }

            if (abs($date - $this->emailDate) < 60 * 60 * 24 * 30 * 2) {
                $s->departure()
                    ->date($date);
                $s->arrival()->noDate();
            }
        }


        $node = $this->http->FindSingleNode($xpath. "/*[normalize-space()][2]");
        if (preg_match("#^\s*([A-Z]{3})\s*\W\s*([A-Z]{3})\s*$#u", $node, $m)) {
            $s->departure()->code($m[1]);
            $s->arrival()->code($m[2]);
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
}
