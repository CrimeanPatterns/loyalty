<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "bcd/it-11.eml, bcd/it-11309476.eml, bcd/it-12.eml, bcd/it-1601941.eml, bcd/it-1639842.eml, bcd/it-1639849.eml, bcd/it-1641042.eml, bcd/it-1675929.eml, bcd/it-1676994.eml, bcd/it-1682862.eml, bcd/it-1771770.eml, bcd/it-1803431.eml, bcd/it-1849657.eml, bcd/it-21.eml, bcd/it-2414676.eml, bcd/it-2481199.eml, bcd/it-2486648.eml, bcd/it-2486649.eml, bcd/it-2654483.eml, bcd/it-2661136.eml, bcd/it-2829456.eml, bcd/it-5.eml, bcd/it-5048911.eml, bcd/it-5319990.eml, bcd/it-5320012.eml, bcd/it-7.eml, bcd/it-8288610.eml";

    public $date;

    public $reBody2 = [
        "en" => ["CONFIRMATION NUMBERS", 'SABRE Record Locator', 'Apollo Record Locator'],
        "es" => ["NÚMEROS DE CONFIRMACIÓN"],
        "pt" => ["NÚMEROS DE CONFIRMAÇÃO"],
    ];

    public static $dictionary = [
        "en" => [
            "TripNumber"                  => ["SABRE Record Locator", "Apollo Record Locator"],
            "Rate"                        => ["Average Rate", "Rate"],
            "Name(s) of people Traveling" => ["Name(s) of people Traveling", "NAME(S) OF PEOPLE TRAVELLING"],
        ],
        "es" => [
            "CONFIRMATION NUMBERS"        => "NÚMEROS DE CONFIRMACIÓN",
            "TripNumber"                  => ["SABRE # de Localizador de Registro"],
            "Airline Record Locator #"    => "de Localizador de la Aerolínea",
            "AIR"                         => "AÉREO",
            "Flight/Equip"                => "Flight/Equip",
            "Name(s) of people Traveling" => "Nombre de Pasajero",
            "ITINERARY"                   => "ITINERARIO",
            'Name'                        => 'Nombre',
            "FARE INFORMATION"            => "INFORMACIÓN DE TARIFAS",
            "Total Flight"                => "Total del vuelo",
            "Base Airfare"                => "Tarifa aérea base",
            "Total Taxes"                 => "Total de impuestos y cargos aplicables",
            "Depart"                      => ["Partida", "Salida"], //mix with pt
            "Arrive"                      => ["Arribo", "Llegada"],
            "Stops"                       => "Escalas",
            "Miles"                       => "Millas",
            "Class"                       => "Clase",
            "Seats Requested"             => "Asientos Solicitados",
            "Status"                      => "Estado",
            // Hotel
        ],
        "pt" => [
            "CONFIRMATION NUMBERS"        => "NÚMEROS DE CONFIRMAÇÃO",
            "TripNumber"                  => ["SABRE Nº do localizador"],
            "Airline Record Locator #"    => "Nº do localizador da empresa aérea",
            "AIR"                         => "AÉREO",
            "Flight/Equip"                => "Flight/Equip",
            "Name(s) of people Traveling" => "Nomes dos passageiros que estão viajando",
            "ITINERARY"                   => "ITINERÁRIO",
            'Name'                        => 'Nome',
            //"FARE INFORMATION" => "",
            "Total Flight"    => "Total do voo",
            "Base Airfare"    => "Tarifa aérea básica",
            "Total Taxes"     => "Total de impostos",
            "Depart"          => ["Saída"],
            "Arrive"          => ["Chegada"],
            "Stops"           => "Escalas",
            "Miles"           => "Milhas",
            "Class"           => "Classe",
            "Seats Requested" => "Assentos solicitados",
            "Status"          => "Status",
            // Hotel
            "Hotel Confirmation" => "Nº de confirmação do hotel",
            "Location"           => "Localidade",
            "Rate"               => ["Average Rate"],
            "Address"            => "Endereço",
            "Phone"              => "Telefone",
        ],
    ];

    public $lang = "en";

    private $text;
    private $code;
    private $airlineRecLocs = [];
    private $travellers = [];
    private $tripNumber;

    private $headers = [
        'bcd' => [
            'from' => ['@bcdtravel.com', '@bcdtravel.co.uk'],
            'subj' => [
                "en" => "Booking Confirmation",
                "es" => "Confirmación de Reserva",
            ],
        ],
        'sabre' => [
            'from' => ['@sabre.'],
            'subj' => [
                "en" => "Booking Confirmation",
                "es" => "Confirmación de Reserva",
                "pt" => "Confirmação da reserva",
            ],
        ],
        'hoggrob' => [
            'from' => ['hrgworldwide.com', '@ar.hrgworldwide.com'],
            'subj' => [
                "en" => "Booking Confirmation",
                "es" => "Confirmación de Reserva",
            ],
        ],
    ];

    private static $providerDetectors = [
        'bcd' => [
            'BCD',
        ],
        'sabre' => [
            'SABRE', 'Apollo',
        ],
        'hoggrob' => [
            'HRG',
        ],
    ];

    private static $providerItineraryDetectors = [
        'national' => [
            'National',
        ],
        'hertz' => [
            'Hertz',
        ],
    ];

    public function parseHtml(Email $email)
    {
        $this->tripNumber = $this->re("#{$this->opt($this->t('TripNumber'))}[\s\#:]+([\w\-]+)#", $this->text);
        // Airline Record Locator #1 UA-GRNRED (United Airlines)
        // Nº do localizador da empresa aérea1 JJ-YW63ZX (Tam Linhas Aereas)
        $regex = "#{$this->opt($this->t('Airline Record Locator #'))}\s*\d+\s+\w{2}-([\w\-]+)\s+\((.+?)\)#i";

        if (preg_match_all($regex, $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $this->airlineRecLocs[$m[2]] = $m[1];
            }
        }

        $travellers = [];
        $this->reFunc("#\s*{$this->opt($this->t('Name'))}:\s*([^\n]+)#ms", function ($m) use (&$travellers) {
            $travellers[] = trim($m[1]);
        }, $this->re("#{$this->opt($this->t('Name(s) of people Traveling'))}(.*?){$this->opt($this->t('ITINERARY'))}#mis", $this->text));
        $this->travellers = array_values($travellers);

        $airs = [];
        $trains = [];
        $hotels = [];
        $cars = [];

        $arrs = $this->splitter("#\n\s*((?:{$this->t('AIR')}|{$this->t('Rail Company')}|{$this->t('HOTEL')}|{$this->t('CAR')}))#ms", $this->text);

        foreach ($arrs as $arr) {
            if (strpos($arr, $this->t('AIR')) === 0) {
                $airs[] = $arr;
            } elseif (strpos($arr, $this->t('Rail Company')) === 0) {
                $trains[] = $arr;
            } elseif (strpos($arr, $this->t('HOTEL')) === 0) {
                $hotels[] = $arr;
            } elseif (strpos($arr, $this->t('CAR')) === 0) {
                $cars[] = $arr;
            }
        }

        if (count($airs) > 0) {
            $this->parseAir($email, $airs);
        }

        if (count($trains) > 0) {
            $this->parseTrains($email, $trains);
        }

        if (count($hotels) > 0) {
            $this->parseHotel($email, $hotels);
        }

        if (count($cars) > 0) {
            $this->parseCar($email, $cars);
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
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

        foreach ($this->headers as $code => $arr) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();
        $flag = false;

        foreach (self::$providerDetectors as $code => $criteria) {
            if (count($criteria) > 0) {
                if (is_array($criteria) && $this->arrikey($body, $criteria) !== false) {
                    $flag = true;
                }
            }
        }

        if ($flag) {
            foreach ($this->reBody2 as $item) {
                $item = (array) $item;

                foreach ($item as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->text = str_replace('>', ' ', $parser->getPlainBody());
        //$this->logger->debug($this->text);
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2  as $lang => $item) {
            $item = (array) $item;

            foreach ($item as $re) {
                if (stripos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        if ($userEmail = $this->re("#Deliver To:.+?Email:\s*(.+?@[\w\-._]+)\n#is", $this->text)) {
            $email->setUserEmail($userEmail);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetectors);
    }

    public static function getEmailTypesCount()
    {
        $typesRes = 3;
        $cntProvs = 2;
        $cnt = count(self::$dictionary) * $typesRes * $cntProvs;

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProviderByItinerary(string $keyword)
    {
        if (!empty($keyword)) {
            foreach (self::$providerItineraryDetectors as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getHTMLBody();
        }
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'bcd') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach (self::$providerDetectors as $code => $criteria) {
            if (count($criteria) > 0) {
                if ($this->arrikey($body, $criteria) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parseAir(Email $email, $nodes)
    {
        $airs = [];

        foreach ($nodes as $root) {
            $airlineName = $this->re('#Flight/Equip.:\s*(.*?)\s+\d+#i', $root);
            $rl = (isset($this->airlineRecLocs[$airlineName])) ? $this->airlineRecLocs[$airlineName] : null;
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();
            $f->ota()->confirmation($this->tripNumber);
            $f->general()->confirmation($rl);

            if ($this->travellers) {
                $f->general()->travellers($this->travellers);
            }

            foreach ($roots as $root) {
                if ($status = $this->getField($this->t("Status"), $root)) {
                    $f->general()->status($status);
                }
                $s = $f->addSegment();

                // Flight/Equip.:United Airlines 1043
                // Flight/Equip.: United Airlines 3493    Embraer ERJ-170
                // Operated By: SHUTTLE AMERICA DBA UNITED EXPRESS
                if (preg_match("#{$this->opt($this->t('Flight/Equip'))}.?:\s*(.*?)\s+(\d+)\s*(.*?)\s+(?:{$this->opt($this->t('Operated By'))}|{$this->opt($this->t('Depart'))})#is", $root, $m)) {
                    $s->airline()->name($m[1]);
                    $s->airline()->number($m[2]);
                    $s->extra()->aircraft($m[3], true, false);
                }

                if (preg_match("#{$this->opt($this->t('Operated By'))}:\s*(.+?)\s*(?:DBA|\n)#i", $root, $m)) {
                    $s->airline()->operator($m[1]);
                }

                // Depart: Santa Ana(SNA) Thursday, Jul 24 9:17 PM
                // Depart:Los Angeles(LAX)/Sunday, Feb 22 11:50
                // Saída: Brasilia(BSB) Segunda-feira, Ago 11 10:35
                // Llegada: La Paz(LPB) miércoles, feb 25 02:58
                $regex = "#{$this->opt($this->t('Depart'))}:\s*(.+?)\s*\(([A-Z]{3})\)[\s/]+([\w\-]+,\s+.+?\d+:\d+.*)#u";

                if (preg_match($regex, $root, $m)) {
                    $s->departure()->name($m[1]);
                    $s->departure()->code($m[2]);
                    $s->departure()->date($this->normalizeDate($m[3]));
                }
                $regex = "#{$this->opt($this->t('Arrive'))}:\s*(.+?)\s*\(([A-Z]{3})\)[\s/]+([\w\-]+,\s+.+?\d+:\d+.*)#u";

                if (preg_match($regex, $root, $m)) {
                    $s->arrival()->name($m[1]);
                    $s->arrival()->code($m[2]);
                    $s->arrival()->date($this->normalizeDate($m[3]));
                }

                $s->extra()->miles($this->re("#\s+{$this->opt($this->t('Miles'))}:\s*(\d+)#", $root), false, true);
                $s->extra()->cabin($this->getField($this->t('Class'), $root), true, false);

                if ($stop = $this->re("#{$this->opt($this->t('Stops'))}:\s+(.*?);?\s+{$this->opt($this->t('Miles'))}#i", $root)) {
                    if ($stop == 'non-stop') {
                        $s->extra()->stops(0);
                    }
                }

                if ($subj = $this->re("#{$this->opt($this->t('Seats Requested'))}:\s*((?:,?\s*\d{1,3}[A-Z])*)#", $root)) {
                    $s->extra()->seats(array_filter(explode(",", $subj)));
                }

                if ($meal = re("#{$this->opt($this->t('Meal'))}:\s*(.+)\s+#i", $this->text)) {
                    $s->extra()->meal(trim($meal));
                }
            }
        }

        if (count($email->getItineraries()) == 1) {
            $its = $email->getItineraries();
            //perhaps there is an error in the case of several airlines
            if (count($its[0]->getTravellers()) == 1) {//per person
                $subj = $this->re("#{$this->opt($this->t('Total Flight'))}\s+.*?\(.*\)\s+(.*)#i", $this->text);
                $tot = $this->getTotalCurrency($subj);

                if (!empty($tot['Total'])) {
                    $its[0]->price()->total($tot['Total']);
                    $its[0]->price()->currency($tot['Currency']);
                }
                $subj = $this->re("#{$this->opt($this->t('Base Airfare'))}\s+.*?\(.*\)\s+(.*)#i", $this->text);
                $tot = $this->getTotalCurrency($subj);

                if (!empty($tot['Total'])) {
                    $its[0]->price()->cost($tot['Total']);
                    $its[0]->price()->currency($tot['Currency']);
                }
                $subj = $this->re("#{$this->opt($this->t('Total Taxes'))}\s+.*?\(.*\)\s+(.*)#i", $this->text);
                $tot = $this->getTotalCurrency($subj);

                if (!empty($tot['Total'])) {
                    $its[0]->price()->tax($tot['Total']);
                    $its[0]->price()->currency($tot['Currency']);
                }
            }
        }
    }

    // it-2654483.eml
    private function parseTrains(Email $email, $nodes)
    {
        $its = [];
        $t = $email->add()->train();
        $t->general()->confirmation($this->re("/{$this->opt($this->t('Rail Record Locator #'))}[\s:\d]+([\w\-]+)/", $this->text));
        $t->ota()->confirmation($this->tripNumber);
        $t->general()->travellers($this->travellers);

        foreach ($nodes as $rl => $root) {
            $s = $t->addSegment();
            //  Number: Thalys  9328
            if (preg_match("#{$this->opt($this->t('Number'))}:\s*(.*?)\s+(\d+)#i", $root, $m)) {
                $s->extra()->number($m[2]);
            }
            // Depart: Rotterdam (NLRTC) Monday, 4 May 09:58
            // Address: Rotterdam, Rotterdam Netherlands
            $regex = "#{$this->opt($this->t('Depart'))}:\s*(.+?)\s+([\w\-]+,\s+.+?\d+:\d+.*?)\n";
            $regex .= "\s*{$this->opt($this->t('Address'))}:\s*(.+?)\s+\n#";

            if (preg_match($regex, $root, $m)) {
                $s->departure()->name("{$m[1]} - {$m[3]}");
                $s->departure()->date($this->normalizeDate($m[2]));
            }
            $regex = "#{$this->opt($this->t('Arrive'))}:\s*(.+?)\s+([\w\-]+,\s+.+?\d+:\d+.*?)\n";
            $regex .= "\s*{$this->opt($this->t('Address'))}:\s*(.+?)\s+\n#";

            if (preg_match($regex, $root, $m)) {
                $s->arrival()->name("{$m[1]} - {$m[3]}");
                $s->arrival()->date($this->normalizeDate($m[2]));
            }
            $s->extra()->cabin($this->re("#\s+{$this->opt($this->t('Class'))}:\s*([^\n]+){$this->opt($this->t('Seats Requested'))}#", $root));

            if ($seats = $this->re("#\s+{$this->opt($this->t('Seats Requested'))}:\s*([^\n]+)#", $root)) {
                $s->extra()->seat($seats);
            }
        }
        $subj = $this->re("#{$this->opt($this->t('Total Fare'))}:\s+(.*)#i", $this->text);
        $tot = $this->getTotalCurrency($subj);

        if (!empty($tot['Total'])) {
            $t->price()->total($tot['Total']);
            $t->price()->currency($tot['Currency']);
        }
        $subj = $this->re("#{$this->opt($this->t('Rail Fare'))}:\s+(.*)#i", $this->text);
        $tot = $this->getTotalCurrency($subj);

        if (!empty($tot['Total'])) {
            $t->price()->cost($tot['Total']);
            $t->price()->currency($tot['Currency']);
        }
    }

    private function parseHotel(Email $email, $nodes)
    {
        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $num = $this->re("#{$this->opt($this->t('Hotel Confirmation'))}[\#\s:]+([\w\-]+)#", $root);

            if ($num == 'NULL') {
                $num = $this->re("#{$this->opt($this->t('Hotel Confirmation'))}[\#\s:]+\d+\s*\w{2}-([\w\-]+)#", $this->text);
            }

            if ($num == 'NULL') {
                $h->general()->noConfirmation();
            } else {
                $h->general()->confirmation($num);
            }
            $h->ota()->confirmation($this->tripNumber);
            $h->general()->travellers($this->travellers);

            $h->hotel()->name($this->re("#\n\s*{$this->opt($this->t('Name'))}:\s*([^\n]*?)\s+{$this->opt($this->t('Location'))}:#", $root));

            if (preg_match("#{$this->t('Check-in')}:\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            } elseif (preg_match("#{$this->t('Check-in')}:\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            }

            if (preg_match("#{$this->t('Check-out')}:\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            } elseif (preg_match("#{$this->t('Check-out')}:\s+([\w\-]+,\s+.+?\d+:\d+(?:\s*[ap]m)?)#i", $root, $m)) {
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            }

            $h->hotel()->address($this->re("#\s+{$this->opt($this->t('Address'))}:\s*([^\n]+)#", $root));
            $h->hotel()->phone($this->re("#\s+{$this->opt($this->t('Phone'))}:\s*([\d\- ]+)#", $root));
            $h->hotel()->fax($this->re("#\s+{$this->opt($this->t('Fax'))}:\s*([\d\- ]+)#", $root), true, false);
            $r = $h->addRoom();
            $r->setRate(trim($this->re("#{$this->opt($this->t('Rate'))}:\s*([^\n]+)#", $root), " ,."), true, true);
            $desc = $this->re("#{$this->opt($this->t('Special Requests'))}:\s*([^\n]+)#", $root);

            if ($desc && strpos($desc, "***") === false) {
                $r->setDescription($desc, true, true);
            }
            $h->booked()->rooms($this->re("#{$this->opt($this->t('Number of Rooms'))}:\s*(\d+)#", $root), false, true);
        }
    }

    private function parseCar(Email $email, $nodes)
    {
        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $r->general()->confirmation($this->re("/{$this->opt($this->t('Confirmation'))}[\s#:]+([\w\-]+)/", $root));
            $r->ota()->confirmation($this->tripNumber);

            $rentalCompany = $this->getField($this->t("Vendor"), $root);

            if ($rentalCompany) {
                if (!empty($code = $this->getProviderByItinerary($rentalCompany))) {
                    $r->program()->code($code);
                } else {
                    $r->program()->keyword($rentalCompany);
                }
            }
            $r->extra()->company($rentalCompany);

            // Pick-up: Friday, Jul 4 08:00 New York Jfk
            // Address: Building 308 Federal Circle
            // Tel.: 888-826-6890
            if (
            preg_match("#{$this->t('Pick-up')}:\s+(\w+,\s+.+?\d+:\d+(?:\s*[ap]m)?)\s*([^\n]*)\s+{$this->opt($this->t('Address'))}:\s+(.+?)\s+{$this->opt($this->t('Tel'))}.?:\s+([\d\- ]+)#i",
                $root, $m)
            ) {
                $r->pickup()->date($this->normalizeDate($m[1]));
                $m[2] = trim($m[2]);

                if (!empty($m[2])) {
                    $r->pickup()->location($m[2] . ', ' . trim($m[3]));
                } else {
                    $r->pickup()->location(trim($m[3]));
                }
                $r->pickup()->phone($m[4]);
            }

            if (
            preg_match("#{$this->t('Drop-Off')}:\s+(\w+,\s+.+?\d+:\d+(?:\s*[ap]m)?)\s*([^\n]*)\s+{$this->opt($this->t('Address'))}:\s+(.+?)\s+{$this->opt($this->t('Tel'))}.?:\s+([\d\- ]+)#i",
                $root, $m)
            ) {
                $r->dropoff()->date($this->normalizeDate($m[1]));
                $m[2] = trim($m[2]);

                if (!empty($m[2])) {
                    $r->dropoff()->location($m[2] . ', ' . trim($m[3]));
                } else {
                    $r->dropoff()->location(trim($m[3]));
                }
                $r->dropoff()->phone($m[4]);
            }
            $r->car()->type($this->getField($this->t("Car size"), $root));
            $equip = $this->getField($this->t('Special Requests'), $root);

            if (!empty($equip) && strpos($equip, "***") === false) {
                foreach (explode(',', $equip) as $item) {
                    $r->extra()->equip($item, 0);
                }
            }
            $node = $this->getField($this->t('Total Car Cost'), $root);
            $tot = $this->getTotalCurrency($node);

            if (!empty($tot['Total'])) {
                $r->price()->total($tot['Total']);
                $r->price()->currency($tot['Currency']);
            }
        }
    }

    private function getField($field, $root)
    {
        return trim($this->re("#{$this->opt($field)}[\s:]*([^\n]*)#", $root));
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
        //$this->logger->error($date);
        $year = date('Y', $this->date);
        $date = trim($date);
        $in = [
            //domingo, mar 13 15:28
            "#^\s*(\w+)\s*,\s+(\w+)\s+(\d+)\s+(\d+:\d+(\s*[AP]M)?)$#u",
            //domingo, 13 mar 15:28
            "#^\s*(\w+)\s*,\s+(\d+)\s+(\w+)\s+(\d+:\d+(\s*[AP]M)?)$#iu",
            // Segunda-feira, Ago 11 10:35
            "#^\s*([\w\-]+)\s*,\s+(\w+)\s+(\d+)\s+(\d+:\d+(\s*[AP]M)?)$#u",
        ];
        $out = [
            '$3 $2 ' . $year . ' $4',
            '$2 $3 ' . $year . ' $4',
            '$2 $3 ' . $year . ' $4',
        ];
        $outWeek = [
            '$1',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . str_replace("#", "\#", preg_quote($s)) . ")";
        }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        //$node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return trim($m[$c]);
        }
        //$this->http->Log("not find: [$re] in [$str]\n");
        return null;
    }

    private function reFunc($re, $text, $index = 1)
    {
        return preg_replace_callback($re, function ($m) use ($text) {
            return $text($m);
        }, $index); // index as text in this case
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }
}
