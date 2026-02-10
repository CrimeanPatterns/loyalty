<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RebookedItinerary extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-618089737.eml";
    public $subjects = [
        // en
        'Rebooked itinerary for booking',
        'Updated itinerary for trip to',
        'has a new departure time',
        // fr
        'Changement d’itinéraire pour la réservation',
        'Nouvelle heure de départ pour le vol',
        'Changement d’itinéraire pour votre voyage à destination de',
    ];

    public $lang = 'en';
    public $date;

    public $detectBody = [
        'en' => [
            'one or more flights in your itinerary were rebooked',
            'your flight was rebooked at a different connection airport',
            'Please review your new itinerary below:',
            'was rescheduled and will be departing on',
            'This applies to all customers on this booking.',
        ],
        'fr' => [
            'au moins un de vos vols a été modifié.',
            'a été reporté et partira plus tôt',
            'Cela s’applique à tous les passagers de cette réservation.',
        ],
    ];

    public static $dictionary = [
        'en' => [
            // 'Booking reference:' => '',
            'Departure'   => 'Departure',
            'Flight'      => 'Flight',
            'operated by' => 'operated by',
            'local time'  => 'local time',
            'Seat'        => 'Seat',
            'class'       => 'class',
        ],
        'fr' => [
            'Booking reference:' => 'Booking reference:',
            'Departure'          => 'Départ',
            'Flight'             => 'Vol',
            'operated by'        => 'Exploité par',
            'local time'         => '(heure locale)',
            'Seat'               => 'Siège',
            'class'              => 'Classe',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.aircanada.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), '@info.aircanada.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.aircanada\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Departure']) && !empty($dict['Flight'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Departure'])}]/following::*[{$this->starts($dict['Flight'])}][1]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getHeader('date'));

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure'))}]/preceding::text()[{$this->starts($this->t('Booking reference:'))}][1]",
                null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{5,7})\s*$/"));

        $paxText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[1]/following::table[2]/descendant::text()[normalize-space()]"));

        if (preg_match_all("/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*\:\n*/mu", $paxText, $m)) {
            $f->general()
                ->travellers(array_unique($m[1]), true);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/following::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $notOldDataStyle = 'not(ancestor-or-self::node()[contains(@style, "999999")])';

            $flightInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::b//text()[{$notOldDataStyle}][normalize-space()]", $root));

            if (preg_match("/^\s*{$this->opt($this->t('Flight'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\n(?:{$this->opt($this->t('operated by'))})?\s*(?<operator>.+)\s*$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber'])
                    ->operator($m['operator']);
            }

            $depInfo = implode(' ', $this->http->FindNodes("./descendant::td[2]/descendant::b//text()[{$notOldDataStyle}][normalize-space()]", $root));

            if (preg_match("/^\s*(?<name>\S.+\s+)?\((?<code>[A-Z]{3})\)\s*(?<date>\w+\,\s*\w+\s*\w+)\s*(?<time>[\d\:]+)\s*{$this->opt($this->t('local time'))}\s*$/u", $depInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']))
                    ->code($m['code']);

                if (!empty($m['name'])) {
                    $s->departure()
                        ->name(trim($m['name']));
                }
            }

            $arrInfo = implode(' ', $this->http->FindNodes("./descendant::td[3]/descendant::b//text()[{$notOldDataStyle}][normalize-space()]", $root));

            if (preg_match("/^\s*(?<name>\S.+\s+)?\((?<code>[A-Z]{3})\)\s*(?<date>\w+\,\s*\w+\s*\w+)\s*(?<time>[\d\:]+)\s*{$this->opt($this->t('local time'))}\s*$/u", $arrInfo, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']))
                    ->code($m['code']);

                if (!empty($m['name'])) {
                    $s->arrival()
                        ->name(trim($m['name']));
                }
            }

            $extraText = implode("\n", $this->http->FindNodes("./following::tr[2]/descendant::b//text()[normalize-space()][{$notOldDataStyle}]", $root));

            $rows = array_filter(preg_split("/\n *[[:alpha:]]+[-.\'[:alpha:] ]*[[:alpha:]]\s*\:\n*/", "\n" . $extraText . "\n"));
            $seats = $cabins = [];

            foreach ($rows as $row) {
                if (
                    preg_match("/^\s*{$this->opt($this->t('Seat'))}\s*(?<seat>\d{1,3}[A-Z])\s*$/u", $row, $m)
                    || preg_match("/^\s*{$this->opt($this->t('Seat'))}\s*(?<seat>\d{1,3}[A-Z])\s*[—\s]+\s*(?<cabin>[[:alpha:] ]+)\s*$/u", $row, $m)
                    || preg_match("/^\s*(?<cabin>[[:alpha:] ]+)\s*$/u", $row, $m)
                ) {
                    if (!empty($m['cabin'])) {
                        $m['cabin'] = preg_replace("/(^\s*{$this->opt($this->t('class'))}\s+|\s+{$this->opt($this->t('class'))}\s*$)/", '', $m['cabin']);
                        $cabins[] = $m['cabin'];
                    }

                    $seats[] = $m['seat'] ?? '';
                }
            }
            $cabins = array_unique(array_filter($cabins));

            if (!empty($cabins)) {
                $s->extra()
                    ->cabin(implode(", ", $cabins));
            }
            $seats = array_filter($seats);

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
        $year = date("Y", $this->date);
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Tuesday, December 05 07:45
            "#^\s*([[:alpha:]]+)\,\s*([[:alpha:]]+)\s*(\d{1,2})\s*(\d{1,2}:\d{2})\s*$#ui",
            "#^\s*([[:alpha:]]+)\,\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{1,2}:\d{2})\s*$#iu",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
            "$1, $2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
