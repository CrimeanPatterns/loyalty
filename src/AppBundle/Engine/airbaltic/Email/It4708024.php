<?php

namespace AwardWallet\Engine\airbaltic\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It4708024 extends \TAccountChecker
{
    public $mailFiles = "airbaltic/it-33010634.eml, airbaltic/it-3995278.eml, airbaltic/it-4598281.eml, airbaltic/it-4645992.eml, airbaltic/it-4708024.eml, airbaltic/it-4971328.eml, airbaltic/it-4977741.eml, airbaltic/it-5064280.eml, airbaltic/it-6018938.eml";

    public static $dictionary = [
        "en" => [
            //            "Booking reference" => "",
            "This booking was made on" => ["This booking was made on", "Your additional item purchased has been successfully registered on"],
            //            "Total:" => "",
            //            "Outbound" => "",
            //            "Departure" => "",
            //            "Arrival" => "",
            //            "Terminal" => "",
            //            "Flight" => "",
            //            "Operated by" => "",
            //            "Return" => "",
            //            "SERVICES" => "",
            //            "Ticket Number" => "",
            //            "Checked bags" => "",
            //            "Seat" => "",
            // Pdf
            //            "Fare " => "",
            //            "Total" => "",
        ],
        "fi" => [
            "Booking reference"        => "Varaustunnus",
            "This booking was made on" => "Varauksen tekoaika on",
            "Total:"                   => "Kokonaissumma", // to check
            "Outbound"                 => "Lähtö",
            "Departure"                => "Lähtö",
            "Arrival"                  => "Saapuminen",
            "Terminal"                 => "Terminaali",
            "Flight"                   => "Lento",
            "Operated by"              => "Liikennöijä",
            "Return"                   => "Paluu", // взято из переводчика, нужен реальный образец письма!
            "SERVICES"                 => "PALVELUT",
            "Ticket Number"            => "Lipun numero",
            "Checked bags"             => "Kirjatut matkatavarat",
            //            "Seat" => "",
            // Pdf
            "Fare " => "Hinta ",
            "Total" => "Kokonaissumma",
        ],
        "ru" => [
            "Booking reference"        => "Номер бронирования",
            "This booking was made on" => ["Вы зарезервировали билет в", "Дата и время совершения покупки:", "Ваш дополнительно приобретенный товар успешно зарегистрирован"],
            "Total:"                   => "Итого:",
            "Outbound"                 => "Вылет",
            "Departure"                => "Вылет",
            "Arrival"                  => "Прибытие",
            "Terminal"                 => "Терминал",
            "Flight"                   => "Рейс",
            "Operated by"              => "Рейс выполняет",
            "Return"                   => "Обратно",
            "SERVICES"                 => "УСЛУГИ",
            "Ticket Number"            => "Номер билета",
            "Checked bags"             => "Сдаваемый багаж",
            "Seat"                     => "Место",
            // Pdf
            "Fare " => "Цена ",
            "Total" => "Итого",
        ],
        "et" => [
            "Booking reference"        => "Broneeringu viitenumber",
            "This booking was made on" => "Broneeringu tegemise aeg",
            "Total:"                   => "Kokku:", // to check
            "Outbound"                 => "Väljumine",
            "Departure"                => "Väljumine",
            "Arrival"                  => "Saabumine",
            //            "Terminal" => "",
            "Flight"        => "Lend",
            "Operated by"   => "Opereerija",
            "Return"        => "Tagasilennud",
            "SERVICES"      => "TEENUSED",
            "Ticket Number" => "Pileti number",
            "Checked bags"  => "Registreeritud pagasiühikud",
            //            "Seat" => "",
            // Pdf
            "Fare " => "Hind ",
            "Total" => "Kokku",
        ],
        "de" => [
            "Booking reference"        => "Buchungsnummer",
            "This booking was made on" => "Diese Buchung erfolgte an",
            "Total:"                   => "Gesamt:", // to check
            "Outbound"                 => "Abflug",
            "Departure"                => "Abflug",
            "Arrival"                  => "Ankunft",
            "Terminal"                 => "Terminal",
            "Flight"                   => "Flug",
            "Operated by"              => "Durchgeführt von",
            "Return"                   => "Rückflug",
            "SERVICES"                 => "SERVICE",
            "Ticket Number"            => "Ticketnummer",
            "Checked bags"             => "Eingecheckte Gepäckstücke",
            "Seat"                     => "Sitzplatz",
            // Pdf
            "Fare " => "Flugpreis",
            "Total" => "Gesamt",
        ],
        "lv" => [
            "Booking reference"        => "Rezervācijas numurs",
            "This booking was made on" => "Biļešu iegādes datums un laiks:",
            "Total:"                   => "Kopsumma:",
            "Outbound"                 => "Izlidošana",
            "Departure"                => "Izlidošana",
            "Arrival"                  => "Ielidošana",
            "Terminal"                 => "Terminālis",
            "Flight"                   => "Lidojums",
            "Operated by"              => "Lidojumu izpilda",
            "Return"                   => "Atgriešanās",
            "SERVICES"                 => "PAKALPOJUMI",
            "Ticket Number"            => "Biļetes numurs",
            "Checked bags"             => "Reģistrētā bagāža",
            "Seat"                     => "Sitzplatz",
            // Pdf
            "Fare " => "Cena ",
            "Total" => "Kopā",
        ],
        "lt" => [
            "Booking reference"        => "Rezervacijos numeris",
            "This booking was made on" => "Šis užsakymas gautas",
            //            "Total:"                   => "Kopsumma:",
            "Outbound"                 => "Išvykimas",
            "Departure"                => "Išvykimas",
            "Arrival"                  => "Atvykimas",
            //            "Terminal"                 => "Terminālis",
            "Flight"                   => "Skrydis",
            "Operated by"              => "Vykdomas",
            "Return"                   => "Grįžimas",
            //            "SERVICES"                 => "PAKALPOJUMI",
            "Ticket Number"            => "Bilieto numeris",
            "Checked bags"             => "Registruoti lagaminai",
            //            "Seat"                     => "Sitzplatz",
            // Pdf
            "Fare " => "Kaina ",
            "Total" => "Iš viso",
        ],
    ];

