<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Voucher extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-334657360.eml, ctrip/it-35201459.eml, ctrip/it-35201572.eml";

    public $reBody = [
        'en' => ['Check-in Voucher', 'Payment Method'],
        'zh' => ['入住憑證', '付款方法'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'Booking No./'    => ['Booking No./', 'Booking No.:'],
            'Address:'        => 'Address:',
            'Number of Rooms' => ['Number of Rooms', 'Rooms'],
        ],
        'zh' => [
            'Booking No./'              => '訂單編號/',
            'Address:'                  => '地址：',
            'Telephone:'                => '電話：',
            'Guest Names'               => '住客姓名',
            'Hotel Confirmation Number' => '酒店確認編號',
            'Check-in'                  => '入住時間',
            'Check-out'                 => '退房時間',
            'Number of Rooms'           => '房間數目',
            'Max. Occupancy'            => '最多入住人數',
            'Room Type'                 => '房型',
            'Meals'                     => '餐膳',
            'Bed Type'                  => '床型',
            'Window'                    => '窗戶',
            'Cancellation Policy:'      => '取消政策:',
            'Contact Us'                => '聯絡我們',
        ],
        //need example Address:, Telephone:, Cancellation Policy:
        //        'ja' => [//should be last lang
        //            'Booking No./' => '予約番号',
        //            'Address:'=>'',
        //            'Telephone:'=>'',
        //            'Guest Names'=>'宿泊者名',
        //            'Hotel Confirmation Number'=>'ホテル確認番号',
        //            'Check-in'=>'チェックイン',
        //            'Check-out'=>'チェックアウト',
        //            'Number of Rooms'=>'客室数',
        //            'Max. Occupancy'=>'定員',
        //            'Room Type'=>'客室タイプ',
        //            'Meals'=>'食事',
        //            'Bed Type'=>'ベッドタイプ',
        //            'Window'=>'窓',
        //            'Cancellation Policy:'=>''
        //        ]
    ];

    private $detectFrom = ['@trip.com', '@ctrip.com'];
    private $detectSubject = [
        'Voucher of',
    ];

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

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[' . $this->contains([".trip.com", "@trip.com", ".c-ctrip.com", ".ctrip.com", "@ctrip.com"], '@href') . ']')->length === 0) {
            return false;
        }

        if ($this->detectBody() && $this->assignLang()
        ) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $info = $this->http->FindNodes("//text()[{$this->eq($this->t('Contact Us'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!='']",
            null, "#(.+:\s+[\d\(\)\-\+ ]+)[;\s]*$#u");
        $phones = [];

        foreach ($info as $phone) {
            if (preg_match("#(.+):\s+([\d\(\)\-\+ ]+)$#u", $phone, $m)) {
                if (!in_array($m[2], $phones)) {
                    $r->ota()
                        ->phone($m[2], $m[1]);
                }
            }
        }
        $r->general()
            ->confirmation(
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking No./'))}]/following::text()[normalize-space()!=''][1]"),
                trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking No./'))}]"), "//::")
            )
            ->traveller(
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Names'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"),
                true
            );

        $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy:'))}]/ancestor::tr[1]",
            null, false, "#{$this->opt($this->t('Cancellation Policy:'))}\s+(.+)#");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Fee']/following::text()[normalize-space()='Free Cancellation']/ancestor::tr[1]");
        }

        if (!empty($cancellation)) {
            $r->general()
                ->cancellation($cancellation);
        }

        if (!empty($hotelConfNoText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Confirmation Number'))}]"))) {
            $r->general()
                ->confirmation(
                    $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Confirmation Number'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"),
                    $hotelConfNoText,
                    true
                );
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Telephone:'))}]", null, false,
            "#{$this->opt($this->t('Telephone:'))}\s*(.+)#");

        if (stripos($phone, ';') !== false) {
            $phone = $this->re("/^(.+)\;/", $phone);
        }

        $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address:'))}]", null, false,
            "#{$this->opt($this->t('Address:'))}\s*(.+)#");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address:'))}]/ancestor::tr[1]", null, false,
                "#{$this->opt($this->t('Address:'))}\s*(.+)#");
        }

        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Address:'))}]/preceding::text()[normalize-space()!=''][1]"))
            ->address($address)
            ->phone($phone);
        $checkIn = implode(" ",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Check-in'))}]/ancestor::table[1]/descendant::tr[normalize-space()!=''][position()>1]"));
        $checkOut = implode(" ",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::table[1]/descendant::tr[normalize-space()!=''][position()>1]"));

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Max. Occupancy'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", null, true, "/^(\d+)$/");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Max. Occupancy'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", null, true, "/\s(\d+)\s*{$this->opt($this->t('adult'))}/");
        }

        $rooms = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of Rooms'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        $r->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
            ->guests($guests)
            ->rooms($rooms);

        $room = $r->addRoom();
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (preg_match("#(?<roomType>.+?)[\-\s]+{$this->opt($this->t('Check in from'))}\s+(?<in>\d+:\d+)[\-\s]+{$this->opt($this->t('Check out before'))}\s+(?<out>\d+:\d+)#",
            $roomType, $m)) {
            $room
                ->setType($m['roomType']);
            $r->booked()
                ->checkIn(strtotime($m['in'], $r->getCheckInDate()))
                ->checkOut(strtotime($m['out'], $r->getCheckOutDate()));
        } else {
            $room
                ->setType($roomType);
        }

        $roomDescription[] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Meals'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
        $roomDescription[] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Bed Type'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
        $roomDescription[] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Window'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
        $room->setDescription(implode("; ", array_filter($roomDescription)));

        $this->detectDeadLine($r);

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //24 2019年4月 チェックイン : 2019年4月24日  |  27 2019年4月 チェックアウト : 2019年4月27日
            '#^(\d+)\s+(\d{4})\s*年\s*(\d+)\s*月\s*(?:(?:チェックイン|チェックアウト).*)?$#u',
            //4 April 2019 チェックイン : 2019年4月4日  |  8 April 2019 チェックアウト : 2019年4月8日
            '#^(\d+)\s+(\w+)\s+(\d{4})\s*(?:(?:チェックイン|チェックアウト).*)?$#u',
            //Apr 2, 2019, 23:59
            '#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)\s*$#u',
            //2019年4月16日23:00
            '#^\s*(\d+)年(\d+)月(\d+)日(\d+:\d+)\s*$#u',
            //15:00–Next day00:00 20 Dec 2023
            '#^([\d\:]+)\–Next day[\d\:]+\s*(\d+)\s*(\w+)\,?\s*(\d{4}).*$#u',
            //Before 12:00 21 Dec 2023
            '#^(?:Before|After)\s*([\d\:]+)\s*(\d+)\s*(\w+)\,?\s*(\d{4})$#u',
            //14:00–23:00 27 Apr 2023 เช็คอิน:27 เม.ย. 2023
            '#^([\d\:]+)\–[\d\:]+\s*(\d+)\s*(\w+)\,?\s*(\d{4}).*$#u',
            //Before 12:00 2 May 2023 เช็คเอาท์:2 พ.ค. 2023
            '#^(?:Before|After)\s*([\d\:]+)\s*(\d+\s*\w+\s*\d{4}).*$#',
            //14:00–Next day00:00 Apr 3, 2023
            '#^([\d\:]+)\–(?:Next day)?[\d\:]+\s*(\w+)\s*(\d+)\,\s*(\d{4}).*$#',
            //Before 12:00 Apr 8, 2023
            '#^(?:Before|After)\s*([\d\:]+)\s*(\w+)\s*(\d+)\,\s*(\d{4}).*$#',
        ];
        $out = [
            '$2-$3-$1',
            '$1 $2 $3',
            '$2 $1 $3, $4',
            '$3.$2.$1, $4',
            '$2 $3 $4, $1',
            '$2 $3 $4, $1',
            '$2 $3 $4, $1',
            '$2, $1',
            '$3 $2 $4, $1',
            '$3 $2 $4, $1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#You may cancel or change for free before (?<date>.+) \(hotel's local time\)#i",
                $cancellationText, $m)
            || preg_match("#（酒店當地時間）(?<date>.+)前可免費取消或更改訂單。#iu",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));
        }

        if (preg_match("/Before\s*(?<day>\d+)\s*(?<month>\w+)\,?\s*(?<year>\d{4})\,\s*(?<time>[\d\:]+) Free Cancellation/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        }

        $h->booked()
            ->parseNonRefundable("#訂單確認後不可取消或更改。#");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Booking No./"], $words["Address:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking No./'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Address:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
