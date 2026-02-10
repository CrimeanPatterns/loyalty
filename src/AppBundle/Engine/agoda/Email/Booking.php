<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "agoda/it-188218301.eml, agoda/it-60061917.eml, agoda/it-60184036.eml";
    public $reFrom = "agoda.com";
    public $reBody = [
        'vi' => ['Booking ID', 'Loại Phòng'],
        'en' => ['Booking ID', 'Customer First Name'],
    ];
    public $reSubject = [
        '#Agoda Booking ID \d+ - #',
    ];
    public $lang = 'en';
    public static $dict = [
        'vi' => [
            'CancelledStatus'      => 'Cancellation Charge:',
            'To Property'          => ['To Property', 'Booked and Payable by'],
            'Booking ID'           => ['Mã số đặt phòng'],
            'Booking confirmation' => ['Xác nhận đặt phòng'],
            'Check-in'             => ['Nhận phòng'],
            'Check-out'            => ['Trả phòng'],
            'Room Type'            => 'Loại Phòng',
            'No. of Rooms'         => 'Số phòng',
            'Occupancy'            => 'Số người',
            'Reference sell rate'  => 'Giá bán tham khảo',
            'From'                 => 'Đến',
            'Rate Plan name:'      => 'Tên chính sách giá:',
        ],
        'en' => [
            'CancelledStatus' => 'Cancellation Charge:',
            'To Property'     => ['To Property', 'Booked and Payable by'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $email->setSentToVendor(true);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'agoda.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);            // 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);    // 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00		->	18800.00

        return $string;
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID'))}]/following::text()[normalize-space()][1]", null, true, "/(\d+)/");
        $descConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID'))}]");
        $h->general()
            ->confirmation($conf, $descConf);

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Amended Booking Confirmation'))}]"))) {
            $h->general()
                ->status('changed');
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking confirmation'))}]"))) {
            $h->general()
                ->status('confirmed');
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('(Property ID'))}]/preceding::text()[normalize-space()][1]");

        if (!empty($hotelName)) {
            $h->hotel()
                ->name($hotelName);
        }

        $country = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Country of Residence'))}]/following::text()[normalize-space()][1]");
        $city = $this->http->FindSingleNode("//text()[{$this->starts($this->t('City'))}]/following::text()[normalize-space()][1]");

        if (!empty($country) || !empty($city)) {
            $h->hotel()
                ->address(trim($country . ', ' . trim($city, ':'), ','));
        }

        $fName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Customer First Name'))}]/following::text()[normalize-space()][1]");
        $lName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Customer Last Name'))}]/following::text()[normalize-space()][1]");
        $h->general()
            ->traveller($fName . ' ' . $lName, true);

        $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space()][1]");
        $timeIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ArrivalTime:'))}]", null, true, "/([\d\:]+)\s*\-/");
        $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space()][1]");

        if (!empty($timeIn)) {
            $h->booked()
                ->checkIn($this->normalizeDate($checkIn . ', ' . $timeIn))
                ->checkOut($this->normalizeDate($checkOut));
        } else {
            $h->booked()
                ->checkIn($this->normalizeDate($checkIn))
                ->checkOut($this->normalizeDate($checkOut));
        }

        $room = $h->addRoom();

        $room->setType($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/ancestor::tr[1]/following::tr[1]/td[1]"))
            ->setRateType($this->http->FindSingleNode("//text()[{$this->starts($this->t('Rate Plan name:'))}]", null, true, "/\:(\D+)/"));

        $xpath = "//text()[{$this->starts($this->t('Reference sell rate'))}]/ancestor::tr[1]/preceding-sibling::tr[not({$this->contains($this->t('From'))})]";
        $node = $this->http->XPath->query($xpath);
        $rate = '';

        if (count($node) > 0) {
            $nights = 0;

            if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                $nights = date_diff(
                    date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                    date_create('@' . strtotime('00:00', $h->getCheckInDate()))
                )->format('%a');

                $rates = [];

                foreach ($node as $root) {
                    // $rateName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                    // $rateSumm = $this->http->FindSingleNode("./descendant::td[2]", $root);
                    // $rate .= $rateName . ': ' . $rateSumm . '; ';
                    if (!empty($this->http->FindSingleNode("./descendant::td[1]", $root, null, "/\b20\d{2}\b/"))) {
                        $rates[] = $this->http->FindSingleNode("./descendant::td[2]", $root);
                    }
                }

                if (count($rates) == $nights) {
                    $room->setRates($rates);
                }
                // $room->setRate($rate);
            }
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/ancestor::tr[1]/following::tr[1]/td[2]"))
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/ancestor::tr[1]/following::tr[1]/td[3]", null, true, "/(\d+)\s+Adults?/"));

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reference sell rate'))}]//following::text()[normalize-space()][2]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('To Property'))}]/preceding::text()[normalize-space()][1]", null, true, "/\s([\d\.\,]+)/");
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reference sell rate'))}]//following::text()[normalize-space()][1]");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('To Property'))}]/preceding::text()[normalize-space()][1]", null, true, "/^\s*([A-Z]{3})\s/");
        }

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total($this->normalizePrice($total))
                ->currency($currency);
        }

        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]");

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);

            $this->detectDeadLine($h, $cancellationPolicy);
        }
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectDeadLine(Hotel $h, $cancellationText)
    {
        if (preg_match("#Any cancellation received within (?<prior>\d+ days?) prior to arrival#i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior']);
        }

        if (preg_match("#You can cancel until\s*(\w+\s*\d+\,\s*\d{4})\s*and pay nothing#i", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        if (preg_match("#This booking is Non-Refundable and cannot be amended or modified#i", $cancellationText, $m)) {
            $h->booked()
                ->nonRefundable();
        }
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
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = ' . print_r($date, true));

        $in = [
            // 26-Aug-2022 (26-08-2022)
            "/^(\d+)\-(\w+)\-(\d{4}).*$/",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
