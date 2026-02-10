<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourFlightItinerary extends \TAccountChecker
{
    public $mailFiles = "expedia/it-13308074.eml, expedia/it-1588728.eml, expedia/it-1835040.eml, expedia/it-2145813.eml, expedia/it-2146044.eml, expedia/it-2158515.eml, expedia/it-6605958.eml, expedia/it-6606681.eml, expedia/it-6683728.eml";

    public $reBody2 = [
        "en" => "Revised Itinerary",
    ];
    public static $dictionary = [
        "en" => [
            "Itinerary No.:" => ["Itinerary No.:", "Itin No.:"],
        ],
    ];
    private $lang = "";
    private $code;
    private $codeSigh = [
        'orbitz'  => 'Orbitz',
        'expedia' => 'Expedia',
    ];
    private $bodies = [
        'orbitz' => [
            'Orbitz',
        ],
        'expedia' => [
            'Expedia',
        ],
    ];
    private static $headers = [
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                'Your flight itinerary to',
            ],
        ],
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'Your flight itinerary to',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $this->assignLang();

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        if (null !== ($this->code = $this->getProvider($parser))) {
            $email->ota()->code($this->code);
            $email->setProviderCode($this->code);
        } else {
            $this->logger->debug('can\'t determine providerCode');

            return $email;
        }
        $this->date = strtotime($parser->getDate());
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Main contact:'))}]/following::text()[normalize-space()!=''][2][{$this->starts($this->t('E-mail:'))}]",
            null, false, "#{$this->opt($this->t('E-mail:'))}\s*(.+\@.+)#");

        if (empty($node)) {
            $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Main contact:'))}]/following::text()[normalize-space()!=''][2][{$this->starts($this->t('E-mail:'))}]/following::text()[normalize-space()!=''][1]",
                null, false, "#(.+\@.+)#");
        }
        $email->setUserEmail($node);

        $this->parseTAInfo($email);

        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== ($code = $this->getProviderByBody())) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $provs = count(self::$headers);
        $langs = count(self::$dictionary);

        return $provs * $langs;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[" . $this->eq("Traveler:") . "]/following::text()[normalize-space(.)][1]"));
        $f->issued()
            ->tickets(array_filter($this->http->FindNodes("//text()[" . $this->eq("Airline Ticket No.:") . "]/following::text()[normalize-space(.)][1]",
                null, "#^[\d\s]+$#")), false);

        $xpath = "//text()[" . $this->eq($this->t("Airline confirmation code:")) . "]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[normalize-space(.) and not(" . $this->contains('Flight') . ")][1]",
                $root)));

            $s->airline()
                ->number($this->http->FindSingleNode("./td[1]/descendant::tr[normalize-space(.)!=''][1]", $root, true,
                    "#.*?\s+{$this->opt($this->t('Flight'))}\s+(\d+)$#"))
                ->name($this->http->FindSingleNode("./td[1]/descendant::tr[normalize-space(.)!=''][1]", $root, true,
                    "#(.*?)\s+{$this->opt($this->t('Flight'))}\s+\d+$#"))
                ->confirmation($this->nextText($this->t("Airline confirmation code:"), $root));
            $s->departure()
                ->code($this->http->FindSingleNode("./td[2]/descendant::tr[normalize-space(.)!=''][2]/td[2]", $root,
                    true, "#.*?\s+-\s+([A-Z]{3})$#"))
                ->name($this->http->FindSingleNode("./td[2]/descendant::tr[normalize-space(.)!=''][2]/td[2]", $root,
                    true, "#(.*?)\s+-\s+[A-Z]{3}$#"))
                ->date(strtotime($this->http->FindSingleNode("./td[2]/descendant::tr[normalize-space(.)!=''][2]/td[1]",
                    $root, true, "#{$this->opt($this->t('Depart'))}\s+(.+)#"), $date));
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[2]/descendant::tr[normalize-space(.)!=''][3]/td[2]", $root,
                    true, "#.*?\s+-\s+([A-Z]{3})$#"))
                ->name($this->http->FindSingleNode("./td[2]/descendant::tr[normalize-space(.)!=''][3]/td[2]", $root,
                    true, "#(.*?)\s+-\s+[A-Z]{3}$#"))
                ->date(strtotime($this->http->FindSingleNode("./td[2]/descendant::tr[normalize-space(.)!=''][3]/td[1]",
                    $root, true, "#{$this->opt($this->t('Arrive'))}\s+(.+)#"), $date));

            $s->setOperatedBy($this->http->FindSingleNode(".//text()[" . $this->starts("Operated by") . "]", $root,
                true, "#Operated by\s+(.+)#"), false, true);
        }
    }

    private function parseTAInfo(Email $email)
    {
        $rl = $this->http->FindSingleNode("(//*[{$this->starts($this->codeSigh[$this->code])} and (({$this->contains($this->t('Itinerary No.:'))}))])[1]",
            null, false, "#.+?:\s*([A-Z\d\-]{5,})#");
        $rlDescr = $this->http->FindSingleNode("(//*[{$this->starts($this->codeSigh[$this->code])} and (({$this->contains($this->t('Itinerary No.:'))}))])[1]",
            null, false, "#(.+?):\s*[A-Z\d\-]{5,}#");
        $email->ota()->confirmation($rl, $rlDescr, true);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]",
            $root);
    }

    private function getProviderByBody()
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re}')]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        //		$year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Thursday, December 25, 2014
            "#^([^\d\s]+)-(\d+)-(\d{2})$#", //July-31-14
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
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
