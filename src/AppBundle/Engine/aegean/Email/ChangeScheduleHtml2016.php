<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ChangeScheduleHtml2016 extends \TAccountChecker
{
    public $mailFiles = "aegean/it-44985158.eml, aegean/it-4931723.eml";

    public $reFrom = ["aegeanair.com"];
    public $reBody = [
        'en' => ['Modified Booking Confirmation E-mail'],
        'el' => ['E-mail επιβεβαίωσης αλλαγής κράτησης'],
    ];
    public $reSubject = [
        'AEGEAN AIRLINES S.A. – Schedule change confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flight' => 'Flight',
            'From'   => 'From',
        ],
        'el' => [
            'Flight'            => 'Πτήση',
            'From'              => 'Από',
            'Booking reference' => 'Κωδικός κράτησης',
            'Passengers'        => 'Επιβάτες',
            'stop(s)'           => 'στάση',
        ],
    ];
    private $keywordProv = 'Aegean';

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
        if ($this->http->XPath->query("//img[contains(@src,'.aegeanair.com')] | //a[contains(@href,'.aegeanair.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/ancestor::td[1]", null, false, "#:\s*([A-Z\d]{5,6})$#"));

        $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('E-ticket:'))}]/ancestor::tr[2]");

        foreach ($passengers as $value) {
            if (preg_match("/(.+?)\s*{$this->opt($this->t('E-ticket:'))}\s*([\d-]+)/", $value, $matches)) {
                $r->general()->traveller($matches[1], true);
                $r->issued()->ticket($matches[2], false);
            }
        }
        $xpath = "//img[contains(@src, '/images/eagles.gif')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[1][{$this->starts($this->t('From'))}]/following-sibling::tr[1]";
            $roots = $this->http->XPath->query($xpath);
        }
        $this->logger->debug('[XPATH] :' . $xpath);

        foreach ($roots as $element) {
            $date = $this->http->FindSingleNode("ancestor::tr[1]/preceding-sibling::tr[1]", $element);
            $flightNumber = $this->http->FindSingleNode('td[5]', $element);

            if (preg_match("/{$this->opt($this->t('From'))}.+?-\s*(.+)/", $date, $matchesDate) && preg_match('/([A-Z\d]{2})\s*(\d+)/', $flightNumber, $matches)) {
                $s = $r->addSegment();
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
                $s->departure()
                    ->noCode()
                    ->name($this->http->FindSingleNode('td[2]', $element));
                $s->arrival()
                    ->noCode()
                    ->name($this->http->FindSingleNode('td[4]', $element));
                $date = $this->normalizeDate($matchesDate[1]);

                if (!empty($date) && ($depTime = $this->http->FindSingleNode('td[1]', $element))
                    && ($arrTime = $this->http->FindSingleNode('td[3]', $element))
                ) {
                    $s->departure()->date(strtotime($depTime, $date));
                    $s->arrival()->date(strtotime($arrTime, $date));

                    if ($s->getDepDate() && $s->getArrDate()
                        && $s->getDepDate() > $s->getArrDate()
                    ) {
                        $s->arrival()->date(strtotime("+ 1 day", $s->getArrDate()));
                    }
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //21Oct16
            '#^(\d+)\s*(\D+?)\s*(\d{2})$#u',
        ];
        $out = [
            '$1 $2 20$3',
        ];
        $str = preg_replace($in, $out, $date);
        $str = strtotime($this->dateStringToEnglish($str));

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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight'], $words['From'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['From'])}]")->length > 0
                ) {
                    $this->lang = $lang;

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

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
