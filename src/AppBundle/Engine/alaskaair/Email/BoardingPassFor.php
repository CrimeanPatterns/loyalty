<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassFor extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-30030455.eml, alaskaair/it-30320220.eml, alaskaair/it-31006018.eml, alaskaair/it-31178428.eml, alaskaair/it-31181198.eml";

    public $reFrom = ["@alaskaair.com"];
    public $reBody = [
        'en'  => ['Boarding pass', 'This is an auto-generated email'],
        'en2' => ['Your boarding passes', 'Passengers'],
    ];
    public $reSubject = [
        'Alaska Airlines boarding pass for',
        'Alaska Airlines boarding passes for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = 'Alaska Airlines';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'This is an auto-generated email')]")->length > 0) {
            $type = '1';

            if (!$this->parseEmail_1($email)) {
                return null;
            }
        } else {
            $type = '1';

            if (!$this->parseEmail_2($email)) {
                return null;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Alaska')]")->length > 0) {
            return $this->assignLang();
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        $formats = 2;
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail_1(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code'))}]",
                null,
                false, "/{$this->opt($this->t('Confirmation code'))}[ :]+([A-Z\d]{5,6})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Boarding pass'))}]/following::text()[normalize-space()!=''][1]"));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Boarding pass'))}]/following::text()[normalize-space()!=''][3][{$this->starts($this->t('Confirmation code'))}]")->length > 0) {
            $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Boarding pass'))}]/following::text()[normalize-space()!=''][2]",
                null, false, "/.*?\s*([\*A-Z\d\-]{5,})$/");

            if (!empty($node)) {
                if (preg_match("/^[\*]{4,}[A-Z\d\-]+$/", $node) > 0) {
                    $masked = true;
                } else {
                    $masked = false;
                }
                $r->program()
                    ->account($node, $masked);
            }
        }

        $xpath = "//text()[{$this->eq($this->t('Boarding at'))}]/ancestor::table[{$this->contains($this->t('Seat'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root);
            //DFW - PDX 5/27/2016
            if (preg_match("/^([A-Z]{3})\s*\-\s*([A-Z]{3})\s*(\d+\/\d+\/\d{4})$/", $node, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->noDate()
                    ->day(strtotime($m[3]));
                $s->arrival()
                    ->code($m[2])
                    ->noDate();
            }
            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][2]", $root);

            if (preg_match("/^(.+)\s+(\d+)$/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][3]", $root);
            $s->extra()->bookingCode($this->re("/^([A-Z]{1,2})\s+(?:(?i)Class)$/", $node));
            $s->extra()->seat($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space()!=''][1]",
                $root, false, "/^\d+[A-z]$/"));

//        $src = $this->http->FindSingleNode("//img[@alt='Boarding pass barcode 0']/@src"); cid:.....
        }

        return true;
    }

    private function parseEmail_2(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1]"));

        $acc = $this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/ancestor::td[1]/descendant::text()[{$this->starts($this->t('member'))}]",
            null, "/{$this->opt($this->t('member'))}\s*([\*A-Z\d\-]{5,})/");

        if (count($acc) > 0) {
            if (!empty($acc[0])) {
                if (preg_match("/^[\*]{4,}[A-Z\d\-]+$/", $acc[0]) > 0) {
                    $masked = true;
                } else {
                    $masked = false;
                }
                $r->program()
                    ->accounts($acc, $masked);
            }
        }

        $dayFlight = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code'))}]/following::text()[normalize-space()!=''][2]"));
        $xpath = "//text()[normalize-space()='Boarding']/ancestor::tr[1][contains(.,'Gate')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root);
            //DFW - PDX 5/27/2016
            if (preg_match("/^([A-Z]{3})\s*.\s*([A-Z]{3})$/u", $node, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->noDate()
                    ->day($dayFlight);
                $s->arrival()
                    ->code($m[2])
                    ->noDate();
            }
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("/^(.+)\s+(\d+).?$/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (!empty($dep = $s->getDepCode()) && !empty($arr = $s->getArrCode())) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/ancestor::td[1]/descendant::text()[{$this->starts($dep)} and {$this->contains($arr)}]/following::text()[normalize-space()!=''][1]",
                    null, "/^\d{1,3}[A-z]$/"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

//        $src = $this->http->FindSingleNode("//img[@alt='Boarding pass barcode 0']/@src"); cid:.....
//        $src = $this->http->FindSingleNode("//img[@alt='Boarding pass barcode 1']/@src"); cid:.....
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
