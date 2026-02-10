<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "airasia/it-179425445.eml";
    public $subjects = [
        "Here's your AirAsia India Boarding Pass for",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@transaction.airasia.co.in') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AirAsia')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your boarding pass is successfully generated'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Status'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]transaction\.airasia\.co\.in$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $pnr = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PNR')]", null, true, "/{$this->opt($this->t('PNR'))}[\s\:]*([A-Z\d]+)/");

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hey')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hey'))}\s*(.+)/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PNR')]/ancestor::tr[1]/following::tr[1]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");
        }
        $f->general()
            ->confirmation($pnr)
            ->traveller($traveller);

        $xpathPart = "//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::th[1]/ancestor::tr[1]";

        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Flight No']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^([A-Z\d]{2})/"))
            ->number($this->http->FindSingleNode("//text()[normalize-space()='Flight No']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

        $s->departure()
            ->code($this->http->FindSingleNode("//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::th[1]/ancestor::tr[1]/descendant::th[1]/descendant::text()[normalize-space()][2]", null, true, "/^([A-Z]{3})$/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][1]")));

        $depTerminal = $this->http->FindSingleNode("//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/preceding::tr[1]/descendant::div[1]", null, true, "/\s+T\-(\S+)$/");

        if (!empty($depTerminal)) {
            $s->departure()
                ->terminal($depTerminal);
        }

        $s->arrival()
            ->code($this->http->FindSingleNode("//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::th[1]/ancestor::tr[1]/descendant::th[3]/descendant::text()[normalize-space()][2]", null, true, "/^([A-Z]{3})$/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Departure Time']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][1]")));

        $arrTerminal = $this->http->FindSingleNode("//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/preceding::tr[1]/descendant::div[3]", null, true, "/\s+T\-?(\S{1,3})$/");

        if (!empty($arrTerminal)) {
            $s->arrival()
                ->terminal($arrTerminal);
        }

        $s->extra()
            ->duration($this->http->FindSingleNode("//text()[normalize-space()='Boarding Time']/ancestor::tr[1]/preceding::text()[contains(normalize-space(), ':')][1]/ancestor::th[1]/ancestor::tr[1]/descendant::th[2]"));

        $seat = $this->http->FindSingleNode("//text()[normalize-space()='Seat No']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^(\d+[A-Z])$/");

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Your boarding pass is successfully generated..']")->length > 0) {
            $bp = $email->add()->bpass();

            $bp->setDepCode($s->getDepCode())
                ->setDepDate($s->getDepDate())
                ->setFlightNumber($s->getFlightNumber())
                ->setRecordLocator($pnr)
                ->setTraveller($f->getTravellers()[0][0])
                ->setUrl($this->http->FindSingleNode("//text()[normalize-space()='Manage']/ancestor::a/@href"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\d+)\.(\d+)\s*\D+\,\s*(\d+)\s*(\w+)\s*(\d{2})$#u", //18.05 hrs, 29 Jul 22
        ];
        $out = [
            "$3 $4 20$5, $1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
