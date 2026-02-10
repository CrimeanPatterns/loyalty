<?php

namespace AwardWallet\Engine\adtrav\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "adtrav/it-207275991.eml, adtrav/it-207991805.eml, adtrav/it-208353259.eml";
    public $subjects = [
        'TICKET(S) ISSUED Itinerary for',
        'AWAITING TICKETING Itinerary for',
        'CANCELED Itinerary for',
        'AIRLINE SCHEDULE CHANGE on Itinerary for',
    ];

    public $lang = 'en';
    public $year;
    public $currentYear;
    public $traveller;

    public static $dictionary = [
        "en" => [
            'Primary Traveler' => ['Primary Traveler', 'Traveler'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@adtrav.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'ADTRAV Travel Management')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Alerts & Notices'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Team Contact Information'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]adtrav\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Airline Booking Reference:')]/preceding::text()[normalize-space()='Depart']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $f = $email->add()->flight();

            if ($this->http->XPath->query("//text()[normalize-space()='Reservation Cancelled']")->length > 0) {
                $f->general()
                    ->cancelled()
                    ->status('cancelled');
            }

            $f->general()
                ->confirmation($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Airline Booking Reference:']/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{6})$/"))
                ->traveller($this->traveller, true);

            $ticket = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Ticket # :']/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d{5,})/");

            if (!empty($ticket)) {
                $f->issued()
                    ->ticket($ticket, false);
            }

            $s = $f->addSegment();

            $tempYear = $this->http->FindSingleNode("./preceding::text()[normalize-space()='Check In'][1]/preceding::text()[normalize-space()][3]", $root);

            if (preg_match("/^.*\s+(\d{4})/", $tempYear, $m)) {
                $this->year = $this->currentYear = $m[1];
            } elseif (preg_match("/^.*N\/A/", $tempYear, $m)) {
                $this->year = $this->currentYear;
            }

            $s->setStatus($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Status:']/following::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Segment'))}\s*(\w+)/"));

            $s->airline()
                ->name($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[normalize-space()][2]", $root, true, "/\s*([A-Z\d]{2})\s+\d{2,4}/"))
                ->number($this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[normalize-space()][2]", $root, true, "/\s*[A-Z\d]{2}\s+(\d{2,4})/"));

            $depDate = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[contains(normalize-space(), ':')][1]", $root);
            $s->departure()
                ->date($this->normalizeDate($depDate))
                ->code($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][last()]/ancestor::p[1]", $root, true, "/^\s*([A-Z]{3})\s+/"));

            $depTerminalText = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][last()]/ancestor::*[1]", $root);

            if (preg_match("/{$this->opt($this->t('TERMINAL'))}\s*([\w\.]+)\s*$/", $depTerminalText, $m)
             || preg_match("/\s*([\w\.]+)\s*{$this->opt($this->t('TERMINAL'))}\s*$/", $depTerminalText, $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            $arrDate = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[contains(normalize-space(), ':')][1]", $root);
            $s->arrival()
                ->date($this->normalizeDate($arrDate))
                ->code($this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][last()]/ancestor::p[1]", $root, true, "/^\s*([A-Z]{3})\s+/"));

            $arrTerminalText = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][last()]/ancestor::*[1]", $root);

            if (preg_match("/{$this->opt($this->t('TERMINAL'))}\s*([\w\.]+)\s*$/", $arrTerminalText, $m)
                || preg_match("/\s*([\w\.]+)\s*{$this->opt($this->t('TERMINAL'))}\s*$/", $arrTerminalText, $m)) {
                $s->arrival()
                    ->terminal($m[1]);
            }

            $seats = explode(",", $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/{$this->opt($this->t('Seat:'))}\s*([\dA-Z\,\s]+)$/"));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            $cabinText = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Class:']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(.+)\s+\(([A-Z])\)$/", $cabinText, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }

            $aircraft = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Equipment:']/following::text()[normalize-space()][1]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $meal = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Meal:']/following::text()[normalize-space()][1]", $root);

            if (!empty($meal) && stripos($meal, 'N/A') === false) {
                $s->extra()
                    ->meal($meal);
            }

            $flightInfo = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Flight Info:']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Stops:'))}\s*(?<stops>\d)\,\s*{$this->opt($this->t('Time:'))}\s*(?<duration>[\d\:\.]+)\,\s*{$this->opt($this->t('Miles:'))}\s*(?<miles>\d+)\s*$/", $flightInfo, $m)) {
                $s->extra()
                    ->stops($m['stops'])
                    ->duration($m['duration'])
                    ->miles($m['miles']);
            }

            $accounts = array_filter(explode(",", $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Frequent Flyer:']/following::text()[normalize-space()][1]", $root, true, "/([\d\,\s]+)/")));

            if (count($accounts) > 0) {
                $f->setAccountNumbers($accounts, false);
            }
        }
    }

    public function ParseRental(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Car Info:')]/preceding::text()[normalize-space()='Pick Up']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Confirmation:']/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{10,})/"))
                ->traveller($this->traveller, true);

            $account = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Personal Membership:']/following::text()[normalize-space()][1]", $root, true, "/^(\d+)$/");

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }

            $company = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][1]", $root);
            $pickUpLocationText = implode("\n", $this->http->FindNodes("./preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));
            /*National Car Rental
              ATLANTA HARTSFIELD AIRPORT ,
              2200 RENTAL CAR CNTR PKWY ,
              COLLEGE PARK GA 30337-0000 ,
              404-530-2800*/

            if (stripos($pickUpLocationText, 'Location:') !== false) {
                $pickUpLocationText = preg_replace("/($company)(.+{$this->opt($this->t('Location:'))})/su", "$1\n", $pickUpLocationText);
            } elseif (stripos($pickUpLocationText, 'Arrival Flight Info:') !== false) {
                $pickUpLocationText = preg_replace("/($company)(.+{$this->opt($this->t('Arrival Flight Info:'))}\s*\d{2,4})/su", "$1\n", $pickUpLocationText);
            }

            if (preg_match("/$company\n(?<location>.+\d{5}\-\d{4})\s*\,\s*\n*(?<phone>[\d\-\s]{6,})$/msu", $pickUpLocationText, $m)
            || preg_match("/National Car Rental\n(?<location>(?:.+\n){1,3})(?<phone>[\d\s]+)$/msu", $pickUpLocationText, $m)) {
                $r->setCompany($company);

                $r->pickup()
                    ->location(str_replace("\n", "", $m['location']))
                    ->phone($m['phone']);
            }

            $pickUpDate = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Pick Up'))][1]", $root, true, "/^(.+)\s+A?P?M/");
            $r->pickup()
                ->date($this->normalizeDate($pickUpDate));

            $dropOffDate = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Drop Off'))][1]", $root, true, "/^(.+)\s+A?P?M/");
            $r->dropoff()
                ->date($this->normalizeDate($dropOffDate));

            $r->car()
                ->type($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Car Info:']/following::text()[normalize-space()][1]", $root));

            $dropOffText = $this->http->FindSingleNode("./following::text()[normalize-space()='Drop Off Info:'][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Drop Off Info:'))}\s+(.+)/");

            if (stripos($dropOffText, 'Same as Pickup location') !== false) {
                $r->dropoff()
                    ->same();
            } elseif (preg_match("/^(?<location>.+\d{5}\-\d{4})\,\s*(?<phone>[\d\-]{12,})?$/", $dropOffText, $m)) {
                $r->dropoff()
                    ->location($m['location']);

                if (isset($m['phone'])) {
                    $r->dropoff()
                        ->phone($m['phone']);
                }
            }

            $priceText = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Estimated Total Cost:']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<total>[\d\,\.]+)\s*(?<currency>[A-Z]{3})$/u", $priceText, $m)) {
                $r->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'Number Of Rooms')]/preceding::text()[normalize-space()='Check In'][1]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->traveller($this->traveller, true);

            $confirmation = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()='Confirmation:']/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d+)\s*$/");

            if (!empty($confirmation)) {
                $h->general()
                    ->confirmation($confirmation);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            $hotelNameText = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<hotelName>[A-z\d\s\-]+)\,(?<address>.+)\,\s*{$this->opt($this->t('Phone:'))}\s*(?<phone>[\d\-\s]+)\s*(?:{$this->opt($this->t('Fax:'))})?\s*(?<fax>[\d\-\s]+)?$/", $hotelNameText, $m)) {
                $h->hotel()
                    ->name($m['hotelName'])
                    ->address($m['address'])
                    ->phone($m['phone']);

                if (isset($m['fax'])) {
                    $h->hotel()
                        ->fax($m['fax']);
                }
            }

            $tempYear = $this->http->FindSingleNode("//text()[{$this->eq($h->getHotelName())}]/preceding::text()[normalize-space()][1]", null, true, "/^.*\s(\d{4})$/");

            if (!empty($tempYear)) {
                $this->year = $tempYear;
            }

            $checkInDate = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][2]", $root);
            $h->booked()
                ->checkIn($this->normalizeDate($checkInDate));

            $checkOutDate = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][2]", $root);
            $h->booked()
                ->checkOut($this->normalizeDate($checkOutDate));

            $otherInfoText = $this->http->FindSingleNode("./following::tr[1]", $root);
            $guests = $this->re("/{$this->opt($this->t('Number Of Guests:'))}\s*(\d+)/", $otherInfoText);

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $rooms = $this->re("/{$this->opt($this->t('Number Of Rooms:'))}\s*(\d+)/", $otherInfoText);

            if (!empty($rooms)) {
                $h->booked()
                    ->rooms($rooms);
            }

            $roomDescription = $this->re("/{$this->opt($this->t('Room Description:'))}\s*(.+)\s[•].+[•]\s+[A-Z]?\s*{$this->opt($this->t('CANCEL BY'))}/", $otherInfoText);
            $roomRate = $this->re("/{$this->opt($this->t('Per Diem Rate:'))}\s*([\d\.\,]+)/", $otherInfoText);

            if (!empty($roomDescription) || !empty($roomRate)) {
                $room = $h->addRoom();

                if (!empty($roomDescription)) {
                    $room->setDescription($roomDescription);
                }

                if (!empty($roomRate)) {
                    $room->setRate($roomRate . ' / night');
                }
            }

            $cancellation = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Other Info:')][1]/ancestor::tr[1]/following::tr[normalize-space()][1][contains(normalize-space(), 'CANCELLED')]", $root);

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);

                $this->detectDeadLine($h);
            }

            if (preg_match("/{$this->opt($this->t('Estimated Total Cost:'))}\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $otherInfoText, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //it-207991805.eml
        if ($this->http->XPath->query("//text()[normalize-space()='Awaiting Ticketing']")->length > 0
        || $this->http->XPath->query("//text()[normalize-space()='Pending']")->length > 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->year = $this->http->FindSingleNode("//text()[normalize-space()='Trip Start Date']/ancestor::tr[1]/descendant::td[2]", null, true, "/(\d{4})$/");
        $this->traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Primary Traveler'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-z\s]+)/");

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Locator:']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"));

        $emailPriceText = $this->http->FindSingleNode("//text()[normalize-space()='Total Quote']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.,]+)$/u", $emailPriceText, $m)) {
            if ($m['currency'] == '$' && $this->http->XPath->query("//text()[contains(normalize-space(), 'USD')]")->length > 0) {
                $m['currency'] = 'USD';
            }

            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Airline Booking Reference:')]")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Car Info:')]")->length > 0) {
            $this->ParseRental($email);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Number Of Rooms')]")->length > 0) {
            $this->ParseHotel($email);
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function normalizeDate($str)
    {
        $year = $this->year;
        $in = [
            //Oct 13 - 6:15 PM
            "#^(\w+)\s+(\d+)[\s\-]+([\d\:]+\s*A?P?M?)$#ui",
            //Oct 13
            "#^\w+[\s\-]*(\w+)\s+(\d+)$#ui",
        ];
        $out = [
            "$2 $1 $year, $3",
            "$2 $1 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\w+)\s+\d{4}#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^HOTEL MUST BE CANCELLED BY (?<time>[\d\:]+\s*A?P?M) OF (?<date>10\/19\/2022) TO AVOID CHARGES$/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ',' . $m['time']));
        }
    }
}
