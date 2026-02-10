<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItinerary2018 extends \TAccountChecker
{
    public $mailFiles = "airasia/it-31801432.eml, airasia/it-46221783.eml, airasia/it-46318465.eml, airasia/it-46858767.eml, airasia/it-47477354.eml, airasia/it-57661318.eml";

    public $lang = '';

    public static $dictionary = [
        'th' => [
            'Booking number'  => ['หมายเลขการสำรองที่นั่ง'],
            'Guests'          => 'ผู้โดยสาร',
            'Seat'            => 'ที่นั่ง',
            'Depart'          => 'ออกจาก',
            'Arrive'          => ['ถึง'],
            'Total paid'      => 'ยอดชำระล่าสุด',
            'confirmedStatus' => ['your booking is confirmed', 'บุ๊คกิ้งของคุณได้รับการยืนยันแล้ว'],
        ],
        'zh' => [
            'Booking number'  => ['訂位編號', '預訂號碼'],
            'Guests'          => ['乘客', '旅客'],
            'Seat'            => '座位',
            'Depart'          => ['出发', '出發'],
            'Arrive'          => ['回程'],
            'Total paid'      => ['最後繳付', '上次已付款項'],
            'resDate'         => '預訂日期',
            'confirmedStatus' => ['your booking is confirmed', '您的預訂已獲確認'],
        ],
        'vi' => [
            'Booking number' => ['Mã số đặt vé'],
            'Guests'         => 'Hành khách',
            //            'Seat' => '',
            'Depart'     => 'Khởi hành',
            'Arrive'     => ['Khứ hồi'],
            'Total paid' => 'Thanh toán gần nhất',
            //            'resDate' => '',
            'confirmedStatus' => ['your booking is confirmed', 'bạn đã được xác nhận đặt vé'],
        ],
        'en' => [
            'Booking number'  => ['Booking number'],
            'Arrive'          => ['Arrive'],
            'Total paid'      => ['Total paid', 'Last paid'],
            'confirmedStatus' => 'your booking is confirmed',
        ],
        'id' => [
            'Booking number' => ['Nomor pemesanan'],
            'Guests'         => 'Penumpang',
            //            'Seat' => '',
            'Depart'          => 'Keberangkatan',
            'Arrive'          => ['Kembali'],
            'Total paid'      => 'Total dibayarkan',
            'confirmedStatus' => ['your booking is confirmed', 'sudah terkonfirmasi'],
        ],
    ];

    private $detectSubject = [
        "en" => " - Travel Itinerary",
    ];

    private $detectCompany = "AirAsia";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]airasia\.com/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking number")) . "]/following::text()[normalize-space(.)][1][not(contains(.,' '))]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#"))
            ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Guests")) . "]/ancestor::tr[2]/following-sibling::tr[normalize-space(.)]/descendant::tr[normalize-space()][1]", null, "#^\s*(\D+)\s*$#"))))
        ;

        // Status
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('confirmedStatus'))}]")->length > 0) {
            $f->general()->status('confirmed');
        }
        // Reservation Date
        $resDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('resDate'))}]/ancestor::td[1]", null, false,
            "#{$this->preg_implode($this->t('resDate'))}\s+(.+)#iu");

        if (!empty($resDate)) {
            $f->general()->date($this->normalizeDate($resDate));
        }

        // Price
        $totalCharge = $this->http->FindSingleNode('//td[' . $this->starts($this->t('Total paid')) . ']/following-sibling::td[normalize-space(.)][1]');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalCharge, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalCharge, $m)) {
            $f->price()
                ->total($this->amount(trim($m['amount'])))
                ->currency($this->currency(trim($m['curr'])));
        }

        $seatXpath = "//text()[{$this->eq($this->t('Guests'))}]/ancestor::tr[2]/following-sibling::tr[normalize-space()]/descendant::tr[normalize-space()][(position() = 1 and not(following-sibling::tr[normalize-space()]) ) or {$this->contains($this->t('Seat'))}]";
        $seatNodes = $this->http->XPath->query($seatXpath);
        $seats = [];
        $seatFlight = '';

        foreach ($seatNodes as $value) {
            if ($flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $value, true, "#^\s*([A-Z\d]{2}\s*\d{1,5})\s*$#")) {
                $seatFlight = preg_replace("#\s+#", '', $flight);

                continue;
            }

            if ($seat = $this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t("Seat")) . "][1]", $value, true,
                    "#" . $this->preg_implode($this->t("Seat")) . ".*\s+(\d{1,3}[A-Z])\s*$#")) {
                $seats[$seatFlight][] = $seat;
            }
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('Depart'))}]/ancestor::tr[following-sibling::tr[{$this->starts($this->t('Arrive'))}]][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$#", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            // Departure
            $departure = implode(" ", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("#\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?:Terminal (?<term>[A-Z\d]{1,3})[ ]+)?(?:[A-Z]{1,3}IA\d*[ ]*)?(?<time>\d+:\d+.+)\s*$#s", $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['time']))
                ;

                if (!empty($m['term'])) {
                    $s->departure()
                        ->terminal($m['term']);
                }
            }
            $depTerm = trim($this->re("#\((.+)\)\s*\(\s*[A-Z]{3}\s*\)#", $departure));

            if (strlen($depTerm) === 2 && strpos($depTerm, 'T') === 0) {
                $depTerm = substr($depTerm, 1, 1);
            }

            if (!empty($depTerm) && empty($s->getDepTerminal())) {
                $s->departure()->terminal($depTerm);
            }

            // Arrival
            $arrival = implode(" ", $this->http->FindNodes("following-sibling::tr[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("#\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?:Terminal (?<term>[A-Z\d]{1,3})[ ]+)?(?:[A-Z]{1,3}IA\d*[ ]*)?(?<time>\d+:\d+.+)\s*$#s", $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['time']))
                ;

                if (!empty($m['term'])) {
                    $s->arrival()
                        ->terminal($m['term']);
                }
            }
            $arrTerm = trim($this->re("#\((.+)\)\s*\(\s*[A-Z]{3}\s*\)#", $arrival));

            if (strlen($arrTerm) === 2 && strpos($arrTerm, 'T') === 0) {
                $arrTerm = substr($arrTerm, 1, 1);
            }

            if (!empty($arrTerm) && empty($s->getArrTerminal())) {
                $s->arrival()->terminal($arrTerm);
            }

            // Extra
            if (!empty($seats) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                    && !empty($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                $s->extra()->seats($seats[$s->getAirlineName() . $s->getFlightNumber()]);
            }
        }

        return $email;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Booking number']) || empty($phrases['Arrive'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking number'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Arrive'])}]")->length > 0
            ) {
                $this->lang = $lang;

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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->http->log($str);
        $in = [
            "#^\s*(\d+:\d+(?:\s*[AP]M)?)\s+[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s*$#i", //14:25 Fri 05 Apr 2019
            "#^\s*(\d+:\d+(?:\s*[AP]M)?)\s+[^\d\s]+\s+(\d+)\s+(\d+)\s+月\s+(\d{4})\s*$#i", //13:15 星期一 06 1 月 2020
            "#^\s*(\d{4})\s*年\s*(\w+?)\s*(\d+)\s*日\s*$#iu", //2019年Oct17日
            "#^\s*(\d+:\d+(?:\s*[AP]M)?)\s+\w+\s+\d+\s+(\d+)\s+([[:alpha:]][[:alpha:] ]*[[:alpha:]])\s+(\d{4})\s*$#iu", // 06:40 Thứ 6 01 Tháng mười một 2019
        ];
        $out = [
            "$2 $3 $4, $1",
            "$4-$3-$2, $1",
            "$3 $2 $1",
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        } elseif ($this->lang === 'vi' && preg_match("#\d+\s+([[:alpha:]][[:alpha:] ]*[[:alpha:]])\s+\d{4}#iu", $str,
                $m)
        ) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function amount($price)
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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
