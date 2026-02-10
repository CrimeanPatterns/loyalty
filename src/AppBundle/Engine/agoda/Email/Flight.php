<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "agoda/it-99130627.eml";

    public $detectSubjects = [
        // en, zh
        'Confirmation for Agoda Booking ID',
        // id
        'Konfirmasi untuk ID Pesanan Agoda',
        // no
        'Bekreftelse av Agoda booking-ID',
        // th
        'ยืนยันการจองเที่ยวบิน (หมายเลขการจองของอโกด้า:',
        // ja
        '航空券予約確定のお知らせ - 予約ID：',
        // fr
        'Confirmation de la Réservation Agoda avec ID',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Agoda Booking ID:' => ['Agoda Booking ID:', 'AGODA BOOKING ID:', 'OUTBOUND BOOKING ID:', 'RETURN BOOKING ID:'],
            //            'Airline Reference:' => '',
            'Passenger details' => 'Passenger details',
            //            'First name' => '',
            //            'Contact details' => '',
            //            'Ticket Number:' => '',
            'Base fare' => ['Base fare', 'Flight + Hotel'],
            //            'Taxes and fees' => '',
            //            'Regular discount' => '',
            //            'Total' => '',
            // Hotel
            //            'Reservations:' => '',
            //            'Room' => '',
            //            'Check in:' => '',
            //            'Check out:' => '',
            //            'Booking ID:' => '',
            //            'Occupancy:' => '',
            //            'Cancellation and Change Policy' => '',
        ],
        "id" => [
            'Agoda Booking ID:'  => ['ID PESANAN AGODA:'],
            'Airline Reference:' => 'Kode booking (PNR):',
            'Passenger details'  => 'Informasi penumpang',
            'First name'         => 'Nama depan',
            //            'Contact details' => '',
            //            'Ticket Number:' => '',
            'Base fare'      => 'Tarif dasar',
            'Taxes and fees' => 'Pajak dan biaya lainnya',
            //            'Regular discount' => '',
            'Total'          => 'Total harga',
            // Hotel
            //            'Reservations:' => '',
            //            'Room' => '',
            //            'Check in:' => '',
            //            'Check out:' => '',
            //            'Booking ID:' => '',
            //            'Occupancy:' => '',
            //            'Cancellation and Change Policy' => '',
        ],
        "no" => [
            'Agoda Booking ID:'  => ['AGODA BOOKING-ID:'],
            'Airline Reference:' => 'Flyselskapets referanse:',
            'Passenger details'  => 'Passasjerer',
            'First name'         => 'Fornavn:',
            //            'Contact details' => '',
            'Ticket Number:'     => 'Billettnummer:',
            'Base fare'          => 'Grunnpris',
            'Taxes and fees'     => 'Skatter og avgifter',
            //            'Regular discount' => '',
            'Total'              => 'Til sammen:',
            // Hotel
            //            'Reservations:' => '',
            //            'Room' => '',
            //            'Check in:' => '',
            //            'Check out:' => '',
            //            'Booking ID:' => '',
            //            'Occupancy:' => '',
            //            'Cancellation and Change Policy' => '',
        ],
        "th" => [
            'Agoda Booking ID:'  => ['หมายเลขการจองของอโกด้า:'],
            'Airline Reference:' => 'หมายเลขอ้างอิงของสายการบิน:',
            'Passenger details'  => 'รายละเอียดผู้โดยสาร',
            'First name'         => 'ชื่อ',
            //            'Contact details' => '',
            'Ticket Number:'     => 'หมายเลขบัตรโดยสาร:',
            'Base fare'          => 'ค่าโดยสาร',
            'Taxes and fees'     => 'ภาษีและค่าธรรมเนียม',
            //            'Regular discount' => '',
            'Total'              => 'ยอดรวมทั้งสิ้น',
            // Hotel
            //            'Reservations:' => '',
            //            'Room' => '',
            //            'Check in:' => '',
            //            'Check out:' => '',
            //            'Booking ID:' => '',
            //            'Occupancy:' => '',
            //            'Cancellation and Change Policy' => '',
        ],
        "ja" => [
            'Agoda Booking ID:'  => ['アゴダ予約ID：'],
            'Airline Reference:' => '航空会社リファレンス番号：',
            'Passenger details'  => '【お客様情報】',
            'First name'         => '名（ローマ字）：',
            //            'Contact details' => '',
            'Ticket Number:'     => '航空券番号：',
            'Base fare'          => '航空券代金：',
            'Taxes and fees'     => '空港諸税・燃油サーチャージ等：',
            //            'Regular discount' => '',
            'Total'              => '最終合計金額：',
            // Hotel
            //            'Reservations:' => '',
            //            'Room' => '',
            //            'Check in:' => '',
            //            'Check out:' => '',
            //            'Booking ID:' => '',
            //            'Occupancy:' => '',
            //            'Cancellation and Change Policy' => '',
        ],
        "zh" => [
            'Agoda Booking ID:'  => ['AGODA預訂編號:'],
            'Airline Reference:' => '航空公司參考編號：',
            'Passenger details'  => '乘客資料',
            'First name'         => '名字',
            //            'Contact details' => '',
            'Ticket Number:'     => '機票號碼：',
            'Base fare'          => '機票+住宿',
            'Taxes and fees'     => '稅項及其他費用',
            //            'Regular discount' => '',
            'Total'              => '總額',
            // Hotel
            'Reservations:'                  => '預訂細節：',
            'Room'                           => '間房',
            'Check in:'                      => '入住日期：',
            'Check out:'                     => '退房日期：',
            'Booking ID:'                    => '預訂編號：',
            'Occupancy:'                     => '入住人數：',
            'Cancellation and Change Policy' => '取消及修改政策',
        ],
        "fr" => [
            'Agoda Booking ID:'  => ['N° DE RÉSERVATION AGODA :'],
            'Airline Reference:' => 'Référence de la compagnie aérienne :',
            'Passenger details'  => 'Informations passager(s)',
            'First name'         => 'Prénom',
            'Contact details'    => 'Coordonnées',
            'Ticket Number:'     => 'Numéro de billet :',
            'Base fare'          => 'Tarif de base',
            'Taxes and fees'     => 'Taxes et frais',
            //            'Regular discount' => '',
            'Total'              => 'Total',
            // Hotel
            //            'Reservations:' => '',
            //            'Room' => '',
            //            'Check in:' => '',
            //            'Check out:' => '',
            //            'Booking ID:' => '',
            //            'Occupancy:' => '',
            //            'Cancellation and Change Policy' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@agoda.com') !== false) {
            foreach ($this->detectSubjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.agoda.com')] | //text[contains(normalize-space(), 'Agoda Company Pte')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (isset($dict['Passenger details']) && $this->http->XPath->query("//*[" . $this->contains($dict['Passenger details']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@agoda.com') !== false;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmations = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Agoda Booking ID:'))}]", null, "/{$this->opt($this->t('Agoda Booking ID:'))}\s*(\d+)/"));

        foreach ($confirmations as $confirmation) {
            $f->general()
                ->confirmation($confirmation);
        }
        $f->general()
            ->travellers(preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1',
                $this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger details")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("First name")) . "]/preceding::text()[normalize-space()][1][not({$this->contains('Contact details')})]")), true);

        $infants = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1',
            $this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger details")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("First name")) . "][ancestor::*[count(.//text()[normalize-space()]) > 2][1]//text()[{$this->eq($this->t("Infant"))}]]/preceding::text()[normalize-space()][1][not({$this->contains('Contact details')})]"));

        foreach ($infants as $infant) {
            $f->removeTraveller($infant)
                ->addInfant($infant);
        }

        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger details")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("Ticket Number:")) . "]/following::text()[normalize-space()][1]", null, "#^[A-Z\d\s\-\*Xx\\\/\|]+$#"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_filter(array_unique($tickets)), false);
        }

        $seats = [];
        $seatsText = implode("\n", $this->http->FindNodes("//tr[normalize-space()='Seat selection']/following::text()[normalize-space()][1]/ancestor::table[not(contains(., 'Seat selection'))][last()]//td[not(.//td)]"));
        $seatsText = $this->split("/(?:^|\n) *([A-Z]{3} *[A-Z]{3} *\n)/", $seatsText);

        foreach ($seatsText as $stext) {
            if (preg_match("/^ *([A-Z]{3}) *([A-Z]{3}) *(\n[\s\S]+)/", $stext, $m)) {
                $seats[$m[1] . $m[2]] = $m[3];
            }
        }
        $xpath = "//img[contains(@src, 'airplane')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[1]", $root, true, "/([A-Z\d]{2})\s+\d{1,5}/"))
                ->number($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[1]", $root, true, "/[A-Z\d]{2}\s+(\d{1,5})/"));

            $pnr = $this->http->FindSingleNode("(./preceding::text()[" . $this->starts($this->t("Airline Reference:")) . "])[1]", $root, true, "/:\s*([A-Z\d]{5,7})\s*$/");

            if (!empty($pnr)) {
                $s->airline()
                    ->confirmation($pnr);
            }

            $timeDep = $this->http->FindSingleNode("./ancestor::td[1]/preceding::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/[A-Z]{3}\s*([\d\:]+)/");
            $timeArr = $this->http->FindSingleNode("./ancestor::td[1]/following::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/[A-Z]{3}\s*([\d\:]+)/");

            $dateDep = $this->http->FindSingleNode("./ancestor::td[1]/preceding::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);
            $dateArr = $this->http->FindSingleNode("./ancestor::td[1]/following::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);

            $s->departure()
                ->code($this->http->FindSingleNode("./ancestor::td[1]/preceding::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/([A-Z]{3})\s/"))
                ->date($this->normalizeDate($dateDep . ', ' . $timeDep));

            $s->arrival()
                ->code($this->http->FindSingleNode("./ancestor::td[1]/following::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/([A-Z]{3})\s/"))
                ->date($this->normalizeDate($dateArr . ', ' . $timeArr));

            $duration = $this->http->FindSingleNode("./ancestor::td[1]", $root);

            if (!empty($duration) && stripos($duration, 'h') !== false) {
                $s->extra()
                    ->duration($duration);
            }

            if (!empty($seats) && $s->getDepCode() && $s->getArrCode()
                && !empty($seats[$s->getDepCode() . $s->getArrCode()])
            ) {
                if (preg_match_all("/:\s*(\d{1,3}[A-Z])\s*(?:\n|$|\()/", $seats[$s->getDepCode() . $s->getArrCode()], $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }
    }

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t("Occupancy:"))}]/ancestor::*[{$this->contains($this->t("Check in:"))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            if ($this->http->FindSingleNode("//text()[{$this->starts($this->t('Passenger details'))}]/preceding::text()[{$this->eq($this->t('Total'))}]")
                && empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Passenger details'))}]/following::text()[{$this->eq($this->t('Total'))}]"))
            ) {
                $h = $email->add()->hotel();
                $this->logger->debug('most likely contains hotel');
            }
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("//*[{$this->eq($this->t('Booking ID:'))}]/following-sibling::*[normalize-space()][1]",
                    $root, true, "/^(\d+)$/"))
                ->travellers($email->getItineraries() ? array_column($email->getItineraries()[0]->getTravellers(), 0) : [])
                ->cancellation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation and Change Policy'))}]/following-sibling::tr[normalize-space()][1]"), true, true)
            ;

            $h->hotel()
                ->name($this->http->FindSingleNode("//img[contains(@src, 'hotelimages')]/preceding::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("//img[contains(@src, 'hotelimages')]/following::text()[normalize-space()][1]", $root))
            ;

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("//*[{$this->eq($this->t('Check in:'))}]/following-sibling::*[normalize-space()][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("//*[{$this->eq($this->t('Check out:'))}]/following-sibling::*[normalize-space()][1]", $root)))
                ->rooms($this->http->FindSingleNode("//*[{$this->eq($this->t('Reservations:'))}]/following-sibling::*[normalize-space()][1]",
                    $root, true, "/^\s*(\d+)\s*{$this->opt($this->t('Room'))}/"))
                ->guests($this->http->FindSingleNode("//*[{$this->eq($this->t('Occupancy:'))}]/following-sibling::*[normalize-space()][1]",
                    $root, true, "/^\s*(\d+)/"))
            ;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Passenger details']) && $this->http->XPath->query("//*[" . $this->contains($dict['Passenger details']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseFlight($email);
        $this->ParseHotel($email);

        // Price
        $currency = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*(?:\d[\d\,\.]*)?\s*([A-Z]{3})\b/");

        if (!empty($currency)) {
            $email->price()
                ->currency($currency);
        }
        $cost = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Base fare")) . "]/following::text()[normalize-space()][1]");

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[" . $this->starts(preg_replace("/(.+)/", '$1' . ' (', $this->t("Base fare"))) . "]/following::text()[normalize-space()][1]");
        }

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight + Hotel")) . "]/following::text()[normalize-space()][1]");
        }

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $cost, $m)) {
            $email->price()
                ->cost(PriceHelper::parse($m['amount'], $currency))
            ;
        }
        $tax = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Taxes and fees")) . "]/following::text()[normalize-space()][1]");

        if (empty($tax)) {
            $tax = $this->http->FindSingleNode("//text()[" . $this->starts(preg_replace("/(.+)/", '$1' . ' (', $this->t("Taxes and fees"))) . "]/following::text()[normalize-space()][1]");
        }

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $tax, $m)) {
            $email->price()
                ->tax(PriceHelper::parse($m['amount'], $currency))
            ;
        }
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
            ;
        }
        $discount = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Regular discount")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $discount, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $discount, $m)) {
            $email->price()
                ->discount(PriceHelper::parse($m['amount'], $currency))
            ;
        }

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

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //Sun, 03 Jan, 2021, 15:00
            '#^\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})\,\s*([\d\:]+)$#',
            // 2022年09Jun日（Thu）, 15:00
            '/^\s*(\d{4})\s*年\s*(\d{1,2})([[:alpha:]]{3,})\s*日\D*,\s*(\d{1,2}:\d{2})\s*$/u',
            // 2022年Dec28日 (Wed), 15:55
            '/^\s*(\d{4})\s*年\s*([[:alpha:]]{3,})(\d{1,2})\s*日\D*,\s*(\d{1,2}:\d{2})\s*$/u',
            // May 17, 2021 (after 3:00 PM)
            '#^\s*(\w+)\s+(\d+)\s*,\s*(\d{4})\s*\(\D*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\)\s*$#ui',
            // 2023年1月01日 AM11:00前
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月(\d{1,2})\s*日\s*([ap]m)\s*(\d{1,2}:\d{2})\D*\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2 $3 $1, $4',
            '$3 $2 $1, $4',
            '$2 $1 $3, $4',
            '$1-$2-$3, $5$4',
        ];
        $str = preg_replace($in, $out, $date);
//        $this->logger->debug('$str = '.print_r( $str,true));

        return strtotime($str);
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
