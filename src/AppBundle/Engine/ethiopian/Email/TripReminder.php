<?php

namespace AwardWallet\Engine\ethiopian\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripReminder extends \TAccountChecker
{
    public $mailFiles = "ethiopian/it-10462542.eml, ethiopian/it-10492241.eml, ethiopian/it-10501729.eml, ethiopian/it-21762977.eml, ethiopian/it-33385397.eml, ethiopian/it-39152334.eml, ethiopian/it-3997933.eml, ethiopian/it-4168898.eml, ethiopian/it-46033165.eml, ethiopian/it-4614217.eml, ethiopian/it-4882153.eml, ethiopian/it-5125640.eml, ethiopian/it-5139744.eml, ethiopian/it-5926366.eml, ethiopian/it-5949526.eml, ethiopian/it-6213342.eml, ethiopian/it-6333803.eml, ethiopian/it-6333808.eml, ethiopian/it-8003792.eml, ethiopian/it-9885585.eml, ethiopian/it-9907128.eml, ethiopian/it-9934261.eml";

    public static $detectProvider = [
        'ethiopian' => [
            'from'           => '@ethiopianairlines.com',
            'subjectCompany' => 'Ethiopian',
            'detectBodyUrl'  => ['//www.ethiopianairlines.com'],
            'detectBody'     => ['Ethiopian'],
        ],
        'malindoair' => [
            'from' => '@malindoair.com',
            //            'subjectCompany' => '',
            'detectBodyUrl' => ['//www.malindoair.com', 'utm_source=malindoair'],
            'detectBody'    => ['Malindo Air', '@malindoair.com'],
        ],
        'cayman' => [
            'from' => 'caymanairways@sabre.com',
            //            'subjectCompany' => '',
            'detectBodyUrl' => ['.caymanairways.com/'],
            'detectBody'    => ['Cayman Airways'],
        ],
        'flyerbonus' => [
            'from'           => '@bangkokair.com',
            'subjectCompany' => 'Bangkok Airways',
            'detectBodyUrl'  => ['www.bangkokair.com'],
            'detectBody'     => ['Bangkok Airways'],
        ],
        'lionair' => [
            'from'           => '@bangkokair.com',
            'subjectCompany' => 'Thai Lion Air',
            'detectBodyUrl'  => ['.lionairthai.com'],
            'detectBody'     => ['Thai Lion Air'],
        ],
        'gulfair' => [
            'from'           => '@gulfair.com',
            'subjectCompany' => 'Gulf Air',
            'detectBodyUrl'  => ['.gulfair.com'],
            'detectBody'     => ['Gulf Air'],
        ],
        'aeromexico' => [
            'from' => '@aeromexico.com',
            //            'subjectCompany' => '',
            'detectBodyUrl' => ['//aeromexico.', '%2F%2Faeromexico.com'],
            'detectBody'    => ['Aeromexico'],
        ],
        'aerolineas' => [
            'from' => '@aerolineas.com',
            //            'subjectCompany' => '',
            'detectBodyUrl' => ['.aerolineas.com'],
            'detectBody'    => ['Aerolineas Argentinas'],
        ],
        'etihad' => [
            'from'           => '@etihad.',
            'subjectCompany' => 'Etihad Airways',
            'detectBodyUrl'  => ['.etihad.com'],
            'detectBody'     => ['Etihad Airways'],
        ],
        'jetairways' => [
            'from'           => '@jetairways.com',
            'subjectCompany' => 'Jet Airways',
            'detectBodyUrl'  => ['.jetairways.com'],
            'detectBody'     => ['Jet Airways'],
        ],
        'kulula' => [
            'from'           => '@flights.kulula.com',
            'subjectCompany' => 'kulula',
            'detectBodyUrl'  => ['.kulula.com'],
            'detectBody'     => ['kulula.com'],
        ],
        'mabuhay' => [
            'from'           => 'philippineairlines.com',
            'subjectCompany' => 'Philippine Airlines',
            'detectBodyUrl'  => ['.philippineairlines.com'],
            'detectBody'     => ['philippineairlines.com'],
        ],
        'velocity' => [
            'from'           => '@virginaustralia.com',
            'subjectCompany' => 'Virgin Australia',
            'detectBodyUrl'  => ['.virginaustralia.com'],
            'detectBody'     => ['Australia Airlines'],
        ],
        'silverairways' => [
            'from'           => '@silverairways.com',
            'subjectCompany' => 'Silver',
            'detectBodyUrl'  => ['silverairways.com'],
            'detectBody'     => ['Have you signed up yet for Silver emails to get the latest news and offers'],
        ],
        'batikair' => [
            'from'           => '@batikair.com',
            //            'subjectCompany' => 'Silver',
            'detectBodyUrl'  => ['.batikair.com'],
            'detectBody'     => ['Batik Air Passenger'],
        ],
    ];
    public $detectSubject = [
        "en" => ["Trip reminder", "Can’t wait to see you on board", "Important. Your flight had schedule changes",
            'Flight reassignment', 'Travel Reminder', 'Schedule change Notification',
            'Schedule Message - Flight Replacement/Time Change', 'flight has changed',
        ],
        "es" => ["Te esperamos a bordo!", "Importante.Tu vuelo tuvo modificaciones", "Su itinerario ha sido modificado"],
        "pt" => ["Importante.Seu voo sofreu modificações"],
    ];

