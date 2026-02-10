<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-657881225.eml, fcmtravel/it-658458154.eml, fcmtravel/it-659200670.eml";

    public $lang = '';

    public $detectLang = [
        "en" => ['Order Number'],
        "zh" => ['订单号'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "zh" => [
            'FCM Travel system'   => '统自动发送，请勿直接回复！',
            'Order Number'        => '订单号：',
            'Date of Reservation' => '预订日期：',
            'Status'              => '态：',
            'Payment method'      => '支付方式：',
            'Passenger'           => ['乘客：', '入住人：'],

            //flight
            //'Domestic Air Ticket for Passenger' => '',
            'Airline Booking Reference' => '记录编号：',
            'PNR'                       => 'PNR',
            'Ticket number'             => '票号：',
            //'Please be informed that the following flight' => '',
            //'has been cancelled' => '',
            'Flight details' => '航班信息：',
            //'Operating carrier' => '',
            'Departure Time' => '发时间：',
            'Arrival Time'   => '时间：',
            'Duration'       => '飞行时长：',
            'Meal'           => '餐食：',
            'Miles'          => '飞行里程：',

            //hotel
            'Hotel'                     => '国内酒店',
            'Hotel Confirmation Number' => '认号：',
            'Cancellation'              => '预订政策：',
            'Hotel Info'                => '酒店信息：',
            'Hotel Name'                => '酒店信息：',
            'Phone'                     => '电话：',
            'Address'                   => '地址：',
            'Check In'                  => '时间：',
            'Check Out'                 => '时间：',
            'Room Type'                 => '房型：',
            'Bed Type'                  => '床型：',
            'Room Count'                => '房间数量：',
        ],
    ];

    private $traveller;

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('FCM Travel system'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Order Number'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Date of Reservation'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Status'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Payment method'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]us\.fcm\.travel$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Passenger'))}[：]*\s*(.+)/u");

        if (empty($this->traveller)) {
            $this->traveller = $this->re("/{$this->opt($this->t('Domestic Air Ticket for Passenger'))}\s+(.+)\s+\d{4}\-/", $parser->getSubject());
            $this->traveller = str_replace($this->t('from'), '', $this->traveller);
        }

        if (empty($this->traveller)) {
            $this->traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Domestic Air Ticket for Passenger'))}]", null, true, "/{$this->opt($this->t('Domestic Air Ticket for Passenger'))}(.+)\s+\d{4}\-/");
            $this->traveller = str_replace($this->t('from'), '', $this->traveller);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Airline Booking Reference'))}]")->length > 0) {
            $this->Flight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Hotel'))}]")->length > 0) {
            $this->Hotel($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR'))}]/following::text()[normalize-space()][2]", null, true, "/^([A-Z\d]{6})$/"))
            ->traveller($this->traveller);

        $ticketNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Ticket number'))}]/ancestor::p[1]", null, true, "/^{$this->opt($this->t('Ticket number'))}[：]*\s*([\d\-]{6,})$/");

        if (!empty($ticketNumber)) {
            $f->addTicketNumber($ticketNumber, false);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Please be informed that the following flight'))} and {$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled();
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Airline Booking Reference'))}]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->setConfirmation($this->http->FindSingleNode("./ancestor::p[1]", $root, true, "/[：]*\s*([A-Z\d]{6})$/"));

            $airlineInfo = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Flight details'))}][1]/ancestor::p[1]", $root);

            if (preg_match("/\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})\s+/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            if (preg_match("/{$this->opt($this->t('Operating carrier'))}\s+(?<aCarName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fCarNumber>\d{2,4})/", $airlineInfo, $m)) {
                $s->setCarrierAirlineName($m['aCarName'])
                    ->setCarrierFlightNumber($m['fCarNumber']);
            }

            $flightDateInfo = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Departure Time'))}][1]/ancestor::p[1]", $root);

            if (preg_match("/{$this->opt($this->t('Departure Time'))}\D*\w+\,\s+(?<depDate>\d+\s*\w+\s*\d{4}\s*\d+\:\d+)\s+{$this->opt($this->t('Arrival Time'))}\D*\w+\,\s+(?<arrDate>\d+\s*\w+\s*\d{4}\s*\d+\:\d+)/", $flightDateInfo, $m)
            || preg_match("/{$this->opt($this->t('Departure Time'))}\D*(?<depDate>\d{4}\-.*\d+\:\d+)\D*{$this->opt($this->t('Arrival Time'))}\D*(?<arrDate>\d{4}\-.*\d+\:\d+)/", $flightDateInfo, $m)) {
                $s->departure()
                    ->date(strtotime($m['depDate']));

                $s->arrival()
                    ->date(strtotime($m['arrDate']));
            }

            $airportInfo = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Departure Time'))}][1]/ancestor::p[1]/following::p[1]", $root);

            if (preg_match("/[：]\s*(?<depName>.+)\sT(?<depTerminal>.+)\s+\-\-\s+(?<arrName>.+)\s+T(?<arrTerminal>.+)\s+[（]/u", $airportInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->terminal($m['depTerminal'])
                    ->noCode();

                $s->arrival()
                    ->name($m['arrName'])
                    ->terminal($m['arrTerminal'])
                    ->noCode();
            }

            $duration = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Duration'))}]/ancestor::p[1]", $root, true, "/{$this->opt($this->t('Duration'))}[：]*\s*(.+)/u");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $meal = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Meal'))}]/ancestor::p[1]", $root, true, "/{$this->opt($this->t('Meal'))}[：]*\s*(.+)/u");

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $miles = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Miles'))}]/ancestor::p[1]", $root, true, "/{$this->opt($this->t('Miles'))}[：]*\s*(.+)/u");

            if (!empty($miles)) {
                $s->extra()
                    ->miles($miles);
            }
        }
    }

    public function Hotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel Confirmation Number'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Hotel Confirmation Number'))}\s*([A-Z\d]{6,})$/"))
            ->traveller($this->traveller)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Cancellation'))}\s*(.+)/"));

        $hotelInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel Info'))}]/ancestor::p[1]");

        if (preg_match("/{$this->opt($this->t('Hotel Name'))}\s*(?<hotelName>.+)\s*{$this->opt($this->t('Phone'))}\s*(?<phone>.+)\s*{$this->opt($this->t('Address'))}\s*(?<address>.+)/u", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->phone($m['phone'])
                ->address($m['address']);
        }

        $inOutInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check In'))}]/ancestor::p[1]");

        if (preg_match("/{$this->opt($this->t('Check In'))}\s*(?<in>\d{4}\-\d+\-\d+)\D*{$this->opt($this->t('Check Out'))}\s*(?<out>\d{4}\-\d+\-\d+)\D*/", $inOutInfo, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['in']))
                ->checkOut(strtotime($m['out']));
        }

        $roomInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Room Type'))}]/ancestor::p[1]");

        if (preg_match("/{$this->opt($this->t('Room Type'))}\s*(?<roomType>.+){$this->opt($this->t('Bed Type'))}.*{$this->opt($this->t('Room Count'))}\s*(?<rooms>\d+)/", $roomInfo, $m)) {
            $h->booked()
                ->rooms($m['rooms']);

            $h->addRoom()->setType($m['roomType']);
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
