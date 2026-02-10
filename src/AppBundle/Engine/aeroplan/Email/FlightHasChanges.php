<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightHasChanges extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-105871765.eml";
    public $subjects = [
        'ACTION REQUIRED: Your Air Canada flight has changed - Booking reference',
        'ACTION REQUISE: Votre vol Air Canada a changé - Numéro de réservation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your new itinerary'              => 'Your new itinerary',
            'Confirm flight or make a change' => [
                'Confirm flight or make a change',
                'We\'re sorry as a part of your itinerary has changed',
                'We’re sorry as one of your flights has a revised time due',
            ],
            // 'Booking Reference:' => '',
            // 'Dear' => '',
            // 'Operated by' => '',
        ],
        "fr" => [
            'Your new itinerary'              => 'Votre nouvel itinéraire',
            'Confirm flight or make a change' => [
                'Consultez les renseignements sur votre vol',
                'Nous sommes désolés, mais une partie de votre itinéraire a été modifiée',
            ],
            'Booking Reference:' => 'Numéro de réservation :',
            'Dear'               => 'Bonjour',
            'Operated by'        => 'Exploité parated by',
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notification.aircanada.ca') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'aircanada.com')]")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['Your new itinerary']) && !empty($dict['Confirm flight or make a change'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Your new itinerary'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Confirm flight or make a change'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notification\.aircanada\.ca$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][1]"))
            ->status('changed');

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}(.+)\,/");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller);
        }

        $xpathTime = 'contains(translate(normalize-space(),"0123456789","dddddddddd"),"d:dd")';
        $xpath = "//text()[{$this->eq($this->t('Your new itinerary'))}]/ancestor::tr[1]/following::text()[{$xpathTime}]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $segmentText = $this->http->FindSingleNode(".", $root);
            $segmentText = str_ireplace(['&shy;', '&#173;', '­'], '', $segmentText); // Soft hyphen

            if (preg_match("/^(?<depTime>[\d\:]+)\s*(?<depDate>\w+[.,]{0,2}\s*\w+[.,]{0,2}\s*\d+)\s*(?<arrTime>[\d\:]+)\s*(?<arrDate>\w+[.,]{0,2}\s*\w+[.,]{0,2}\s*\d+)\s*(?<depName>\D+)\s+(?<depCode>[A-Z]{3})\s*(?<aName>[A-Z\d]{2})\s*(?<aNumber>\d{1,4})\s+(?<arrName>\D+)\s*(?<arrCode>[A-Z]{3})\s*$/u", $segmentText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['aNumber']);

                $s->departure()
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                $s->arrival()
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']))
                    ->code($m['arrCode']);

                $operator = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your new itinerary'))}]/preceding::text()[normalize-space()='{$s->getAirlineName()} {$s->getFlightNumber()}'][1]/ancestor::table[1]/descendant::text()[{$this->starts($this->t('Operated by'))}]", null, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your new itinerary']) && !empty($dict['Confirm flight or make a change'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your new itinerary'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Confirm flight or make a change'])}]")->length > 0
            ) {
                $this->lang = $lang;
            }
        }

        $this->date = strtotime($parser->getDate());
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);

        $in = [
            // Mon, Aug 02, 15:45
            // sam., juil. 01, 19:56
            '/^\s*([[:alpha:]]+)[.,]*\s*([[:alpha:]]+)[.,]*\s*(\d+)\s*,\s*(\d{1,2}:\d{2})\s*$/ui',
            // '/^\s*(\w+).*?(\w+).*?(\d+).*?\s*(\d{1,2}:\d{2})\s*$/ui',
        ];
        $out = [
            "$1, $3 $2 {$year}, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#(?<week>\w+), (?<date>\d+ \w+\s*.+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
