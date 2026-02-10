<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingWith extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-686097505.eml, airfrance/it-713189638.eml, airfrance/it-716466874.eml, airfrance/it-716893725.eml";
    public $subjects = [
        // en
        '/Your Booking [A-Z\d]{6} with AIR France$/',
        '/Booking confirmed .+ < > .+ on .+/',
        // zh
        '/预订已确认 .+ < > .+/',
        // pt
        '/Reserva confirmada .+ < > .+ de .+/',
        // fr
        '/Réservation confirmée .+ < > .+ du .+/',
        '/Votre réservation [A-Z\d]{6} avec AIR FRANCE$/i',
        // it
        '/Prenotazione confermata .+ < > .+ del .+/',
        // es
        '/Reserva confirmada .+ < > .+ del .+/',
        // ru
        '/Бронирование подтверждено .+ < > .+/',
        // de
        '/Bestätigte Buchung Flug .+ < > .+ am .+/',
    ];

    public $date;
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your booking reference:' => ['Your booking reference:', 'Your booking is confirmed'],
            'Details of your trip'    => ['DETAILS OF YOUR TRIP', 'Details of your trip',  'YOUR TRIP DETAILS'],
            // 'Operated by:' => '',
            // 'terminal' => '',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)' => ['PASSENGER(S)'],
            // 'Seat reservation' => '',
            // 'RECEIVED' => '',
            // 'Total' => '',
        ],
        "zh" => [
            'Your booking reference:' => ['号预订：'],
            'Details of your trip'    => ['行程详情'],
            'Operated by:'            => '承运人:',
            'terminal'                => '航站楼',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)'     => ['旅客'],
            'Seat reservation' => '预订座位',
            'RECEIVED'         => '已收到',
            'Total'            => '总计',
        ],
        "pt" => [
            'Your booking reference:' => ['Temos o prazer de confirmar sua reserva'],
            'Details of your trip'    => ['DETALHES DE SUA VIAGEM'],
            'Operated by:'            => 'Operado por:',
            'terminal'                => 'Terminal',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)' => ['PASSAGEIRO(S)'],
            // 'Seat reservation' => '预订座位',
            'RECEIVED' => 'RECEBIDO',
            'Total'    => 'Total',
        ],
        "fr" => [
            'Your booking reference:' => ['Nous avons le plaisir de vous confirmer votre réservation',
                'Votre référence de réservation:', ],
            'Details of your trip' => ['DÉTAILS DE VOTRE VOYAGE', 'DETAIL DE VOTRE VOL'],
            'Operated by:'         => ['Effectué par :', 'Effectué par:'],
            'terminal'             => 'Terminal',
            'Flight time:'         => 'Durée du voyage:',
            'Further informations' => 'Plus d\'informations',
            'Aircraft:'            => 'Avion:',
            'Meals on board:'      => 'Repas à bord:',
            'PASSENGER(S)'         => ['PASSAGER(S)'],
            // 'Seat reservation' => '预订座位',
            'RECEIVED' => 'REÇU',
            'Total'    => 'Total',
        ],
        "it" => [
            'Your booking reference:' => ['Siamo lieti di confermarle la sua prenotazione'],
            'Details of your trip'    => ['DETTAGLI DEL SUO VIAGGIO'],
            'Operated by:'            => 'Operato da:',
            'terminal'                => 'Terminal',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)'     => ['PASSEGGERO/I'],
            'Seat reservation' => 'Prenotazione di un sedile',
            'RECEIVED'         => 'RICEVUTA',
            'Total'            => 'Totale',
        ],
        "es" => [
            'Your booking reference:' => ['Es un placer confirmarle su reserva'],
            'Details of your trip'    => ['INFORMACIÓN DETALLADA DEL VIAJE'],
            'Operated by:'            => 'Operado por:',
            'terminal'                => 'Terminal',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)'     => ['PASAJERO(S)'],
            'Seat reservation' => 'Prenotazione di un sedile',
            'RECEIVED'         => 'RECIBO',
            'Total'            => 'Total',
        ],
        "ru" => [
            'Your booking reference:' => ['С удовольствием подтверждаем ваше бронирование'],
            'Details of your trip'    => ['ИНФОРМАЦИЯ О ВАШЕМ ПУТЕШЕСТВИИ'],
            'Operated by:'            => ' Выполняет авиакомпания:',
            'terminal'                => 'Терминал',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)'     => ['ПАССАЖИР(Ы)'],
            'Seat reservation' => 'Бронирование места в салоне самолета',
            'RECEIVED'         => 'КВИТАНЦИЯ',
            'Total'            => 'Итого',
        ],
        "de" => [
            'Your booking reference:' => ['wir freuen uns, Ihnen Ihre Buchung'],
            'Details of your trip'    => ['REISEDETAILS:'],
            'Operated by:'            => 'Durchgeführt von:',
            'terminal'                => 'Terminal',
            // 'Flight time:' => '',
            // 'Further informations' => '',
            // 'Aircraft:' => '',
            // 'Meals on board:' => '',
            'PASSENGER(S)'     => ['PASSAGIER(E)'],
            'Seat reservation' => 'Sitzplatzreservierung',
            'RECEIVED'         => 'QUITTUNG',
            'Total'            => 'Gesamt',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@service-airfrance.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a/@href[{$this->contains(['wwws.airfrance.', '.flyingblue.fr'])}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains(['Thank you for choosing Air France', 'sito o sull\'applicazione Air France',
                'remercions d\'avoir choisi Air France', ])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Details of your trip']) && !empty($dict['PASSENGER(S)'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Details of your trip'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['PASSENGER(S)'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]service\-airfrance.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Details of your trip']) && !empty($dict['PASSENGER(S)'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Details of your trip'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['PASSENGER(S)'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getDate());
        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation(!in_array($this->lang, ['zh']) ?
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*:?\s*$/")
                : $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference:'))}]/preceding::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7}):?\s*$/")
            )
            ->travellers($this->http->FindNodes("//tr[{$this->eq($this->t('PASSENGER(S)'))}]/following-sibling::*/descendant::text()[normalize-space()][1][not(ancestor::tr[1][.//img[contains(@alt, 'Icon - Information')]])]",
                null, "/^\s*[[:alpha:]][[:alpha:]\-\' ]+ [[:alpha:]\-\' ]+[[:alpha:]]/u"))
        ;
        $dates = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Your booking reference:'))}]/following::text()[normalize-space()][position() < 7]",
            null, "/^\s*((?:.*\D|)20\d{2}(?:\D.*|))\s*$/"));

        if (count($dates) > 0) {
            $dates = array_values($dates);
            $this->date = $this->normalizeDate($dates[0]);

            if (empty($this->date) && preg_match("/\b(20\d{2})\b/", $dates[0], $m)) {
                $this->date = strtotime('01.01.' . $m[1]);
            }
            $f->general()
                ->date($this->date);
        } else {
            $this->logger->debug('check parsing Relative Date');
        }

        $seats = [];
        $seatsNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Seat reservation'))}]/ancestor::td[1]");

        foreach ($seatsNodes as $sRoot) {
            $flight = $this->http->FindSingleNode("preceding::text()[contains(., '>')][1]", $sRoot);
            $traveller = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $sRoot);
            $seat = $this->http->FindSingleNode(".", $sRoot, true, "/{$this->opt($this->t('Seat reservation'))}\s*:\s*(\d{1,3}[A-Z])\s*$/");

            if (preg_match("/^.*\(([A-Z]{3})\)\s*>\s*.+\(([A-Z]{3})\)\s*$/", $flight, $m)) {
                $seats[$m[1] . $m[2]][] = ['seat' => $seat, 'name' => $traveller];
            }
        }

        $nodes = $this->http->XPath->query("//tr[count(*) = 3][*[2][not(normalize-space())][.//img]]");

        foreach ($nodes as $root) {
            if (!preg_match("/\d/", $root->nodeValue)) {
                continue;
            }
            $s = $f->addSegment();

            // Airline
            $flightInfo = $this->http->FindNodes("preceding::text()[normalize-space()][1]/ancestor::td[position() < 3][preceding-sibling::*]/ancestor::tr[1]/*", $root);

            $date = $flightInfo[1] ?? '';

            if (preg_match("/^\s*(?<al>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fn>\d{1,4})\s*$/", $flightInfo[2] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            if (preg_match("/^\s*{$this->opt($this->t('Operated by:'))}\s*(?<operator>\S.+)\s*$/", $flightInfo[0], $m)) {
                $s->airline()
                    ->operator($m['operator']);
            }

            $re = "/^\s*(?<name>.+?)\n\s*(?<code>[A-Z]{3})\n(?<terminal>.+\n)?\s*(?<time>\d{1,2}\D\d{2}.*?)(?:\(\w(?<overnight>[-+]\d)\))?\s*$/s";
            // $this->logger->debug('$re = '.print_r( $re,true));

            // Departure
            $depInfo = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));
            // $this->logger->debug('$depInfo = '.print_r( $depInfo,true));

            if (preg_match($re, $depInfo, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->terminal(trim(preg_replace("/\s*{$this->opt($this->t('terminal'))}\s*\W*/i", ' ', $m['terminal'] ?? '')), true, true)
                    ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null);

                if (!empty($m['overnight']) && !empty($s->getDepDate())) {
                    $s->departure()
                        ->date(strtotime($m['overnight'] . ' day', $s->getDepDate()));
                }
            }

            // Arrival
            $arrInfo = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match($re, $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->terminal(trim(preg_replace("/\s*{$this->opt($this->t('terminal'))}\s*\W*/i", ' ', $m['terminal'] ?? '')), true, true)
                    ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null);

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['overnight'] . ' day', $s->getArrDate()));
                }
            }

            // Extra
            if (!empty($flightInfo[3])) {
                $s->extra()
                    ->cabin($flightInfo[3]);
            }

            $flightInfo2 = $this->http->FindNodes("preceding::text()[normalize-space()][1]/ancestor::tr[2]/*", $root);

            if (preg_match("/{$this->opt($this->t('Flight time:'))}\s*(.+)/", $flightInfo2[1] ?? '', $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            $aircraftInfo = implode("\n", $this->http->FindNodes("./following::text()[{$this->eq($this->t('Further informations'))}][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('Aircraft:'))}\s*(?<aircraft>.+?)\s*(?:{$this->opt($this->t('Meals on board:'))}|$)/", $aircraftInfo, $m)) {
                $s->extra()
                    ->aircraft($m['aircraft']);
            }

            if (preg_match("/{$this->opt($this->t('Meals on board:'))}\s*(.+)/", $aircraftInfo, $m)) {
                $s->extra()
                    ->meal($m[1]);
            }

            // Seats
            if (strlen($s->getDepCode() . $s->getArrCode()) === 6 && isset($seats[$s->getDepCode() . $s->getArrCode()])) {
                foreach ($seats[$s->getDepCode() . $s->getArrCode()] as $v) {
                    $s->extra()
                        ->seat($v['seat'], true, true, $v['name']);
                }
            }
        }

        // Price
        $priceNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('RECEIVED'))}]/following::text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/following-sibling::*[count(*[normalize-space()]) = 2]/*[normalize-space()][2]");

        foreach ($priceNodes as $pRoot) {
            if (preg_match("/^\s*MLS\s*\d[\d., ]+?\s*$/", $pRoot->nodeValue)) {
                $f->price()
                    ->spentAwards($pRoot->nodeValue);
            } elseif (preg_match("/^\s*(\D*?)\s*(\d[\d., ]+?)\s*$/", $pRoot->nodeValue, $m)) {
                $f->price()
                    ->currency($m[1])
                    ->total(PriceHelper::parse($m[2], $m[1]))
                ;
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        if ($year < 2000) {
            $year = '';
        }

        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // with year
            // Mardi 13 août 2024
            "/^\s*[[:alpha:]\-]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui",
            // Mardi 27 août 2024, 14:35
            "/^\s*[[:alpha:]\-]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2})\D(\d{2})\s*$/ui",

            // without year
            // Tuesday, March 11, 11h50
            "/^\s*([[:alpha:]\-]+)\s*\,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{1,2})\D(\d{2})\s*$/ui",
            // Domingo 8 de setembro, 07h00
            // Montag 7. Oktober, 16h55
            "/^\s*([[:alpha:]\-]+)\s*[\s,]\s*(\d{1,2})[.]?\s+(?:de\s+)?([[:alpha:]]+)\s*,\s*(\d{1,2})\D(\d{2})\s*$/ui",
            // 年10月5日星期六, 17h50
            "/^\s*年(\d{1,2})月(\d{1,2})日([[:alpha:]\-]+)\s*\,\s*(\d{1,2})\D(\d{2})\s*$/ui",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4:$5",

            "$1, $3 $2 $year, $4:$5",
            "$1, $2 $3 $year, $4:$5",
            "$3, $year-$1-$2, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]\-]+), (?<date>\d+ \w+ .+|\d+-\d+-.+)#u", $str, $m)
            && !empty($year)
        ) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

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