    public $detectBody = [
        "it" => "Partenza alle",
        "fr" => "Départ à",
        "pt" => "Chegada",
        "es" => "Salida",
        "id" => "Berangkat",
        "en" => "Departs",
        "ja" => "出発時刻",
        "de" => "Abflug um",
        "ru" => "Отправление в",
        "ar" => "رقم المقعد",
    ];

    public static $dictionary = [
        "en" => [
            "Booking Code:" => ["Booking Code:", "Confirmation Code:", 'Booking Reference:', 'Reservation code #',
                'Etihad Airways Reference:', ],
        ],
        "it" => [
            "Booking Code:" => "CODICE DI PRENOTAZIONE:",
            "Departs"       => "Partenza alle",
            "Passengers"    => "Passeggeri",
            "Seat"          => "Posti",
            " to "          => " a ",
            "Operated by"   => ["Volo Operato Da", "Volo operato da"],
        ],
        "fr" => [
            "Booking Code:" => "CODE DE RESERVATION:",
            "Departs"       => "Départ à",
            "Passengers"    => "Passagers",
            "Seat"          => "Sièges",
            " to "          => " à ",
            "Operated by"   => ["Assuré Par", "Assuré par"],
        ],
        "pt" => [
            "Booking Code:" => "CÓDIGO DA RESERVA:",
            "Departs"       => "Saída",
            "Passengers"    => "Passageiros",
            "Seat"          => "Assentos",
            " to "          => " Para ",
            "Operated by"   => "Operado por",
        ],
        "es" => [
            "Booking Code:" => "CÓDIGO DE RESERVACIÓN:",
            "Departs"       => "Salida",
            "Passengers"    => "Pasajeros",
            "Seat"          => "Asientos",
            " to "          => " Hacia ",
            "Operated by"   => ["Operado Por", "Operado por"],
        ],
        "id" => [
            "Booking Code:" => "KODE PEMESANAN",
            "Departs"       => "Berangkat",
            "Passengers"    => "Penumpang",
            "Seat"          => "Kursi",
            " to "          => " Menuju ",
            //            "Operated by" => "NOTTRANSLATED",
        ],
        "ja" => [
            "Booking Code:" => "予約コード",
            "Departs"       => "出発時刻",
            "Passengers"    => "乗客",
            "Seat"          => "座席",
            " to "          => " へ ",
            //            "Operated by" => "NOTTRANSLATED",
        ],
        "de" => [
            "Booking Code:" => ["RESERVIERUNGSCODE:", "Etihad Airways-Referenz:"],
            "Departs"       => "Abflug um",
            "Passengers"    => "Passagiere",
            "Seat"          => "Sitzgelegenheiten",
            " to "          => " auf ",
            "Operated by"   => "Betreiber-Fluggesellschaft",
        ],
        "ru" => [
            "Booking Code:" => "КОД ПРЕДВАРИТЕЛЬНОГО ЗАКАЗА",
            "Departs"       => "Отправление в",
            "Passengers"    => "пассажиров",
            "Seat"          => "Мини-",
            " to "          => " к ",
            //            "Operated by" => "",
        ],
        "ar" => [
            "Booking Code:" => "الحجز أعدت",
            "Departs"       => "رقم المقعد",
            "Passengers"    => "أسماء الرُكاب",
            "Seat"          => "رقم المقعد",
            " to "          => " ل ",
            "Operated by"   => "مشغل الرحلة",
        ],
    ];

