<?php

namespace AwardWallet\Engine\crystalcruises\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "crystalcruises/it-112049050.eml, crystalcruises/it-145578679.eml";
    public $subjects = [
        'Booking Confirmation - Guest Copy, Booking',
    ];

    public $lang = 'en';
    public $lastDate;

    public static $dictionary = [
        "en" => [
            'confNumber'         => ['Cruise RESERVATION NUMBER:', 'CRUISE RESERVATION NUMBER:', 'CANCELLED RESERVATION NUMBER:'],
            'Crystal Society #:' => ['Crystal Society #:', 'Client ID:', 'CLIENT ID:', 'CRYSTAL SOCIETY'],
            'Guests / Stateroom' => ['Guests / Stateroom', 'Guests Stateroom', 'GUESTS / STATEROOM'],
            'taxes'              => ['Taxes, Fees And Port Charges', 'Taxes, Fees and Port Charges'],
            'Issued:'            => ['Issued:', 'ISSUED:'],
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@crystalcruises.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Crystal Cruises, LLC')]")->length > 0
            || $this->http->XPath->query("//a[contains(normalize-space(), 'www.crystalcruises.com')]")->length > 0) {
            return ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing Crystal Cruises')]")->length > 0
                    || $this->http->XPath->query("//text()[contains(normalize-space(), 'Important information regarding upcoming Crystal cruise')]")->length > 0
                    || $this->http->XPath->query("//text()[contains(normalize-space(), 'CANCELLED RESERVATION NUMBER:')]")->length > 0
                )
                && $this->http->XPath->query("//text()[contains(normalize-space(),'Cruise Itinerary') or contains(normalize-space(),'CRUISE ITINERARY') or contains(normalize-space(),'Crystal Society')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]crystalcruises\.com$/', $from) > 0;
    }

    public function ParseCruise(Email $email): void
    {
        $c = $email->add()->cruise();

        $ship = array_unique($this->http->FindNodes("//text()[contains(normalize-space(), 'days on board')]", null, "/{$this->opt($this->t('days on board'))}\s*(.+)$/"));

        $c->setShip($ship[0]);

        $c->general()
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Guests / Stateroom'))}]/following::text()[{$this->starts($this->t('Crystal Society #:'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]", null, "/^(?:Mrs|Mr|Ms)\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/iu"), true)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, "/{$this->opt($this->t('confNumber'))}\s*(\d+)/"), 'RESERVATION NUMBER')
            ->date(strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Issued:') or contains(normalize-space(), 'ISSUED:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Issued:'))}\s*(.+)/")));

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'CANCELLED RESERVATION NUMBER:')]")->length > 0) {
            $c->general()
                ->cancelled();
        }
        $roomInfoArray = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Guests / Stateroom'))}]/following::text()[{$this->starts($this->t('Crystal Society #:'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][3]")));

        foreach ($roomInfoArray as $roomInfo) {
            if (preg_match("/^(?<room>\d+)\s*\((?<deck>.+)\)\s+(?<description>.+)$/", $roomInfo, $m)) {
                $c->setRoom($m['room']);
                $c->setDeck($m['deck']);
                $c->setDescription($m['description']);
            }
        }

        $accounts = $this->http->FindNodes("//text()[{$this->eq($this->t('Guests / Stateroom'))}]/following::text()[{$this->starts($this->t('Crystal Society #:'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][2]", null, "/[#]?\s*\:\s*(\d+)(?:\,|$)/");

        if (count($accounts) > 0) {
            $c->setAccountNumbers($accounts, false);
        }

        $totalText = $this->http->FindSingleNode("//text()[normalize-space()='Your Crystal Cruise Fare']/ancestor::tr[1]/descendant::td[normalize-space()][last()]");

        if (preg_match("/^(\D)\s*(\d[,.\'\d ]*)$/", $totalText, $m)) {
            $c->price()
                ->total(PriceHelper::cost($m[2], ',', '.'))
                ->currency($m[1]);
        }

        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('taxes'))}]/ancestor::tr[1]/descendant::td[normalize-space()][last()]", null, true, "/^\D\s*(\d[,.\'\d ]*)$/");

        if (!empty($tax)) {
            $c->price()
                ->tax(PriceHelper::cost($tax, ',', '.'));
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Dock']/ancestor::table[1]/descendant::tr[not(contains(normalize-space(),'Location') or contains(normalize-space(),'LOCATION'))]");

        foreach ($nodes as $root) {
            $dateDep = '';
            $date = $this->http->FindSingleNode("./descendant::td[1]", $root);
            $dateDep = $date;

            if (!empty($date)) {
                $this->lastDate = $date;
            } else {
                $date = $this->lastDate;
            }

            $name = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()]", $root);

            $arrTime = str_replace(['Embark from '], '', $this->http->FindSingleNode("./descendant::td[3]", $root));
            $depTime = str_replace(['Overnight', 'Disembark AM/Morning', 'Disembark AM/MORNING'], '', $this->http->FindSingleNode("./descendant::td[4]", $root));

            if (!empty($name) && (!empty($arrTime))) {
                $s = $c->addSegment();
                $s->setName($name);
                $s->setAshore($this->normalizeDate($date . ' ' . $arrTime));

                if (empty($depTime)) {
                    $dateDep = $this->http->FindSingleNode("./following::tr[1]/td[1]", $root);
                    $depTime = str_replace(['Overnight', 'Disembark AM/Morning', 'Disembark AM/MORNING'], '', trim($this->http->FindSingleNode("./following::tr[1]/td[4]", $root)));
                }

                if (!empty($depTime)) {
                    if (!empty($dateDep)) {
                        $s->setAboard($this->normalizeDate($dateDep . ' ' . $depTime));
                    } else {
                        $s->setAboard($this->normalizeDate($date . ' ' . $depTime));
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->ParseCruise($email);

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

    private function normalizeDate($date)
    {
        $this->logger->warning($date);
        $year = date('Y', $this->date);
        $in = [
            '/^(\w+)\,\s*(\w+)\s*(\d+)\s*([\d\:]+\s*A?P?M)$/u', // Mon, Apr 25 12:00 PM
        ];
        $out = [
            '$1, $3 $2 ' . $year . ' $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