    private $detectFrom = '@airbaltic.';
    private $detectSubject = [
        'en' => 'Confirmation/Invoice',
        'Travel Confirmation/Invoice',
        'fi' => 'Matkaa koskeva vahvistus/lasku',
        'ru' => 'Подтверждение/Счет',
        'Подтверждение/Инвойс',
        'Подтверждение покупки и счет',
        'et' => 'Reisi kinnitus/arve',
        'de' => 'Reisebestätigung/Rechnung',
        'lv' => 'Rezervācijas apstiprinājums/Rēķins',
        'lt' => 'Skrydžio bilietas',
    ];

    private $detectCompany = 'airBaltic.com';
    private $detectBody = [
        'fi' => 'PALVELUT',
        'ru' => 'УСЛУГИ',
        'et' => 'TEENUSED',
        'de' => 'REISEPLAN',
        'lv' => 'PAKALPOJUMI',
        'lt' => 'PASLAUGOS',
        'en' => 'SERVICES', // after all
    ];

    private $lang = 'en';

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02  |  0167544038003-004
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->setEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        if (!empty($email->getItineraries()[0]) && empty($email->getItineraries()[0]->getPrice())) {
            $pdfs = $parser->searchAttachmentByName('.+\.pdf');

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (stripos($text, 'AIR BALTIC CORPORATION A/S') && strpos($text, $this->t('Total')) !== false
                            && strpos($text, $this->t('Fare ')) !== false) {
                        // Price
                        $total = $this->re("#\n\s*" . $this->t('Fare ') . "[ ]{5,}.*(?:\n.*){0,10}\n\s*" . $this->t('Total') . "[ ]{5,}(.+)#", $text);

                        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
                            $email->getItineraries()[0]->price()
                                ->total($this->amount($m['amount']))
                                ->currency($m['currency'])
                            ;
                        }
                    }
                } else {
                    return null;
                }
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
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
        return count(self::$dictionary);
    }

    private function flight(Email $email): void
    {
        $accountNames = ['airBaltic Club', 'PINS', 'airBaltic Club Executive'];

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking reference')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]"), $this->t('Booking reference'))
        ;
        $idate = $this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->starts($this->t('This booking was made on')) . "])[1]", null, true, "#" . $this->preg_implode($this->t('This booking was made on')) . "\s*(.+?)\s*(\(|$)#"));

        if (!empty($idate)) {
            $f->general()->date($idate);
        }

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total:")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($m['currency'])
            ;
        }

        $travellers = $tickets = $accounts = [];

        // Segments
        $xpath = "//text()[normalize-space(.)='" . $this->t('Departure') . ":']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[normalize-space(.)='" . $this->t('Arrival') . ":']/ancestor::tr[1]/preceding-sibling::tr[1][starts-with(normalize-space(.), '" . $this->t('Departure') . "')]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $this->logger->debug('Segments root not found: ' . $xpath);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[normalize-space(./td[1])='" . $this->t('Outbound') . "' or normalize-space(./td[1])='" . $this->t('Return') . "'][1]/td[2]", $root));

            if (empty($date) && in_array($this->lang, ['lt'])) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[normalize-space(./td[1])='" . $this->t('Outbound') . "' or normalize-space(./td[1])='" . $this->t('Return') . "'][following-sibling::tr[normalize-space()][1][starts-with(normalize-space(.), '" . $this->t('Departure') . "')]][1]/td[2]", $root));
            }

            // Airlines

            /*
                BT423 / Economy class / Basic ticket, Z
            */
            $flight = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2][ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Flight'), "translate(.,':','')")}] ]/*[normalize-space()][2]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s*\/|$)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                $servicesItems = $this->http->XPath->query("//*[{$this->eq($this->t('SERVICES'))}]/following::text()[{$this->contains(['(' . $m['name'] . $m['number'] . ')', '(' . $m['name'] . ' ' . $m['number'] . ')'])}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]");

                foreach ($servicesItems as $itemRoot) {
                    $passengerText = implode("\n", $this->http->FindNodes("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $itemRoot));
                    $passengerName = $this->normalizeTraveller($this->re("#^({$this->patterns['travellerName']})(?:\s*{$this->preg_implode($accountNames)}|$)#u", $passengerText));

                    if (!in_array($passengerName, $travellers)) {
                        $f->general()->traveller($passengerName, true);
                        $travellers[] = $passengerName;
                    }

                    if (preg_match("#^({$this->preg_implode($accountNames)})[:\s]+([-A-Z\d]{6,30})$#m", $passengerText, $matches)
                        && !in_array($matches[2], $accounts)
                    ) {
                        $f->program()->account($matches[2], false, $passengerName, $matches[1]);
                        $accounts[] = $matches[2];
                    }

                    $ticket = $this->http->FindSingleNode(".", $itemRoot, true, "#{$this->preg_implode($this->t("Ticket Number"))}\s*[:]+\s*({$this->patterns['eTicket']})$#");

                    if ($ticket && !in_array($ticket, $tickets)) {
                        $f->issued()->ticket($ticket, false, $passengerName);
                        $tickets[] = $ticket;
                    }

                    $seat = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<3][{$this->starts($this->t("Seat"))}][1]", $itemRoot, true, "#^{$this->preg_implode($this->t("Seat"))}\s*[:]+\s*(\d+[A-Z])$#");

                    if ($seat) {
                        $s->extra()->seat($seat, false, false, $passengerName);
                    }
                }
            }

            if (preg_match("/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s*\/\s*([^,\/]{2,}?)\s*\//", $flight, $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match("/,\s*([A-Z]{1,2})$/", $flight, $m)) {
                $s->extra()->bookingCode($m[1]);
            }

            $s->airline()->operator($this->http->FindSingleNode("./following-sibling::tr[3][normalize-space(./td[1])='" . $this->t('Operated by') . ":']/td[2]", $root, true, "#(.*?),#"), false, true);

            // Departure
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^\s*(?:\(\s*(?<add>[+\-]\d)\s*\)\s*)?(?<time>\d+:\d+)\s+(?<name>.+?)\s*(?:,\s*" . $this->t('Terminal') . "[*]?\s*(?<terminal>.*))?$#u", $node, $m)) {
                if (!empty($m['add'])) {
                    $date = strtotime($m['add'] . " days", $date);
                }
                $s->departure()
                    ->noCode()
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, true, true)
                ;

                if (!empty($date)) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
            }

            // Arrival
            $node = $this->http->FindSingleNode("./following-sibling::tr[1][normalize-space(./td[1])='" . $this->t('Arrival') . ":']/td[2]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./following-sibling::tr[1][normalize-space(./td[1])='" . $this->t('Arrival') . "']/td[2]", $root);
            }

            if (preg_match("#^\s*(?:\(\s*(?<add>[+\-]\d)\s*\)\s*)?(?<time>\d+:\d+)\s+(?<name>.+?)\s*(?:,\s*" . $this->t('Terminal') . "[*]?\s*(?<terminal>.*))?$#u", $node, $m)) {
                if (!empty($m['add'])) {
                    $date = strtotime($m['add'] . " days", $date);
                }
                $s->arrival()
                    ->noCode()
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, true, true)
                ;

                if (!empty($date)) {
                    $s->arrival()->date(strtotime($m['time'], $date));
                }
            }

            // Extra
            $s->extra()
                ->aircraft($this->http->FindSingleNode("./following-sibling::tr[3][normalize-space(./td[1])='" . $this->t('Operated by') . ":']/td[2]", $root, true, "#.*?,\s*(.+)#"))
            ;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*\S+\s+(\d+\.\d+.\d{4})\s*$#u", //Thu 19.05.2016
            "#^\s*\S+,\s*(\d+\.\d+.\d{4})\s*(\d+:\d+)\s*$#u", //Wednesday, 06.04.2016 10:56
        ];
        $out = [
            "$1",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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

    private function amount($price)
    {
        $price = str_replace(' ', '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:Miss|Mrs|Ms|Mr|Dr|Nainen|Г-н|Г-жа|Frau|Herr|K-gs)';

        return preg_replace([
            "/^(.{2,}?)\s+{$namePrefixes}[.\s]*$/i",
            "/^{$namePrefixes}[.\s]+(.{2,})$/i",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
