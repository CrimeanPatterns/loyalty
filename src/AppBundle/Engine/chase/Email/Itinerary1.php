<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "chase/it-10449743.eml, chase/it-2.eml, chase/it-2969478.eml, chase/it-2969521.eml, chase/it-4.eml";

    public $reFrom = "donotreply@travelemail.res12.com";
    public $reSubject = "Travel Reservation Center Trip";
    public $reSubject2 = 'Travel Reservation Center Trip';
    public $reBody = "Chase Travel Center";
    public $reBody2 = "Room";
    public $reBody3 = "Vehicle";
    public $reBody4 = "Please review your updated flight itinerary below";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confNumber = $this->http->FindSingleNode("//*[contains(text(),'Booking Confirmation Number')]/b");

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//*[contains(text(),'Booking Confirmation Number')]", null, true, "/\:\s+([A-Z\d]{10})/");
        }

        $h->general()
            ->confirmation($confNumber, 'Booking Confirmation Number')
            ->traveller($this->http->FindSingleNode("//*[contains(text(),'Booking Confirmation Number')]/text()/ancestor::td[1]/following-sibling::td[1]"))
            ->cancellation(implode(' ', $this->http->FindNodes("//*[contains(text(),'Rules and Policies')]/following-sibling::ul[1]/*[contains(text(),'Cancellation')]")))
            ->status($this->http->FindSingleNode("//*[contains(text(),'Your Reservation Status')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("(//*[contains(text(),'Check-In')]/ancestor::table[2]//tr[1])[1]/td[1]"))
            ->address($this->http->FindSingleNode("(//*[contains(text(),'Check-In')]/ancestor::table[2]//tr[1])[1]/td[2]"));

        $checkIn = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-In')]/b[1]"));

        if (empty($checkIn)) {
            $checkIn = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-In')]/strong[1]"));
        }

        $checkOut = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-In')]/b[2]"));

        if (empty($checkOut)) {
            $checkOut = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-In')]/strong[2]"));
        }

        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut)
            ->guests((int) $this->http->FindSingleNode("//*[contains(text(),'Check-In')]/following-sibling::*[1]", null, true, "#(\d+)\s+Adult#"))
            ->kids((int) $this->http->FindSingleNode("//*[contains(text(),'Check-In')]/following-sibling::*[1]", null, true, "#(\d+)\s+Child#"));

        $this->detectDeadLine($h, $h->getCancellation());

        $roomType = trim($this->http->FindSingleNode("//*[contains(text(),'Room Type')]", null, true, "#Room Type:(.+)#"));

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $h->price()
            ->total(str_replace(",", "", $this->http->FindSingleNode("//*[contains(text(),'Total Charges')]/following-sibling::td[2 or 1][last()]", null, true, "#([0-9,\.]+)#")))
            ->currency(currency(trim($this->http->FindSingleNode("//*[contains(text(),'Total Charges')]/following-sibling::td[2 or 1][last()]", null, true, "#([^\s0-9,\.]+)#"))))
            ->spentAwards($this->http->FindSingleNode("//*[contains(text(),'Total Charges')]/following-sibling::td[2 or 1][last()-1]", null, true, "#([0-9,\.]+)#"));

        return true;
    }

    public function parseCar(Email $email)
    {
        $r = $email->add()->rental();

        $confNumber = $this->http->FindSingleNode("//*[contains(text(),'Booking Confirmation Number')]/b");

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//*[contains(text(),'Booking Confirmation Number')]", null, true, "/\:\s+([A-Z\d]{10})/");
        }
        $r->general()
            ->confirmation($confNumber, 'Booking Confirmation Number')
            ->traveller(trim($this->http->FindSingleNode("//text()[contains(.,'Booking Confirmation Number')]/ancestor::td[1]/following-sibling::*[1]")));

        $status = $this->http->FindSingleNode("//text()[contains(.,'Your Reservation Status')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        //it-4.eml
        $junkTest = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pick-Up Location')]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('Pick-Up Location:'))}\s+{$this->opt($this->t('Drop-Off Date\/Time:'))}.+{$this->opt($this->t('Drop-Off Location:'))}\s+{$this->opt($this->t('Same as pick-up'))}/", $junkTest)) {
            $email->setIsJunk(true);
        }

        $r->pickup()
            ->date(strtotime(str_replace('- ', '', trim($this->http->FindSingleNode("//*[contains(text(),'Pick-Up Date/Time')]", null, true, "#Pick-Up Date/Time:(.*?)Pick-Up Location#")))));

        $pickUpLocation = trim($this->http->FindSingleNode("//*[contains(text(),'Pick-Up Date/Time')]", null, true, "#Pick-Up Location:(.+)$#"));

        if (empty($pickUpLocation)) {
            $r->pickup()->noLocation();
        } else {
            $r->pickup()->location($pickUpLocation);
        }

        $r->dropoff()
            ->date(strtotime(str_replace('- ', '', trim($this->http->FindSingleNode("//*[contains(text(),'Drop-Off Date/Time')]", null, true, "#Drop-Off Date/Time:(.*?)Drop-Off Location#")))));

        $dropOffLocation = trim($this->http->FindSingleNode("//*[contains(text(),'Drop-Off Date/Time')]", null, true, "#Drop-Off Location:(.+)$#"));

        if (($dropOffLocation == 'Same As Pick Up' || $dropOffLocation == 'Same as pick-up') && !empty($pickUpLocation)) {
            $r->dropoff()
                ->same();
        } else {
            $r->dropoff()
                ->location($dropOffLocation);
        }

        $r->car()
            ->type(trim($this->http->FindSingleNode("//text()[contains(.,'Pick-Up Date/Time')]/ancestor::tr[2]/../tr[1]/td[1]", null, true, "#(\w+) Car#")));

        $r->car()
            ->model(trim($this->http->FindSingleNode("//text()[contains(.,'Pick-Up Date/Time')]/ancestor::tr[2]/../tr[1]/td[1]", null, true, "#Car (.+)#")));

        if (is_null($total = $this->amount($this->http->FindSingleNode("//text()[contains(.,'Total Charges')]/ancestor::td[1]/following-sibling::td[2]")))) {
            $total = $this->amount($this->http->FindSingleNode("//text()[contains(.,'Total')]/ancestor::td[1]/following-sibling::td[1]"));
        }

        $currency = $this->currency($this->http->FindSingleNode("//text()[contains(.,'Total Charges')]/ancestor::td[1]/following-sibling::td[2]", null, true, "#([^\s0-9,\.]+)#"));

        if (empty($currency)) {
            $currency = $this->currency($this->http->FindSingleNode("//text()[contains(.,'Total')]/ancestor::td[1]/following-sibling::td[1]"));
        }

        $r->price()
            ->total($total)
            ->currency($currency);

        $r->price()
            ->spentAwards($this->http->FindSingleNode("//text()[contains(.,'Total Charges')]/ancestor::td[1]/following-sibling::td[1]", null, true, "#([0-9,\.]+)#"));

        return true;
    }

    public function parseFlight(Email $email)
    {
        $agencyConfirmation = $this->http->FindSingleNode("//*[contains(text(), 'Agency Reference Number')]/b");

        if (!empty($agencyConfirmation)) {
            $email->ota()
                ->confirmation($agencyConfirmation);
        }

        $f = $email->add()->flight();

        $f->general()
            ->travellers($this->http->FindNodes("//*[contains(text(),'Passenger')]/following-sibling::*[1]"))
            ->noConfirmation();

        $airlineConfirmation = $this->http->FindSingleNode("//*[contains(text(), 'Airline Reference Number')]/b");

        $xpath = "//img[contains(@src,'https://images1.orxenterprise.com/Images/Airlines/30x30/DL.JPG')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if (!empty($airlineConfirmation)) {
                $s->airline()
                    ->confirmation($airlineConfirmation);
            }

            $s->airline()
                ->name($this->http->FindSingleNode(".//tr/td[2]", $root, true, "#^([A-Z]+)\s+\#\d+#"))
                ->number($this->http->FindSingleNode(".//tr/td[2]", $root, true, "#^[A-Z]+\s+\#(\d+)#"));

            $s->departure()
                ->code(trim($this->http->FindSingleNode(".//tr/td[3]", $root, true, "#^\S+,\s+\S+\s+\d+,\s+\d+\s+\d+:\d+\s+[AMP]{2}.*?\((.*?)\)#")))
                ->date(strtotime(trim($this->http->FindSingleNode(".//tr/td[3]", $root, true, "#^(\S+,\s+\S+\s+\d+,\s+\d+\s+\d+:\d+\s+[AMP]{2}).*?\(.*?\)#"))));

            $s->arrival()
                ->code(trim($this->http->FindSingleNode(".//tr/td[4]", $root, true, "#^\S+,\s+\S+\s+\d+,\s+\d+\s+\d+:\d+\s+[AMP]{2}.*?\((.*?)\)#")))
                ->date(strtotime(trim($this->http->FindSingleNode(".//tr/td[4]", $root, true, "#^(\S+,\s+\S+\s+\d+,\s+\d+\s+\d+:\d+\s+[AMP]{2}).*?\(.*?\)#"))));

            $iarcraft = $this->http->FindSingleNode(".//tr/td[2]", $root, true, "#^[A-Z]+\s+\#\d+\s+\S+\s+([A-Z\s[0-9]+)#");

            if (!empty($iarcraft)) {
                $s->extra()
                    ->aircraft($iarcraft);
            }

            $cabin = $this->http->FindSingleNode(".//tr/td[2]", $root, true, "#^[A-Z]+\s+\#\d+\s+(\S+)\s+[A-Z\s[0-9]+#");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $duration = $this->http->FindSingleNode(".//tr/td[6]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $stops = $this->http->FindSingleNode(".//tr/td[5]", $root);

            if (!empty($stops)) {
                $s->extra()
                    ->stops($stops);
            }
        }

        return true;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false
            && (
                strpos($body, $this->reBody2) !== false
                || strpos($body, $this->reBody3) !== false
                || strpos($body, $this->reBody4) !== false
            );
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject2);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $subject = $parser->getSubject();
        $tripID = $this->re("/\#\s+(\d{8})/", $subject);

        if (!empty($tripID)) {
            $email->ota()
                ->confirmation($tripID);
        }

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        if (stripos($body, $this->reBody2)) {
            $this->parseHotel($email);
        }

        if (stripos($body, $this->reBody3)) {
            $this->parseCar($email);
        }

        if (stripos($body, $this->reBody4)) {
            $this->parseFlight($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function amount($s)
    {
        $s = str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));

        if (is_numeric($s)) {
            return $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => '$',
            '£' => 'GBP',
            '₹' => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine

        if (preg_match("#PLEASE CANCEL AT LEAST (\d+) DAYS PRIOR TO ARRIVAL TO AVOID#i",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' days', null);
        }

        if (preg_match("#Cancellations made after (\w+\s+\d+\,\s+\d{4}\s+[\d\:]+\s+A?P?M) \(property local time\)#i",
            $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m[1]));
        }
    }

    private function normalizeDate($instr)
    {
        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})\s+([\d\:]+\s+A?P?M)$#", //Aug 29, 2020 11:59 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $instr);

        return strtotime($str);
    }
}
