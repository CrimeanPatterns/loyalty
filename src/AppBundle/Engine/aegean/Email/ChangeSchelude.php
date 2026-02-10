<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangeSchelude extends \TAccountChecker
{
    public $mailFiles = "aegean/it-118451168.eml, aegean/it-118451197.eml, aegean/it-137950220.eml";
    public $subjects = [
        'AEGEAN AIRLINES SCHEDULE CHANGE NOTIFICATION',
        'AEGEAN AIRLINES NOTIFICATION',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Passenger'],
        'el' => ['Επιβάτες'],
        'es' => ['Pasajeros'],
        'de' => ['Passagiere'],
    ];

    public static $dictionary = [
        "en" => [
            'Thank you for choosing AEGEAN for your travel' => [
                'Thank you for choosing AEGEAN for your travel',
                'If you wish to contact Aegean Airlines',
                'Aegean Airlines',
                'Our flight schedule has been updated',
            ],

            'Booking Reference:'   => ['Booking Reference :', 'Booking Reference:'],
            'New Flight Itinerary' => ['New Flight Itinerary', 'Flight Itinerary'],
        ],
        "el" => [
            'Thank you for choosing AEGEAN for your travel' => 'Παρακάτω εμφανίζονται οι πληροφορίες για την πτήση σας',
            'New Flight Itinerary'                          => ['Nέο Δρομολόγιο', 'Δρομολόγιο'],
            'Passengers'                                    => 'Επιβάτες',
            'Booking Reference:'                            => ['Κωδικός Κράτησης:', 'Κωδικός Κράτησης :'],
            'Passenger'                                     => 'Επιβάτες',
            'Flight'                                        => 'Πτήση',
            'From '                                         => 'Από ',
            'Seats'                                         => 'Θέσεις',
            'Seat'                                         => 'Θέση',
        ],

        "es" => [
            'Thank you for choosing AEGEAN for your travel' => 'Si desea ponerse en contacto con Aegean Airlines',
            'New Flight Itinerary'                          => ['Itinerario de vuelo'],
            'Passengers'                                    => 'Pasajeros',
            'Booking Reference:'                            => ['Referencia de la reserva:'],
            'Passenger'                                     => 'Pasajeros',
            'Flight'                                        => 'Vuelo',
            'From '                                         => 'De ',
        ],
        "de" => [
            'Thank you for choosing AEGEAN for your travel' => 'Ihre Reservierung wurde aufgrund einer Flugplanänderung modifiziert',
            'New Flight Itinerary'                          => ['Flugreiseplan'],
            'Passengers'                                    => 'Passagiere',
            'Booking Reference:'                            => ['Buchungsreferenz:'],
            'Passenger'                                     => 'Passagiere',
            'Flight'                                        => 'Flug',
            'From '                                         => 'Von ',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@schedulechange.aegeanair.com') !== false || stripos($headers['from'], 'flightstatus-noreply@aegeanair.com') !== false)) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing AEGEAN for your travel'))}] | //a[contains(@href, '.aegeanair.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('New Flight Itinerary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passengers'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]schedulechange\.aegeanair\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]{5,})/"))
            ->travellers(array_unique(preg_replace('/^\s*(?:MRS|MR|MS) /', '', $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr/td[normalize-space()][1]"))), true);

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr/td[normalize-space()][2]",
            null, "/^[\d\W]+$/"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight'))}]/ancestor::tr[1]/following-sibling::tr[1]");

        foreach ($nodes as $root) {
            $date = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('From '))}][1]/ancestor::tr[1]", $root, true, "/\-\s*(.+)/");
            $nodeText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^\s*(?<depTime>[\d\:]+)\s*(?<depName>\D+)\s*(?<arrTime>[\d\:]+)\s*(?<arrName>\D+)\s*(?<name>[A-Z][A-Z\d]{1})(?<flight>\d{2,4})$/", $nodeText, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['name'])
                    ->number($m['flight']);

                $s->departure()
                    ->noCode()
                    ->name(implode(', ', array_filter([trim($m['depName']), $this->http->FindSingleNode("./following::tr[1][count(*[normalize-space()]) = 3]/descendant::td[normalize-space()][1]", $root)])))
                    ->date($this->normalizeDate($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->noCode()
                    ->name(implode(', ', array_filter([trim($m['arrName']), $this->http->FindSingleNode("./following::tr[1][count(*[normalize-space()]) = 3]/descendant::td[normalize-space()][2]", $root)])))
                    ->date($this->normalizeDate($date . ', ' . $m['arrTime']));

                $depTerminal = $this->http->FindSingleNode("./following::tr[2]/descendant::td[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/u");

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }

                $arrTerminal = $this->http->FindSingleNode("./following::tr[2]/descendant::td[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/u");

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }

                $stops = $this->http->FindSingleNode("./following::tr[1]/descendant::td[normalize-space()][last()]", $root, true, "/^(\d+)\s*{$this->opt($this->t('stop'))}/u");

                if ($stops !== null) {
                    $s->extra()
                        ->stops($stops);
                }

                $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/following::tr[{$this->contains($this->t('Seat'))}]/descendant::text()[{$this->contains($s->getAirlineName() . $s->getFlightNumber())}]/following::text()[normalize-space()][1]", null, "/^\s*(\d+[A-Z])/");

                if (count($seats) > 0) {
                    $s->extra()
                        ->seats($seats);
                } else {
                    $seat = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Solicitudes de servicios especiales'))}]/following::text()[{$this->eq($this->t('Vuelo'))}]/following::text()[{$this->starts($s->getAirlineName() . $s->getFlightNumber())}]/ancestor::tr[1]/descendant::td[1]", null, true, "/\((.+)\)/");

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$word}')]")->length > 0) {
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
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->error($date);
        $in = [
            '/^(\d+)\s*(\D+)\s*(\d{4})\,\s*([\d\:]+)$/u', // 24Dec2021, 10:45
        ];
        $out = [
            '$1 $2 $3, $4',
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
}
