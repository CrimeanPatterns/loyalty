<?php

namespace AwardWallet\Engine\cheapoair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelReminder extends \TAccountChecker
{
    public $mailFiles = "cheapoair/it-39164723.eml";

    public $reFrom = ["cheapoair@cheapoair.com"];
    public $reBody = [
        'en' => ['Upcoming Flight(s)', 'Thank you for booking with CheapOair.com'],
    ];
    public $reSubject = [
        'TRAVEL REMINDER for CheapOair booking #',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'View Full Itinerary'  => 'View Full Itinerary',
            'Airline Confirmation' => ['Airlilne Confirmation', 'Airline Confirmation'],
        ],
    ];
    private $keywordProv = 'CheapOair';

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
        if ($this->http->XPath->query("//img[contains(@alt,'CheapOair') or contains(@src,'.cheapoair.com')] | //a[contains(@href,'.cheapoair.com')]")->length > 0) {
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
                if (($fromProv || (stripos($headers["subject"], $this->keywordProv) !== false))
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
        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('View Full Itinerary'))}]/following::table[1]/descendant::td[normalize-space()!='' and count(.//td)=0]",
            null, "#^\d+\.\s*(.+)#");
        $phoneOta = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('For any changes with your flight, date, route or names call us at'))}][1]/following::text()[normalize-space()!=''][1]",
            null, false, "#^[\d\-\+\(\) ]+$#");
        $confNoOta = $this->http->FindSingleNode("//text()[{$this->starts($this->t('As per your booking #'))}][1]",
            null, false, "#^{$this->opt($this->t('As per your booking #'))}\s*(\d+)#");

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') and translate(translate(substring(normalize-space(.),string-length(normalize-space(.))-1),'APM','apm'),'apm','ddd')='dd'";
        $xpath = "//text()[{$ruleTime}]/ancestor::tr[count(./descendant::text()[{$ruleTime}])=2][./preceding-sibling::tr][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains($this->t('Airline Confirmation'))}]/descendant::text()[{$this->starts($this->t('Airline Confirmation'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $r = $email->add()->flight();

            if (!empty($confNoOta)) {
                $r->ota()->confirmation($confNoOta, $this->t('booking #'));
            }

            if (!empty($phoneOta)) {
                $r->ota()->phone($phoneOta,
                    $this->t('For any changes with your flight, date, route or names call us at'));
            }

            $r->general()
                ->confirmation($rl)
                ->travellers($pax, true);

            foreach ($roots as $root) {
                $s = $r->addSegment();

                // airline
                $airline = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains($this->t('Flight'))}]/descendant::text()[{$this->starts($this->t('Flight'))}]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $flight = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains($this->t('Flight'))}]/descendant::text()[{$this->starts($this->t('Flight'))}]",
                    $root, null, "#{$this->opt($this->t('Flight'))}\s+(\d+)#");

                $s->airline()
                    ->name($airline)
                    ->number($flight);

                // departure
                $time = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]", $root);
                $date = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $code = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]/following::text()[normalize-space()!=''][1]",
                    $root);
                $name = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]/following::text()[normalize-space()!=''][2]",
                    $root);
                $s->departure()
                    ->code($code)
                    ->name($name)
                    ->date(strtotime($date . ', ' . $time));

                // arrival
                $time = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][2]", $root);
                $date = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][2]/preceding::text()[normalize-space()!=''][1]",
                    $root);
                $code = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][2]/following::text()[normalize-space()!=''][1]",
                    $root);
                $name = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][2]/following::text()[normalize-space()!=''][2]",
                    $root);
                $s->arrival()
                    ->code($code)
                    ->name($name)
                    ->date(strtotime($date . ', ' . $time));

                // duration
                $duration = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]/following::text()[normalize-space()!=''][3]",
                    $root, false, "#^\d+[hrs]+\b.*#");

                if (!empty($duration)) {
                    $s->extra()->duration($duration);
                }

                // stops
                $stops = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]/following::text()[normalize-space()!=''][4]",
                    $root);

                if (preg_match("#Non[\- ]*stop#i", $stops)) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#^(\d+)\s*{$this->t('stop')}#i", $stops, $m)) {
                    $s->extra()->stops($m[1]);
                }
            }
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
            if (isset($words['View Full Itinerary'], $words['Airline Confirmation'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['View Full Itinerary'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Airline Confirmation'])}]")->length > 0
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }
}
