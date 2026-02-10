<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InfoFlightBooking extends \TAccountChecker
{
    public $mailFiles = "edreams/it-39037474.eml, edreams/it-39141925.eml, edreams/it-39150804.eml, edreams/it-60266927.eml";

    public $reFrom = ["@e.edreams.com"];
    public $reBody = [
        'en'  => ['All set to go?', 'How can we help make your trip perfect?'],
        'en2' => ['Got everything you need?', 'Let us help you make your trip amazing'],
    ];
    public $reSubject = [
        'Information on your flight booking #',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Hi ' => ['Hi ', 'Hey '],
        ],
    ];
    private $keywordProv = 'eDreams';
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

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
        if ($this->http->XPath->query("//img[@alt='eDreams' or contains(@src,'.odigeo.com')] | //a[contains(@href,'.odigeo.com')]")->length > 0) {
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
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

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->flight();
        $r->general()->noConfirmation();

        if (preg_match("#Information on your flight booking \#\s*(\d{6,})#", $this->subject, $m)) {
            $r->ota()->confirmation($m[1]);
        }
        $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Hi "))}]", null, false,
            "/{$this->opt($this->t('Hi '))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*,/u");
        $r->general()->traveller($pax, !empty(strpos($pax, ' ')));

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
        $xpath = "descendant::text()[{$xpathTime}]/ancestor::tr[ count(*)=3 and not(preceding-sibling::tr[normalize-space()]) and following-sibling::tr[normalize-space()] ][1][ *[string-length()>10] ]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            // departure
            $depCode = $this->http->FindSingleNode("./preceding::tr[count(./descendant::text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!=''][1]",
                $root);
//            $depName = $this->http->FindSingleNode("./following::tr[count(./descendant::text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!=''][1]",$root);
            $depDate = $this->normalizeDate($this->http->FindSingleNode('*[1]', $root));

            // arrival
            $arrCode = $this->http->FindSingleNode("./preceding::tr[count(./descendant::text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!=''][2]",
                $root);
//            $arrCName = $this->http->FindSingleNode("./following::tr[count(./descendant::text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!=''][2]", $root);
            $arrDateValue = $this->http->FindSingleNode('*[3]', $root);

            // don't use depName and arrName - sometimes it's wrong - it-39037474.eml
            // flight #1 of trip
            $s = $r->addSegment();
            $s->departure()
                ->code($depCode)
                ->date($depDate);
            $s->arrival()
                ->code($arrCode)
                ->noDate();
            $s->airline()
                ->noName()
                ->noNumber();
            // flight #2 of trip
            $s = $r->addSegment();
            $s->departure()
                ->code($arrCode)
                ->noDate();
            $s->arrival()->code($depCode);

            if ($arrDateValue === '' || $arrDateValue === ' ') {
                $s->arrival()->noDate();
            } else {
                $s->arrival()->date($this->normalizeDate($arrDateValue));
            }
            $s->airline()
                ->noName()
                ->noNumber();
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //11:25 - Sat 27/04/19
            '#^(\d+:\d+)\s+\-\s+([\w\-]+)\s+(\d+)\/(\d+)\/(\d{2})$#u',
        ];
        $out = [
            '20$5-$4-$3 $1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
