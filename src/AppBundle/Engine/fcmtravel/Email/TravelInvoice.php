<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelInvoice extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-136355899.eml, fcmtravel/it-249113400.eml, fcmtravel/it-255233870.eml";
    public $subjects = [
        '/Travel Invoice for\s*\D+Traveling on.+\([A-Z\d]{6}\)\s*$/u',
        '/Your request to apply an Unused Ticket could not be fulfilled on Record Locator: [A-Z\d]{6}\s*$/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '.fcm.travel') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'FCM Travel Solutions')]")->length > 0) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'HOTEL')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Travel Summary - Record'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket Detail'))}]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AIR')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Depart:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Duration:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Airport Map'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:us|corp)\.fcm\.travel$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'HOTEL')]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $paxText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveler')]/ancestor::table[1]/descendant::text()[normalize-space()]"));

            $h->general()
                ->travellers(array_filter(explode("\n", $this->re("/Traveler\n(.+)Date/s", $paxText))))
                ->confirmation($this->http->FindSingleNode("./following::text()[normalize-space()='Confirmation:'][1]/following::text()[normalize-space()][1]", $root, true, "/\s*(\d{7,})/"))
                ->cancellation($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Cancellation Policy:')][1]/ancestor::tr[1]/descendant::td[2]", $root));

            $h->hotel()
                ->name($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Address:')][1]/ancestor::tr[1]/descendant::td[2]", $root))
                ->phone($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Tel:')][1]/ancestor::tr[1]/descendant::td[2]", $root));

            $dateText = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check In/Check Out:')][1]/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/(.+)\s+\-\s+(.+)/", $dateText, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1]))
                    ->checkOut(strtotime($m[2]));
            }

            $account = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Frequent Guest ID:')][1]/ancestor::tr[1]/descendant::td[2]", $root);

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Class/Type')][1]/following::text()[normalize-space()='{$h->getHotelName()}'][1]/ancestor::tr[1]/descendant::td[normalize-space()][last()]", $root);
            $rate = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Rate Per Night:')][1]/ancestor::tr[1]/descendant::td[2]", $root, true, "/([\d\,\.]+\s*[A-Z]{3})/");

            if (!empty($roomType) || !empty($rate)) {
                $room = $h->addRoom();

                if (!empty($roomType)) {
                    $room->setType($roomType);
                }

                if (!empty($rate)) {
                    $room->setRate($rate);
                }
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $paxText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveler')]/ancestor::table[1]/descendant::text()[normalize-space()]"));

        if (!empty($paxText)) {
            $f->general()
                ->travellers(array_filter(explode("\n", $this->re("/Traveler\n(.+)Date/s", $paxText))));
        } else {
            $traveller = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Dear')]/ancestor::tr[1]", null, true, "/Dear\s*(\w+\/?\D*)\,/u");
            $f->general()
                ->traveller(preg_replace("/\s(?:Mrs|Ms|Mr)$/iu", "", $traveller));
        }

        $conf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Record Locator:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Record Locator:'))}\s*([A-Z\d]{5,})/");

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf, 'Record Locator');
        } else {
            $f->general()
                ->noConfirmation();
        }

        $ticketNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Ticket Number')]", null, true, "/{$this->opt($this->t('Ticket Number'))}\s*(\d{6,})\//");

        if (!empty($ticketNumber)) {
            $f->issued()->ticket($ticketNumber, false);
        }

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'AIR -')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/Flight\s*(?<airlineName>[A-Z\d]{2})(?<flightNumber>\d{2,4})\s*(?<cabin>\D+)$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $depName = '';
            $depInfo = implode("\n", $this->http->FindNodes("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Depart:'][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

            if (stripos($depInfo, 'Terminal') !== false) {
                if (preg_match("/^(?<name1>.+)\, Terminal\s*(?<terminal>.+)\n(?<name2>.+)$/", $depInfo, $m)) {
                    $depName = $m['name1'] . ' ' . $m['name2'];
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            } else {
                $depName = str_replace("\n", " ", $depInfo);
            }

            $s->departure()
                ->noCode()
                ->name($depName)
                ->date($this->normalizeDate($this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Depart:'][1]/following::text()[normalize-space()='(Directions)'][1]/preceding::text()[normalize-space()][1]", $root)));

            $arrName = '';
            $arrInfo = implode("\n", $this->http->FindNodes("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Arrive:'][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));

            if (stripos($arrInfo, 'Terminal') !== false) {
                if (preg_match("/^(?<name1>.+)\, Terminal\s*(?<terminal>.+)\n(?<name2>.+)$/", $arrInfo, $m)) {
                    $arrName = $m['name1'] . ' ' . $m['name2'];
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            } else {
                $arrName = str_replace("\n", " ", $arrInfo);
            }

            $s->arrival()
                ->noCode()
                ->name($arrName)
                ->date($this->normalizeDate($this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Arrive:'][1]/following::text()[normalize-space()='(Directions)'][1]/preceding::text()[normalize-space()][1]", $root)));

            $durationInfo = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Duration:'][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(.+)\s+(?:Stop\s*\d+|Non\-stop)$/", $durationInfo, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            $programInfo = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='FF Number:'][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^([X\d]+)$/", $programInfo, $m)) {
                $accounts[] = $m[1];
            }

            $statusInfo = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Status:'][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<status>.+)\s+\-\s+.*\:\s+(?<segConfirmation>[A-Z\d]{6})$/", $statusInfo, $m)) {
                $s->setStatus($m['status']);
                $s->setConfirmation($m['segConfirmation']);
            }

            $aircraft = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()='Equipment:'][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Equipment:'))}\s*(.+)/");

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $seat = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Seat:'][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d{1,3}[A-Z])/");

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
        }
        $f->program()
            ->accounts(array_unique($accounts), true);
    }

    public function ParseCar(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'CAR')]");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $paxText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveler')]/ancestor::table[1]/descendant::text()[normalize-space()]"));

            if (!empty($paxText)) {
                $r->general()
                    ->travellers(array_filter(explode("\n", $this->re("/Traveler\n(.+)Date/s", $paxText))));
            }

            $r->general()
                ->confirmation($this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Confirmation:']/following::text()[normalize-space()][1]", $root));

            $r->setCompany($this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]", $root));

            $pickUpText = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Pick Up:']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<location>.+)\;\s*Tel\:(?<phone>[\s\d\=\(\)\-\+]+)\;\s*Fax\:\s*(?<fax>[\s\d\=\(\)\-\+]+)$/", $pickUpText, $m)) {
                $r->pickup()
                    ->location($m['location'])
                    ->fax($m['fax'])
                    ->phone($m['phone']);
            }

            $pickUpDate = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='(Directions)'][1]/preceding::text()[normalize-space()][1]", $root);
            $r->pickup()
                ->date($this->normalizeDate($pickUpDate));

            $dropOffDate = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='(Directions)'][2]/preceding::text()[normalize-space()][1]", $root);
            $r->dropoff()
                ->date($this->normalizeDate($dropOffDate));

            $dropOffText = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Drop Off:']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<location>.+)\;\s*Tel\:(?<phone>[\s\d\=\(\)\-\+]+)\;\s*Fax\:\s*(?<fax>[\s\d\=\(\)\-\+]+)$/", $dropOffText, $m)) {
                $r->dropoff()
                    ->location($m['location'])
                    ->fax($m['fax'])
                    ->phone($m['phone']);
            }

            $r->car()
                ->type($this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Type:'][1]/following::text()[normalize-space()][1]", $root));

            $account = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Frequent Renter ID:'][1]/following::text()[normalize-space()][1]", $root, true, "/[x](\d+)$/iu");

            if (!empty($account)) {
                $r->program()
                    ->account($account, true);
            }

            $priceText = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()='Total:'][1]/following::text()[normalize-space()][1]", $root, true, "/^([\d\.\,]+\s*[A-Z]{3})\s/");

            if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $priceText, $m)) {
                $r->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Travel Summary - Record ')]", null, true, "/{$this->opt($this->t('Travel Summary - Record '))}\s*([A-Z\d]{5,})/");

        if (!empty($otaConf)) {
            $email->ota()->confirmation($otaConf, 'Travel Summary - Record');
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'HOTEL')]")->length > 0) {
            $this->ParseHotel($email);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AIR')]")->length > 0) {
            $this->ParseFlight($email);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'CAR')]")->length > 0) {
            $this->ParseCar($email);
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

        if (preg_match("/^[A-Z\d]+\s+CANCEL\s*(\d+)\s*DAYS PRIOR TO ARRIVAL$/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }

        if (preg_match("/^[A-Z\d]+\s+CANCEL (\d+) HOURS PRIOR TO ARRIV$/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        }
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^([\d\:]+\s*A?P?M?)\s*\w+\,\s+(\w+)\s*(\d+)\s*(\d{4})$#u", //09:45 AM Thursday, January 5 2023
        ];
        $out = [
            "$3 $2 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
