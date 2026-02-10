<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-206932074.eml, aeroplan/it-278326971.eml, aeroplan/it-724130826.eml";
    public $subjects = [
        // en
        'check in for your upcoming trip to',
        ' is departing earlier',
        ' is delayed',
        'Gate change for flight',
        'Gate assigned for flight',
        // fr
        'effectuez l’enregistrement pour votre prochain voyage à',
        'est retardé de nouveau',
        'est retardé.',
        'Changement de porte d’embarquement pour le vol',
        'Porte d’embarquement désignée pour le vol',
        'doit maintenant décoller plus tôt.',
        'Enregistrement pour votre voyage à destination de',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Check in for your trip to'                             => ['Check in for your trip to', 'check-in is now open for your flight to', 'welcome to your connection in '],
            'Original flight time'                                  => ['Original flight time', 'Last updated flight time:', 'Original time:', 'Last updated time:'],
            'your flight has a new gate and will be departing from' => ['your flight has a new gate and will be departing from',
                'your flight has been assigned a gate and will be departing from', 'your flight is delayed and is now departing', 'your flight is delayed further until', ],
            'Booking reference:' => 'Booking reference:',
            'Customer(s) on'     => ['Customer(s) on', 'Customers on', 'Customer on', 'Passenger(s) on', 'Passengers on', 'Passenger on'],
            // 'Aeroplan number:' => '',
            // 'Flight' => '',
            // 'Cabin class:' => '',
            'timeDelimiter'                                         => ':',
            'textAfterName'                                         => [', welcome to your connection in', ', check-in is now open for your flight to',
                ', your flight is delayed further until', ', your flight is delayed and is now departing at', ],
        ],
        "fr" => [
            'Check in for your trip to'                             => ['L’enregistrement pour votre voyage vers', 'l’enregistrement pour votre vol à destination de'],
            'Original flight time'                                  => ['Dernière mise à jour de l’heure du vol', 'Heure du vol initiale'],
            'your flight has a new gate and will be departing from' => ['votre vol partira désormais de la', 'votre vol partira de la'],
            'Booking reference:'                                    => 'Numéro de réservation :',
            'Customer(s) on'                                        => ['Client sur cette réservation :', 'Clients sur cette réservation :'],
            'Aeroplan number:'                                      => 'Numéro Aéroplan :',
            'Flight'                                                => 'Vol',
            'Cabin class:'                                          => 'Cabine :',
            'timeDelimiter'                                         => ' h ',
            'textAfterName'                                         => ', l’enregistrement pour votre vol à destination de',
        ],
    ];

    private $type = '';
    private $year = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'communications@info.aircanada.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, '.aircanada.com')]")->length === 0) {
            return false;
        }

        if ($this->detectBody() === true) {
            return true;
        }

        return false;
    }

    public function detectBody()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['Booking reference:'])
            ) {
                continue;
            }
            // Check In
            if (!empty($dict['Booking reference:']) && !empty($dict['Check in for your trip to'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Check in for your trip to'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Booking reference:'])}]")->length > 0
            ) {
                $this->lang = $lang;
                $this->type = 'checkIn';

                return true;
            }
            // Check In
            if ((!empty($dict['Original flight time'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Original flight time'])}]")->length > 0)
                || (!empty($dict['your flight has a new gate and will be departing from'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['your flight has a new gate and will be departing from'])}]")->length > 0)
            ) {
                $this->lang = $lang;
                $this->type = 'change';

                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.aircanada\.com$/i', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆"),"∆∆' . $this->t('timeDelimiter') . '∆∆")';

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Booking reference:'))}])[1]");

        if (preg_match("/^({$this->opt($this->t('Booking reference:'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//a[{$this->starts($this->t('Check in now'))}]/@href",
                null, true, "/&orderId=([A-Z\d]{5,7})&/");
        }

        if (empty($confirmation) && $this->type === 'change') {
            $f->general()
                ->noConfirmation();
        }

        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Customer(s) on'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Customer(s) on'))})]",
            null, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");

        if (empty($travellers)) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[{$this->contains($this->t('textAfterName'))}]",
                null, true, "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])?{$this->opt($this->t('textAfterName'))}/u")]);
        }

        if (empty($travellers) && $this->type === 'change') {
        } else {
            $f->general()
                ->travellers($travellers);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Aeroplan number:'))}]", null,
            true, "/{$this->opt($this->t('Aeroplan number:'))}\s*(\d+)$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $notReplace = "not(preceding::text()[{$this->starts($this->t('Original flight time'))}])";
        $xpath = "//tr[*[normalize-space()][1][{$this->starts($this->t('Flight'))}] and count(*[{$xpathTime}])=2][not(preceding::text()[{$notReplace}])]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug('$xpath = ' . print_r($xpath, true));

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[{$this->contains($this->t('Cabin class:'))} or {$this->contains($this->t('segment'))}]/following::text()[{$this->contains($this->t('time'))}][1]/ancestor::tr[1][{$this->contains($this->t('local'))}][{$notReplace}]");
        }

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//img[contains(@src, 'plane-takeoff@2x')]/ancestor::tr[1][{$notReplace}]");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/{$this->opt($this->t('Flight'))}?\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            // Mumbai (Bombay) (BOM) Saturday February 11 01:00 local time
            $departure = implode(' ', $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));
            $arrival = implode(' ', $this->http->FindNodes("*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\(\s*([A-Z]{3})\s*\)/", $departure, $m)) {
                $s->departure()->code($m[1]);
            }

            $terminal = $this->http->FindSingleNode("descendant::text()[normalize-space()][contains(., 'Terminal')]", $root);

            if (!empty($terminal)) {
                $s->departure()
                    ->terminal(preg_replace("/\s*\bTerminal\b\s*/i", '', trim($terminal)), true, true);
            }

            if (preg_match("/\(\s*([A-Z]{3})\s*\)/", $arrival, $m)) {
                $s->arrival()->code($m[1]);
            }

            $patterns['dateShort'] = '(?:[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)'; // February 09    |    09 February
            $patterns['time'] = '\d{2}' . $this->t('timeDelimiter') . '\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?'; // 04:19PM    |    02:00 p. m.

            $dateDep = $dateArr = $timeDep = $timeArr = null;

            if (preg_match("/\(\s*[A-Z]{3}\s*\)\s*(?<date>.*?\d.*?)\s+(?<time>{$patterns['time']})/", $departure, $matches)) {
                $dateDep = $this->normalizeDate($matches['date']);
                $timeDep = str_replace($this->t('timeDelimiter'), ':', $matches['time']);
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if (preg_match("/\(\s*[A-Z]{3}\s*\)\s*(?<date>.*?\d.*?)\s+(?<time>{$patterns['time']})/", $arrival, $matches)) {
                $dateArr = $this->normalizeDate($matches['date']);
                $timeArr = str_replace($this->t('timeDelimiter'), ':', $matches['time']);
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            $cabinClass = $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Cabin class:'))}][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match('/^[A-Z]{1,2}$/', $cabinClass)) {
                // J
                $s->extra()->bookingCode($cabinClass);
            } else {
                // Economy
                $s->extra()->cabin($cabinClass, false, true);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $emailDate = strtotime($parser->getDate());
        $this->year = date('Y', $emailDate ? $emailDate : null);
        $this->logger->debug('Email Year: ' . $this->year);

        $this->detectBody();

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
        return count(self::$dictionary) * 2;
    }

    private function normalizeDate($str)
    {
        // $year = date("Y", $this->date);
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // vendredi 09 juin
            // dim. 11 juin
            "/^\s*([[:alpha:]\-]+)[.]?\s+(\d+)\s+([[:alpha:]]+)[\.]?\s*$/u",
            // Wednesday October 12
            "/^\s*([[:alpha:]\-]+)[.,]?\s+([[:alpha:]]+)\s+(\d+)\s*$/u",
        ];
        $out = [
            "$1, $2 $3 {$this->year}",
            "$1, $3 $2 {$this->year}",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/^\s*(.*\b\d+\s+)([[:alpha:]]+)(\s+\d{4})\s*$/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }
        $this->logger->debug('$str 2 = ' . print_r($str, true));

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ \d{4})\s*$/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        $this->logger->debug('$str 3 = ' . print_r($str, true));

        return $str;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
