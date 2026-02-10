<?php

namespace AwardWallet\Engine\extrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketItinerary extends \TAccountChecker
{
    public $mailFiles = "extrip/it-23262188.eml";

    public $reFrom = ["@exploretrip.com"];
    public $reBody = [
        'en' => ['Please find your E-ticket confirmation below', 'E-Ticket Itinerary'],
    ];
    public $reSubject = [
        '#E-Ticket Confirmation for PNR[ :]+[A-Z\d]{5,} from Explore Trip#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'www.exploretrip.com')] | //a[contains(@href,'www.exploretrip.com')] | //text()[contains(normalize-space(.),'Explore Trip')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $email->ota()
            ->code('extrip')
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Explore Trip #'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^[A-Z\d]+$#"),
                trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Explore Trip #'))}]") . " :"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Explore Trip Customer Support 24/7'))}][1]/ancestor::td[1]/following-sibling::td[1]"),
                $this->t('Explore Trip Customer Support 24/7'));

        $f = $email->add()->flight();
        $f->issued()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('E-Ticket No'))}]/ancestor::tr[1][{$this->contains($this->t('Travellers'))}]/ancestor::table[1]/following-sibling::table[1]"))
            ->tickets($this->http->FindNodes("//text()[{$this->eq($this->t('E-Ticket No'))}]/ancestor::tr[1][{$this->contains($this->t('Travellers'))}]/following-sibling::tr[count(./td)=3]/td[3]",
                null, "#^[\d\-]+$#"), false);

        $f->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('E-Ticket No'))}]/ancestor::tr[1][{$this->contains($this->t('Travellers'))}]/following-sibling::tr[count(./td)=3]/td[1]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^[A-Z\d]+$#"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR:'))}]") . " :"));

        $f->price()
            ->currency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Base Fare'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                null, false, "#^([A-Z]{3}) *\d#"))
            ->cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Base Fare'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                null, false, "#^[A-Z]{3} *([\d\.]+)$#"))
            ->tax($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes & Fees'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                null, false, "#^[A-Z]{3} *([\d\.]+)$#"))
            ->total($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'))}][1]/ancestor::td[1]/following-sibling::td[1]",
                null, false, "#^[A-Z]{3} *([\d\.]+)$#"));
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[2][{$ruleTime}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug($xpath);
        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root);

            if (preg_match("#^([A-Z]{3})\s*(\d+:\d+)$#", $node, $m)) {
                $s->departure()
                    ->code($m[1]);
                $depTime = $m[2];
            }
            $node = $this->http->FindSingleNode("./descendant::tr[1]/td[3]", $root);

            if (preg_match("#^(\d+:\d+)\s*([A-Z]{3})$#", $node, $m)) {
                $s->arrival()
                    ->code($m[2]);
                $arrTime = $m[1];
            }
            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space()!=''][1]",
                    $root))
                ->cabin($this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space()!=''][2]",
                    $root));
            $s->departure()
                ->date(strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[1]/td[1]",
                    $root))));
            $s->arrival()
                ->date(strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[1]/td[2]",
                    $root))));

            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])[\- ]+(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $s->airline()->confirmation($this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]",
                $root, false, "#^{$this->opt($this->t('AIRPNR'))}[ :]+([A-Z\d]{5,})$#"));
        }

        return true;
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
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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
