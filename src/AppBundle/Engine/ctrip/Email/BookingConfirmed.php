<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-113675949.eml, ctrip/it-11986513.eml, ctrip/it-19695015.eml, ctrip/it-28289074.eml, ctrip/it-42473461.eml, ctrip/it-50863871.eml, ctrip/it-50922026.eml, ctrip/it-51565752.eml, ctrip/it-51577556.eml, ctrip/it-55104450.eml, ctrip/it-82726315.eml";

    private $detectFrom = ['@ctrip.com', '@trip.com'];
    private $detectSubject = [
        // de
        "Flugbuchungsbestätigung",
        // ja
        "航空券予約確認書",
        // en
        "Flight Booking Confirmed",
        // pl
        "Rezerwacja lotu potwierdzona",
        // pt
        "Confirmação de reserva de voo",
        // it
        'Prenotazione del volo confermata',
        //nl
        'Boeking vlucht bevestigd',
    ];

    private $detectsHtml = [
        'th' => ['การจองเที่ยวบินได้รับการยืนยันแล้ว'],
        'de' => ['Flugbuchungsbestätigung'],
        'id' => ['Untuk iformasi selengkapnya, silakan periksa lampiran atau lihat pemesanan Anda lebih detail di situs web atau aplikasi Trip.com'],
        'ko' => ['트립닷컴 고객님, 안녕하세요'],
        'ru' => ['Бронирование авиабилета подтверждено'],
        'pt' => ['Confirmação de reserva de voo'],
        'en' => ['Flight Booking Confirmed'],
        'ja' => ['航空券予約確認書'],
        'zh' => ['機票訂單確認郵件'],
        'pl' => ['Rezerwacja lotu potwierdzona'],
        'it' => ["Prenotazione del volo confermata"],
        'nl' => ["Boeking vlucht bevestigd"],
    ];

    private $lang = '';

    private $pdfPattern = '.+\.pdf';

    private static $dictionary = [
        'nl' => [
            // HTML
            'Airline Booking Reference' => 'Boekingsreferentie luchtvaartmaatschappij',
            'Booking No'                => 'Boekingsnummer',
            'Passenger'                 => ['Passagier'],
            'Name'                      => 'Naam',
            'Ticket Number'             => 'Ticketnummer',
            //'Booked On'       => [''],
            // PDF
            //'Total amount'    => [''],
            //'Fare'            => [''],
            //'Taxes & Fees'    => '',
            //'Flights'         => '',
            //'Payment Summary' => '',
        ],
        'th' => [
            // HTML
            'Airline Booking Reference' => 'เลขที่อ้างอิงการจองของสายการบิน',
            'Booking No'                => 'หมายเลขการจอง',
            'Passenger'                 => 'ผู้โดยสาร',
            'Name'                      => 'ชื่อ',
            'Ticket Number'             => 'หมายเลขบัตรโดยสาร',
            //            'Booked On'       => '',
            // PDF
            //            'Total amount' => ['', ''],
            //            'Fare' => '',
            //            'Taxes & Fees' => '',
            //            'Flights' => '',
            //            'Payment Summary' => '',
        ],
        'de' => [
            // HTML
            'Airline Booking Reference' => 'Referenzcode der Fluggesellschaft',
            'Booking No'                => ['Buchungsnr', "Buchungsnr."],
            'Passenger'                 => ['Passagier(e)', 'Passagier'],
            //            'Name' => '',
            'Ticket Number'   => 'Ticketnummer',
            'Booked On'       => 'Gebucht am',
            // PDF
            'Total amount'    => ['Bestellmenge gesamt', 'Summe', 'Gesamtbetrag'],
            'Fare'            => ['Preis', 'Flugpreis'],
            'Taxes & Fees'    => 'Steuern & Gebühren',
            'Flights'         => 'Flüge',
            'Payment Summary' => 'Zahlungsübersicht',
        ],
        'pt' => [
            // HTML
            'Airline Booking Reference' => 'Referência de reserva da companhia aérea',
            'Booking No'                => 'Nº da reserva',
            'Passenger'                 => ['Passageiro'],
            'Name'                      => 'Nome',
            'Ticket Number'             => 'Número da passagem',
            'Booked On'                 => ['Reserva em', 'Reservado em'],
            // PDF
            'Total amount'    => ['Valor total'],
            'Fare'            => ['Tarifa'],
            'Taxes & Fees'    => 'Impostos e taxas',
            'Flights'         => 'Voos',
            'Payment Summary' => 'Resumo do pagamento',
        ],
        'ko' => [
            // HTML
            'Airline Booking Reference' => '항공사 예약번호(PNR)',
            'Booking No'                => '트립닷컴 예약번호',
            'Passenger'                 => ['탑승객 정보'],
            'Name'                      => '탑승객 이름',
            'Ticket Number'             => '항공권 번호',
            //            'Booked On' => '',
            // PDF
            //            'Total amount' => [''],
            //            'Fare' => [''],
            //            'Taxes & Fees' => '',
            //            'Flights' => '',
            //            'Payment Summary' => '',
        ],
        'ru' => [
            // HTML
            'Airline Booking Reference' => 'Код бронирования (PNR)',
            'Booking No'                => 'Бронирование №',
            'Passenger'                 => ['Пассажир'],
            'Name'                      => 'Имя',
            'Ticket Number'             => 'Номер билета',
            'Booked On'                 => 'Заказ оформлен',
            // PDF
            //            'Total amount' => [''],
            //            'Fare' => [''],
            //            'Taxes & Fees' => '',
            //            'Flights' => '',
            //            'Payment Summary' => '',
        ],
        'id' => [
            // HTML
            'Airline Booking Reference' => 'Referensi Pemesanan Maskapai',
            'Booking No'                => 'No. Pemesanan',
            'Passenger'                 => 'Penumpang',
            'Name'                      => 'Nama',
            'Ticket Number'             => 'Nomor Tiket',
            //            'Booked On' => '',
            // PDF
            'Total amount' => ['Total amount'],
            'Fare'         => 'Fare',
            //            'Taxes & Fees' => '',
            'Flights'         => 'Flights',
            'Payment Summary' => 'Payment Summary',
        ],
        'en' => [
            // HTML
            //            'Airline Booking Reference' => '',
            //            'Booking No'                => '',
            //            'Passenger'                 => '',
            //            'Name'                      => '',
            //            'Ticket Number'             => '',
            //            'Booked On'       => '',
            // PDF
            'Total amount' => ['Total amount', 'Total'],
            //            'Fare' => '',
            //            'Taxes & Fees' => '',
            //            'Flights' => '',
            //            'Payment Summary' => '',
        ],
        'ja' => [
            // HTML
            'Airline Booking Reference' => ['予約番号（PNR）', '航空会社予約番号（PNR）'],
            'Booking No'                => '予約番号',
            'Passenger'                 => ['乗客', '搭乗者'],
            'Name'                      => ['乗客名', '搭乗者名'],
            'Ticket Number'             => ['航空券番号', 'eチケット番号'],
            'Booked On'                 => '予約日時',
            // PDF
            'Total amount'    => ['Total amount'],
            'Fare'            => 'Fare',
            'Taxes & Fees'    => '税金手数料',
            'Flights'         => 'Flights',
            'Payment Summary' => 'Payment Summary',
        ],
        'zh' => [
            // HTML
            'Airline Booking Reference' => '預訂參考編號    ',
            'Booking No'                => ['訂單編號    ', '訂單編號'],
            'Passenger'                 => '乘客',
            'Name'                      => '姓名',
            'Ticket Number'             => '機票編號',
            //            'Booked On'       => '',
            // PDF
            'Total amount' => ['Total amount'],
            'Fare'         => 'Fare',
            //            'Taxes & Fees' => '',
            'Flights'         => 'Flights',
            'Payment Summary' => 'Payment Summary',
        ],
        'pl' => [
            // HTML
            'Airline Booking Reference' => 'Numer referencyjny rezerwacji w linii lotniczej',
            'Booking No'                => 'Nr rezerwacji',
            'Passenger'                 => 'Pasażer',
            'Name'                      => 'Nazwa',
            'Ticket Number'             => 'Numer biletu',
            'Booked On'                 => 'Zarezerwowano w dniu',
            // PDF
            //            'Total amount' => ['Total amount', 'Total'],
            //            'Fare' => '',
            //            'Taxes & Fees' => '',
            //            'Flights' => '',
            //            'Payment Summary' => '',
        ],
        'it' => [
            // HTML
            'Airline Booking Reference' => 'Codice di prenotazione della compagnia aerea',
            'Booking No'                => 'Prenotazione n.',
            'Passenger'                 => 'Passeggero',
            'Name'                      => 'Nome',
            'Ticket Number'             => 'Numero del biglietto',
            'Booked On'                 => 'Prenotazione effettuata il giorno',
            // PDF
            //            'Total amount' => ['Total amount', 'Total'],
            //            'Fare' => '',
            //            'Taxes & Fees' => '',
            //            'Flights' => '',
            //            'Payment Summary' => '',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (substr_count($body, '<head>') > 1) {
            $pos = stripos($body, '<head>');
            $posBegin = stripos($body, '<head>', $pos + 5);
            $i = 0;

            while (!empty($posBegin) && $i < 5) {
                $body = preg_replace("#<head>[\s\S]*?</head>#i", '', $body);
                $posBegin = stripos($body, '<head>', $pos + 5);
                $i++;
            }
            $this->http->SetEmailBody($body);
        }

        foreach ($this->detectsHtml as $lang => $detects) {
            if ($this->http->XPath->query('//node()[' . $this->contains($detects) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $email->setType('BookingConfirmed' . ucfirst($this->lang));

        $this->parseEmailHtml($email);

        /*
            + price and cabin
        */
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'E-Receipt') !== false) {
                $this->parsePdfReceipt($text, $email);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains(['.ctrip.com', '/ctrip.com', '//www.trip.com', '//Trip.com', '//pages.trip.com', 'id.trip.com/flights'], '@href') . "]")->length == 0
            && $this->http->XPath->query("//node()[" . $this->contains(['Thank you for choosing Trip.com']) . "]")->length == 0
        ) {
            return false;
        }

        foreach ($this->detectsHtml as $detects) {
            if ($this->http->XPath->query('//node()[' . $this->contains($detects) . ']')->length > 0) {
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

    private function parseEmailHtml(Email $email): void
    {
        // Travel Agency
        $conf = $this->getNode($this->t('Booking No'));

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//td[" . $this->eq($this->t('Booking No')) . "]/following-sibling::td[1]");
        }

        $email->ota()
            ->confirmation($conf);

        $f = $email->add()->flight();

        // General
        $f->general()
            ->travellers(preg_replace("/(.+?)\s*\/\s*(.+)/", '$2 $1',
                $this->http->FindNodes('//text()[' . $this->eq($this->t("Passenger")) . ']/ancestor::tr[1]/following-sibling::tr[1]//tr[not(.//tr)]/td[1][not(' . $this->eq($this->t("Name")) . ')]')), true)
        ;
        $date = $this->normalizeDate($this->getNode($this->t('Booked On')));

        if (!empty($date)) {
            $f->general()
                ->date($date);
        }

        $ticketNumbers = array_filter($this->http->FindNodes('//text()[' . $this->eq($this->t("Passenger")) . ']/ancestor::tr[1]/following-sibling::tr[1][' . $this->contains($this->t("Ticket Number")) . ']//tr[not(.//tr)]/td[2]', null, "#^\s*([\d\- ]{7,}(?:\s*,\s*[\d\- ]{7,})*)\s*$#"));
        $tickets = [];

        foreach ($ticketNumbers as $value) {
            $tickets = array_merge($tickets, explode(',', $value));
        }
        $tickets = array_map('trim', array_unique(array_filter($tickets)));

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        $airlineBookingReference = $this->http->FindSingleNode("//td[(" . $this->eq($this->t("Airline Booking Reference")) . ") and not(descendant::td)]/following-sibling::td[normalize-space()][1]");

        if (
            empty($airlineBookingReference)
            && empty($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Airline Booking Reference')) . ']'))
        ) {
            $airlineBookingReference = CONFNO_UNKNOWN;
        }

        if (
            empty($airlineBookingReference)
            && !empty($this->http->FindSingleNode('//tr[' . $this->eq($this->t('Airline Booking Reference')) . ' and not(.//tr)]'))
            && empty($this->http->FindSingleNode("//tr[" . $this->eq($this->t('Airline Booking Reference')) . ' and not(.//tr)]/following::text()[string-length(normalize-space())>3][1]', null, true, '/^\W*([A-Z\d]{5,})\b/'))
        ) {
            $airlineBookingReference = CONFNO_UNKNOWN;
        }

        $airlineBookingReference = preg_split('/\s*,\s*/', $airlineBookingReference);

        if (empty($airlineBookingReference)) {
            $this->logger->debug('RecordLocator not found!');

            return;
        } else {
            foreach ($airlineBookingReference as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        }

        $segmentXpath = "tr[not(.//tr) and count(td) = 3 and td[1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')] and td[2][string-length(normalize-space())=3]]";
        $xpath = "//" . $segmentXpath . "[following::" . $segmentXpath . "]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//td[contains(@style, '#f2f8ff') or contains(@style, '#F2F8FF')  or contains(@style, 'rgb(242,248,255);')]/ancestor::tr[1]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found by xpath: ' . $xpath);

            return;
        }

        foreach ($segments as $key => $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]', $root);

            if (preg_match('/\W\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)(?:\b|\D)/', $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            $dates = $this->http->XPath->query(".//text()[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')]/ancestor::tr[1]", $root);

            $rows = $this->http->XPath->query('.//' . $segmentXpath, $root);

            if ($rows->length === 2) {
                // Departure
                $s->departure()
                    ->code($this->http->FindSingleNode("td[2]", $rows->item(0)))
                    ->date($this->normalizeDate($this->http->FindSingleNode("td[1]", $rows->item(0))))
                ;
                $name = $this->http->FindSingleNode("td[3]", $rows->item(0));

                if (preg_match("#(.+?)(?:\s+T(\w+))$#", $name, $m)) {
                    $name = trim($m[1]);

                    $s->departure()
                        ->terminal($m[2]);
                }
                $s->departure()
                    ->name($name);

                // Arrival
                $s->arrival()
                    ->code($this->http->FindSingleNode("td[2]", $rows->item(1)))
                    ->date($this->normalizeDate($this->http->FindSingleNode("td[1]", $rows->item(1))))
                ;
                $name = $this->http->FindSingleNode("td[3]", $rows->item(1));

                if (preg_match("#(.+?)(?:\s+T(\w+))$#", $name, $m)) {
                    $name = trim($m[1]);

                    $s->arrival()
                        ->terminal($m[2]);
                }
                $s->arrival()
                    ->name($name);
            }
        }

        $this->logger->debug('Parsing HTML success.');
    }

    private function normalizeDate(?string $str = '')
    {
//        $this->logger->info($str);

        $in = [
            "#^\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日\s*(\d+:\d+)\s*$#u", // 2018年 10月 31日 20:44
            "#^(\d{1,2})[.]?\s([A-z]+)\s(\d{4}),\s(\d{1,2}:\d{1,2})$#u", //12. Dezember 2019, 15:26
            "#^(\d{1,2}:\d{1,2})\s+(\d{1,2})\s(\w+)\s(\d{4})\s*г\.$#u", //07:30 29 декабря 2019 г.
            "#^\s*(\d{4})\s*년\s*(\d+)\s*월\s*(\d+)\s*일\s*(\d+:\d+)\s*$#u", //2020년 3월 1일 23:15
            "#^\s*(\d+) de (\w+) de (\d{4}), (\d+:\d+)\s*$#u", //18 de Janeiro de 2020, 05:21
        ];
        $out = [
            "$3.$2.$1 $4",
            "$1 $2 $3 $4",
            "$2 $3 $4, $1",
            "$3.$2.$1 $4",
            "$1 $2 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if ((false === strtotime($str)) && ('en' !== $this->lang) && preg_match('/\s*([[:alpha:]]+)\s*/iu', $str, $m)) {
            $str = preg_replace("/{$m[1]}/", MonthTranslate::translate($m[1], $this->lang), $str);
        }

//        $this->logger->info($str);
        return strtotime($str);
    }

    private function getNode($str, $re = null): ?string
    {
        return $this->http->FindSingleNode("//td[" . $this->contains($str) . "]/following-sibling::td[1]", null, true, $re);
    }

    private function parsePdfReceipt($text, Email $email): void
    {
        if (preg_match('/' . $this->opt($this->t('Total amount')) . '[ ]{5,}(.+)/', $text, $m)) {
            $email->price()
                ->total($this->amount($m[1]))
                ->currency($this->currency($m[1]));
        }

        if (preg_match('/' . $this->opt($this->t('Fare')) . '[ ]{5,}(.+)/', $text, $m)) {
            $email->price()
                ->cost($this->amount($m[1]));
        }

        if (preg_match('/' . $this->opt($this->t('Taxes & Fees')) . '[ ]{5,}(.+)/', $text, $m)) {
            $email->price()
                ->tax($this->amount($m[1]));
        }

        $pos1 = strpos($text, $this->t('Flights'));

        if ($pos1 !== false) {
            $text = substr($text, $pos1);
        }
        $pos2 = strpos($text, $this->t('Payment Summary'));

        if ($pos2 !== false) {
            $text = substr($text, 0, $pos2);
        }

        $segCount = 0;

        foreach ($email->getItineraries() as $ikey => $it) {
            if ($it->getType() !== 'flight') {
                continue;
            }
            /**
             * @var Flight $it
             */
            foreach ($it->getSegments() as $skey => $s) {
                $segCount++;

                if (
                    !empty($s->getDepCode()) && !empty($s->getArrCode())
                    && preg_match('/\d[ ]+' . $s->getDepCode() . '\s*-\s*' . $s->getArrCode() . '[ ]{5,}(.+)/', $text, $m)
                ) {
                    $s->extra()->cabin($m[1]);
                    $foundCabin = true;
                }
            }
        }

        if (empty($foundCabin)
            && preg_match_all('/^\s*(?:\w+[.,]? ){0,6}\d{4}(?: \w+[.,]?){0,6}[ ]{2,}.+?-.+?[ ]{5,}(.+)/mu', $text, $cabinMatches)
            && $segCount === count($cabinMatches[0])
        ) {
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() !== 'flight') {
                    continue;
                }
                /**
                 * @var Flight $it
                 */
                foreach ($it->getSegments() as $s) {
                    $s->extra()->cabin(array_shift($cabinMatches[1]));
                }
            }
        }

        $this->logger->debug('Parsing PDF-attachment success.');
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }
}
