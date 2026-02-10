<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class It1831920 extends \TAccountCheckerExtended
{
    public $mailFiles = "agoda/it-1831920.eml, agoda/it-48607574.eml, agoda/it-48779159.eml, agoda/it-48983889.eml, agoda/it-554344034.eml, agoda/it-555774483.eml";

    private $subject = ["Agoda Booking ID", "Agoda boekings-ID", "【アゴダ領収書】予約ID", "【Agoda訂單收據】訂單編號", "Customer Receipt from Booking ID"];
    private $body = 'Agoda';
    private $lang = '';
    private $pdfNamePattern = ".*pdf";

    private static $detectors = [
        'en' => ["Receipt", "Customer Name & Address"],
        'ja' => ["領収書", "宿泊者氏名 & 住所", "宿泊者氏名&住所"],
        'zh' => ["收據", "客人姓名& 地址", '收据', '收據'],
        'nl' => ["Kwitantie", "Klantnaam & Adres"],
        'ko' => ["영수증"],
        'de' => ["Buchungsnummer"],
    ];

    private static $dictionary = [
        'ja' => [
            "confNumber"  => ["予約番号"],
            "Charge Date" => ["請求日"],
            "Hotel Name"  => ["ホテル名"],
            "Period"      => ["期間"],
            // "night" => "",
            "Telephone"   => ["電話番号"],
            "Room Type"   => ["部屋タイプ"],
            "GRAND TOTAL" => ["総合金額"],
            "# of Rms."   => ["部屋数"],
            "Name"        => ["名前"],
            //     "Discount" => [""],
            "Total Extra Bed Charge" => "エクストラベッド合計金額",
        ],
        'zh' => [
            "confNumber"  => ["訂單編號", '订单编号', '預訂ID'],
            "Charge Date" => ["入帳日期", "付款日期"],
            "Hotel Name"  => ["預訂住宿名稱", "酒店名称", '酒店名稱'],
            "Period"      => ["入住期間", '入住日期'],
            "night"       => "晚",
            "Telephone"   => ["電話"],
            "Room Type"   => ["房型"],
            "GRAND TOTAL" => ["總計應付金額", "總計"],
            "# of Rms."   => ["房間數"],
            "Name"        => ["姓名"],
            // "Discount" => [""],
            "Total Extra Bed Charge" => ["加床費用", "加床費總額"],
        ],
        'nl' => [
            "confNumber"  => ["Boekingsnummer"],
            "Charge Date" => ["Belastbare Datum"],
            "Hotel Name"  => ["Hotelnaam"],
            "Period"      => ["Periode"],
            // "night" => "",
            "Telephone"   => ["Telefoon"],
            "Room Type"   => ["Kamertype"],
            "GRAND TOTAL" => ["EINDTOTAAL"],
            "# of Rms."   => ["Aantal kamers"],
            "Name"        => ["Naam"],
            //            "Discount" => [""],
            "Total Extra Bed Charge" => "Totale Kosten Extra Bed",
        ],
        'ko' => [
            "confNumber"  => ["예약 번호"],
            "Charge Date" => ["결제일"],
            "Hotel Name"  => ["호텔명"],
            "Period"      => ["기간"],
            "night"       => "박",
            //"Telephone"   => [""],
            "Room Type"   => ["객실 종류"],
            "GRAND TOTAL" => ["총 금액"],
            "# of Rms."   => ["객실 수"],
            "Name"        => ["이름"],
            //            "Discount" => [""],
            "Total Extra Bed Charge" => "총 간이 침대 요금",
        ],
        'de' => [
            "confNumber"  => ["Buchungsnummer"],
            "Charge Date" => ["Zahlungsdatum"],
            "Hotel Name"  => ["Hotelname"],
            "Period"      => ["Zeitraum"],
            // "night" => "",
            //"Telephone"   => [""],
            "Room Type"   => ["Zimmertyp"],
            "GRAND TOTAL" => ["Gesamtbetrag"],
            "# of Rms."   => ["Anzahl Zimmer"],
            "Name"        => ["Name"],
            //            "Discount" => [""],
            "Total Extra Bed Charge" => "Zuschlag für Zusatzbetten",
        ],
        'en' => [
            "confNumber"             => ["Booking No"],
            "Charge Date"            => ["Charge Date", "Payment Date"],
            "Hotel Name"             => ["Hotel Name", "HotelName"],
            "Period"                 => ["Period"],
            // "night" => "",
            "Telephone"              => ["Telephone"],
            "Room Type"              => ["Room Type", "RoomType"],
            "GRAND TOTAL"            => ["GRAND TOTAL"],
            "# of Rms."              => ["# of Rms."],
            "Name"                   => ["Name"],
            "Discount"               => ["Discount"],
            "Total Extra Bed Charge" => "Total Extra Bed Charge",
        ],
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (is_string($sub) && array_key_exists('subject', $headers) && stripos($headers['subject'], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@agoda.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || stripos($textPdf, $this->body) === false) {
                continue;
            }

            if ($this->detectBody($textPdf) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || $this->assignLang($textPdf) !== true) {
                continue;
            }

            if (!empty($htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf)))) {
                $this->parseEmailPdf($email, $htmlPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('It1831920' . ucfirst($this->lang));

        return $email;
    }

    private function detectBody(?string $text): bool
    {
        if (empty($text) || !isset(self::$detectors)) {
            return false;
        }

        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (stripos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $words) {
            if (!is_string($lang) || empty($words["confNumber"])) {
                continue;
            }

            if ($this->strposArray($text, $words["confNumber"]) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function parseEmailPdf(Email $email, $html): void
    {
        $httpComplex = clone $this->http;
        $httpComplex->SetEmailBody(str_replace(['­', ' ', '&#160;', '  '], ' ', $html));

        $xPath = "/following-sibling::p[normalize-space() and normalize-space()!=' '][1]";

        $patterns = [
            'date' => '[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}', // June 19, 2024
        ];

        $r = $email->add()->hotel();

        $rn = $httpComplex->FindSingleNode("//*[" . $this->contains($this->t("confNumber")) . "]" . $xPath, null, true, "/^[-A-Z\d]{5,25}$/");

        if (!empty($rn)) {
            $r->general()
                ->confirmation($rn, $this->t("confNumber")[0]);
        } elseif (preg_match("/^\s*({$this->opt($this->t("confNumber"))}\.?)[:\s]*([-A-Z\d]{5,25})\s*$/", $httpComplex->FindSingleNode("//text()[{$this->starts($this->t("confNumber"))}]"), $m)) {
            $r->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $reservationDate = $httpComplex->FindSingleNode("//*[{$this->contains($this->t('Charge Date'))}]" . $xPath, null, true, "/^\s*({$patterns['date']})\s*$/u");

        if (!empty($reservationDate)) {
            $r->general()
                ->date(strtotime($reservationDate));
        }

        $hotelName = $httpComplex->FindSingleNode("//*[" . $this->contains($this->t('Hotel Name')) . "]" . $xPath);

        if (!empty($hotelName)) {
            $r->hotel()->name($hotelName)->noAddress();
        }

        $travellerName = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('Name')) . "]" . $xPath);

        if (!empty($travellerName)) {
            $r->general()
                ->traveller($travellerName, true);
        }

        $datesVal = $httpComplex->FindSingleNode("//*[{$this->contains($this->t('Period'))}]" . $xPath);

        if (preg_match("/^(?<date1>{$patterns['date']})(?:\s*[-–]\s*|\s+)(?<date2>{$patterns['date']})(?:[(\s]*\d{1,3}\s*(?:[[:alpha:]]|{$this->opt($this->t('night'))}|night)|$)/iu", preg_replace('/[^\w\d\s,.()]/u', ' ', $datesVal), $m)) {
            // December 20, 2014 ­ December 23, 2014 3 night(s)    |    December 20, 2014 -­ December 23, 2014 (3 night(s))
            $r->booked()->checkIn2($m['date1'])->checkOut2($m['date2']);
        }

        $phone = $httpComplex->FindSingleNode("//*[" . $this->contains($this->t('Telephone')) . "]" . $xPath . "[not({$this->contains($this->t('Tax'))})]");

        if (!empty($phone)) {
            $r->hotel()
                ->phone($phone);
        }

        $roomType = $httpComplex->FindSingleNode("//*[" . $this->contains($this->t('Room Type')) . "]" . $xPath);

        if (!empty($roomType)) {
            $r->addRoom()
                ->setType($roomType);
        }

        $roomCount = $httpComplex->FindSingleNode("//*[" . $this->contains($this->t('# of Rms.')) . "]" . $xPath);

        if (!empty($roomCount)) {
            $r->booked()
                ->rooms($roomCount);
        }

        $totalPrice = $httpComplex->FindSingleNode("//*[{$this->eq($this->t('GRAND TOTAL'))}]" . $xPath, null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/\(\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*\)$/u', $totalPrice, $matches)
        ) {
            // USD 172.64    |    KRW 234,236 (USD 172.64)
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $discount = $httpComplex->FindSingleNode("//*[{$this->starts($this->t('Discount'))}]" . $xPath, null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*[-][ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $discount, $m)) {
                // USD -79.82
                $r->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $fee = $httpComplex->FindSingleNode("//*[{$this->starts($this->t('Total Extra Bed Charge'))}]" . $xPath, null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $fee, $m)) {
                $feeName = $httpComplex->FindSingleNode("//*[{$this->starts($this->t('Total Extra Bed Charge'))}]", null, true, '/^(.+?)[\s:：]*$/u');
                $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