    public $lang = "en";

    public $providerCode = '';
    private $date;

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['from']) && stripos($from, $detects['from']) !== false) {
                $this->providerCode = $prov;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
            return false;
        }

        $detectProv = false;

        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['from']) && !empty($headers['from']) && stripos($headers['from'], $detects['from']) !== false) {
                $detectProv = true;
                $this->providerCode = $prov;

                break;
            } elseif (!empty($detects['subjectCompany']) && stripos($headers['subject'], $detects['subjectCompany']) !== false) {
                $detectProv = true;
                $this->providerCode = $prov;

                break;
            }
        }

        if ($detectProv === true) {
            foreach ($this->detectSubject as $detectSubject) {
                foreach ($detectSubject as $dSubject) {
                    if (stripos($headers["subject"], $dSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider() === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($parser->getHTMLBody(), $dBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate('date'));
        $this->http->FilterHTML = true;

        $this->assignProvider();

        foreach ($this->detectBody as $lang => $dBody) {
            if (strpos($this->http->Response["body"], $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $email->setProviderCode($this->providerCode);
        $email->setType('TripReminder' . ucfirst($this->lang));

        $this->parseHtml($email);

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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $confNo = $this->nextText($this->t("Booking Code:"));

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Booking Code:"))}]", null, false,
                "#{$this->preg_implode($this->t("Booking Code:"))}\s*([\w\-]+)$#");
        }
        $f->general()->confirmation($confNo);

        $passengers = [];
        $passengerPos = $this->http->XPath->query("//tr/*[normalize-space()][6][ descendant::text()[{$this->eq($this->t("Passengers"))}] ]/preceding-sibling::*")->length;

        // for: it-6333803.eml
        $seatPos = $this->http->XPath->query("//tr/*[normalize-space()][7][ descendant::text()[{$this->eq($this->t("Seat"))}] ]/preceding-sibling::*")->length;

        $xpath = "//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::tr[1]/following-sibling::tr[count(./td[string-length(normalize-space(.))>1])>4 and not(translate(normalize-space(./td[4]),'bus ','BUS')='BUS')]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $key => $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]", $root));

            $dTime = $this->http->FindSingleNode("*[normalize-space(.)][2]", $root);

            if (!empty($date) && !empty($dTime)) {
                $s->departure()->date(strtotime($dTime, $date));
            }
            $aTime = $this->http->FindSingleNode("*[normalize-space(.)][3]", $root);

            if (!empty($date) && !empty($aTime)) {
                if (preg_match("#(.+?)\s*\(\s*([\-\+]\s*\d{1,2})\s*\w*\s*\)#u", $aTime, $m)) {
                    // 6:05 AM (+1 day)
                    $s->arrival()->date(strtotime($m[1], $date));

                    if (!empty($s->getArrDate())) {
                        $s->arrival()->date(strtotime($m[2] . 'days', $s->getArrDate()));
                    }
                } else {
                    $s->arrival()->date(strtotime($aTime, $date));
                }
            }

            $flight = $this->http->FindSingleNode("*[normalize-space()][4]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            $operator = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Operated by")) . "]", $root, true, "#" . $this->preg_implode($this->t("Operated by")) . "\s+(\S.+)#");

            if (empty($operator)) {
                $operator = $this->nextText($this->t("Operated by"), $root);
            }

            if ($operator) {
                $s->airline()->operator($this->re("#^\s*(.+?)( DBA \S.*$|\s*$)#", $operator));
            }

            $s->departure()->code($this->http->FindSingleNode("./td[normalize-space(.)][5]", $root, true, "#^([A-Z]{3})" . $this->t(" to ") . "[A-Z]{3}$#"));
            $s->arrival()->code($this->http->FindSingleNode("./td[normalize-space(.)][5]", $root, true, "#^[A-Z]{3}" . $this->t(" to ") . "([A-Z]{3})$#"));

            // Passengers + Seats
            if ($passengerPos > 0
                && ($passenger = $this->http->FindSingleNode("*[$passengerPos+1]", $root, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'))
            ) {
                $passengers[] = $passenger;
            }
            $seats = [];

            if ($seatPos > 0
                && ($seat = $this->http->FindSingleNode("*[$seatPos+1]", $root, true, '/^\d{1,5}[A-z]$/'))
            ) {
                $seats[] = $seat;
            }
            $followRows = $this->http->XPath->query('following-sibling::tr', $root);

            foreach ($followRows as $row) {
                if (!empty($segments[$key + 1]) && $segments[$key + 1] === $row) {
                    break;
                }

                if ($passengerPos > 0
                    && ($passenger = $this->http->FindSingleNode("*[$passengerPos+1]", $row, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'))
                ) {
                    $passengers[] = $passenger;
                }

                if ($seatPos > 0
                    && ($seat = $this->http->FindSingleNode("*[$seatPos+1]", $row, true, '/^\d{1,5}[A-z]$/'))
                ) {
                    $seats[] = $seat;
                }
            }

            if (count($seats)) {
                $s->extra()->seats($seats);
            }

            $terminalDep = $this->http->FindSingleNode('*[normalize-space()][last()]', $root);

            if (preg_match('/^TERMINAL\s+\b([A-z\d\s]+)$/i', $terminalDep, $m)
                || preg_match('/^([A-z\d\s]+)\b\s+TERMINAL$/i', $terminalDep, $m)
                || preg_match('/^T?(\w)$/', $terminalDep, $m)
            ) {
                $s->departure()->terminal($m[1]);
            }
        }

        if (count($passengers)) {
            $passengers = preg_replace("/^\s*(Mr|Mrs|Miss|Mstr|Ms|Dr) /", '', $passengers);
            $f->general()->travellers(array_unique($passengers));
        }
    }

    private function assignProvider(): bool
    {
        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['detectBodyUrl']) && $this->http->XPath->query('//a[' . $this->contains($detects['detectBodyUrl'], '@href') . ']')->length > 0) {
                $this->providerCode = $prov;

                return true;
            } elseif (!empty($detects['detectBody']) && $this->http->XPath->query('//node()[' . $this->contains($detects['detectBody']) . ']')->length > 0) {
                $this->providerCode = $prov;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $this->logger->debug('$date = ' . $date);
        $year = date('Y', $this->date);
        $in = [
            "#^\s*([^\s\d\,\.]+)[.]?,\s*([^\s\d,.]+)[.]?\s+(\d{1,2})\s*$#u", //Thu, Oct 27; sam., avr. 06
            "#^\s*([^\s\d\,\.]+),\s*(\d{1,2})\s+(\d{2})\s*$#u", //木, 5 23
            "#^\s*(\d{1,2})\s+([^\s\d\,\.]+)\s+(\d{2})\s*$#u", //03 Jul 16
        ];
        $out = [
            "$1, $3 $2 $year",
            "$1, $3.$2.$year",
            "$1 $2 20$3",
        ];
        $date = preg_replace($in, $out, $date);

        $additionalLangs = ['en', 'to', 'de'];

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = preg_replace("/(\d+\s+){$m[1]}/", '$1' . $en, $date);
            } else {
                foreach ($additionalLangs as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $date = preg_replace("/(\d+\s+){$m[1]}/", '$1' . $en, $date);

                        break;
                    }
                }
            }
        }
        $this->logger->debug('$date = ' . $date);
        // mar, March 2020
        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+|\d{1,2}\.\d{1,2}\.\d{4})#u", $date, $m)) {
            $weekNum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            if (empty($weekNum)) {
                foreach ($additionalLangs as $lang) {
                    $weekNum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $lang));

                    if ($weekNum !== null) {
                        break;
                    }
                }
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weekNum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }
        $this->logger->debug('$date = ' . $date);

        return $date;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
