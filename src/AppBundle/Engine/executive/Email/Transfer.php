<?php

namespace AwardWallet\Engine\executive\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: uniglobe/It2583289

class Transfer extends \TAccountChecker
{
    public $mailFiles = "executive/it-43298439.eml";

    private $lang = 'en';

    private $detects = [
        'Thank you for your business!',
    ];

    private $from = '/[@\.]executivetravel\.com/';

    private $prov = 'Executive Travel';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        if ($ota = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Agency Booking Confirmation Number')]/following-sibling::node()[normalize-space(.)][1]")) {
            $email->ota()
                ->confirmation($ota, 'Agency Booking Confirmation Number');
        }

        if (0 < $this->http->XPath->query("//tr[normalize-space(.)='Ground Transportation' and descendant::tr][1]/following-sibling::tr[1]")->length) {
            $this->transfer($email);
        }

        if (0 < $this->http->XPath->query("//tr[starts-with(normalize-space(.), 'Tour') and not(.//tr)]")->length) {
            $this->event($email);
        }

        if (0 < $this->http->XPath->query("//tr[contains(normalize-space(.), 'Flight Number') and descendant::tr][1]/following-sibling::tr[1]")->length) {
            $this->flight($email);
        }

        if (0 < $this->http->XPath->query("//tr[contains(normalize-space(.), 'Flight Number') and descendant::tr][1]/following-sibling::tr[1]")->length) {
            $this->hotel($email);
        }
    }

    private function transfer(Email $email): void
    {
        $this->logger->notice(__METHOD__);
        $t = $email->add()->transfer();

        if ($paxs = $this->http->FindNodes("//tr[normalize-space(.)='Passenger Names' and not(.//tr)]/following::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]")) {
            foreach ($paxs as $pax) {
                $t->addTraveller($pax);
            }
        }

        if (
            ($tot = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Total Fare')]/following::node()[normalize-space(.)][1]"))
            && preg_match('/([A-Z]{3})[ ]*\D([\d\.]+)/', $tot, $m)
        ) {
            $t->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $xpath = "//tr[normalize-space(.)='Ground Transportation' and descendant::tr][1]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $t->addSegment();

            $date = $this->http->FindSingleNode('descendant::tr[contains(., "Date") and not(.//tr)][1]', $root, true, '/(\w+, \d{1,2}\/\d{1,2})\/\d{2,4}/');

            $data = $this->http->FindSingleNode('descendant::tr[contains(., "PICKUP") and not(.//tr)][1]', $root);

            // RATE CONF-MG090211PICKUP-ST LOUIS AIRPORT JET LINX FLIGHT 530303 AT 742AMDROPOFF-1720 KENMONT ROAD 63124CF-MG090211
            $re = '/CONF\-([\dA-Z]+).*?PICKUP\-(.+?) AT (\d{1,2})\:?(\d{2})([AP][M]?)\s*DROPOFF\-(.+?)(?:\s*\d+\s*RESERVATION|MEET DRIVER|CF\-)/s';

            if (preg_match($re, $data, $m)) {
                $t->addConfirmationNumber($m[1]);
                $s->departure()
                    ->name($m[2])
                    ->date(strtotime($date . ' ' . $m[3] . ':' . $m[4] . ' ' . str_replace('MM', 'M', $m[5] . 'M')));
                $s->arrival()
                    ->name($m[6])
                    ->noDate();
            } else {
                $t->removeSegment($s);
            }
        }
    }

    private function hotel(Email $email): void
    {
        $this->logger->notice(__METHOD__);
        $xpath = "//tr[contains(normalize-space(.), 'Hotel') and descendant::tr][1]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $h = $email->add()->hotel();
            $hotelInfo = $this->http->FindNodes('descendant::tr[not(.//tr)][1]/descendant::text()[normalize-space(.)]', $root);

            if (5 === count($hotelInfo)) {
                $h->hotel()
                    ->name($hotelInfo[0])
                    ->address($hotelInfo[1] . ', ' . $hotelInfo[2])
                    ->phone($this->re('/Phone[ ]*\:[ ]*(.+)/i', $hotelInfo[3]))
                    ->fax($this->re('/Fax[ ]*\:[ ]*(.+)/i', $hotelInfo[4]))
                ;
            }

            if ($conf = $this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(.), 'Confirmation')][1]", $root, true, '/(\d+)/')) {
                $h->general()
                    ->confirmation($conf);
            }

            if ($checkIn = $this->hNode($root, 'Check-In Date')) {
                $h->booked()
                    ->checkIn(strtotime($checkIn));
            }

            if ($acc = $this->hNode($root, 'Membership Number')) {
                $h->addAccountNumber($acc, false);
            }

            if ($status = $this->hNode($root, 'Status')) {
                $h->setStatus($status);
            }

            if ($checkOut = $this->hNode($root, 'Check-Out Date')) {
                $h->booked()
                    ->checkOut(strtotime($checkOut));
            }

            if ($rooms = $this->hNode($root, 'Number of Rooms')) {
                $h->booked()
                    ->rooms($rooms);
            }

            if ($total = $this->hNode($root, 'Approximate Total')) {
                $h->price()
                    ->total($total);
            }
            $r = $h->addRoom();

            if ($rate = $this->hNode($root, 'Cost per night')) {
                $r->setRate($rate);
            }
        }
    }

    private function hNode(\DOMNode $node, string $s = '', ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("descendant::node()[starts-with(normalize-space(.), '{$s}')]/following-sibling::text()[normalize-space(.)][1]", $node, true, $re);
    }

    private function flight(Email $email): void
    {
        $this->logger->notice(__METHOD__);
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        if ($paxs = $this->http->FindNodes("//tr[starts-with(normalize-space(.), 'Executive Travel') and contains(normalize-space(.), 'Office hrs') and not(.//tr)]/ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)]")) {
            foreach ($paxs as $pax) {
                $f->addTraveller($pax);
            }
        }

        if (preg_match('/([A-Z]{3})[ ]+[^D]*?([\d\.]+)/', $this->getNode('Total Fare'), $m)) {
            $f->price()
                ->currency($m[1])
                ->total($m[2]);
        }

        if ($ffs = $this->http->FindNodes("//table[starts-with(normalize-space(.), 'Frequent Flyer Info') and not(.//table)]/following-sibling::table[1]/descendant::tr[not(.//tr)]", null, '/(\d+)/')) {
            foreach ($ffs as $ff) {
                $f->addAccountNumber($ff, false);
            }
        }

        $xpath = "//tr[contains(normalize-space(.), 'Flight Number') and descendant::tr][1]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if ($conf = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(.), 'CONFIRMATION NUMBER IS')][1]", $root, true, '/CONFIRMATION NUMBER IS[ ]+([A-Z\d]{5,9})/')) {
                $s->airline()
                    ->confirmation($conf);
            }

            $node = $this->http->FindSingleNode("preceding::text()[contains(normalize-space(.), 'Flight Number')][1]", $root);

            if (preg_match('/([A-Z\d]{2})[ ]*\-[ ]*Flight Number[ ]*(\d+)/', $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } elseif (
                preg_match('/\-[ ]*Flight Number[ ]*(\d+)/', $node, $m)
                && ($name = $this->http->FindSingleNode("preceding::text()[contains(normalize-space(.), 'Flight Number')][1]/preceding-sibling::a[1]/descendant::text()[normalize-space(.)][1]", $root))
            ) {
                $s->airline()
                    ->name($name)
                    ->number($m[1]);
            }

            if ($ddate = $this->getNode('Departure', $root)) {
                $s->departure()
                    ->date(strtotime($ddate));
            }

            if ($adate = $this->getNode('Arrival', $root)) {
                $s->arrival()
                    ->date(strtotime($adate));
            }

            if ($dname = $this->getNode('Departure City', $root)) {
                $s->departure()
                    ->name($dname)
                    ->code($this->getNode('Departure City', $root, '/\(([A-Z]{3})\)/', 2))
                ;
            }

            if ($aname = $this->getNode('Arrival City', $root)) {
                $s->arrival()
                    ->name($aname)
                    ->code($this->getNode('Arrival City', $root, '/\(([A-Z]{3})\)/', 2))
                ;
            }

            if ($dterm = $this->getNode('Departing Terminal', $root)) {
                $s->departure()
                    ->terminal($dterm);
            }

            if ($aterm = $this->getNode('Arrival Terminal', $root)) {
                $s->arrival()
                    ->terminal($aterm);
            }

            if ($status = $this->getNode('Status', $root)) {
                $s->setStatus($status);
            }

            if ($class = $this->getNode('Class of Service', $root, '/([A-Z])[ ]*\-/')) {
                $s->extra()
                    ->bookingCode($class);
            }

            if ($cabin = $this->getNode('Class of Service', $root, '/[A-Z][ ]*\-[ ]*(.+)/')) {
                $s->extra()
                    ->cabin($cabin);
            }

            if ($duration = $this->getNode('Travel Time', $root)) {
                $s->extra()
                    ->duration($duration);
            }

            if ($miles = $this->getNode('Miles', $root)) {
                $s->extra()
                    ->miles($miles);
            }

            if ($aircraft = $this->getNode('Equipment', $root)) {
                $s->extra()
                    ->aircraft($aircraft);
            }
        }
    }

    // no tour name
    private function event(Email $email)
    {
        $this->logger->notice(__METHOD__);

        return null;
        $e = $email->add()->event();

        if (
            ($tot = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Total Fare')]/following::node()[normalize-space(.)][1]"))
            && preg_match('/([A-Z]{3})[ ]*\D([\d\.]+)/', $tot, $m)
        ) {
            $e->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $xpath = "//tr[starts-with(normalize-space(.), 'Tour') and not(.//tr)]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            if (preg_match('/Confirmation\:[ ]*ENROUTE/', $root->nodeValue)) {
                $e->setNoConfirmationNumber(true);
            }

            if ($date = $this->http->FindSingleNode('following::tr[starts-with(normalize-space(.), "Departure") and not(.//tr)][1]', $root, true, '/(\w+, \d{1,2}\/\d{1,2}\/\d{2,4})/')) {
                $e->booked()->start(strtotime($date));
            }

            if ($addr = $this->http->FindSingleNode('following::tr[starts-with(normalize-space(.), "Departure") and not(.//tr)][1]', $root, true, '/Departure City\:[ ]+(.+)/')) {
                $e->place()->address($addr);
            }
        }
    }

    private function getNode(string $s = '', ?\DOMNode $node = null, ?string $re = null, int $text = 1): ?string
    {
        if (null !== $node) {
            return $this->http->FindSingleNode("descendant::*[count(tr)=5]/descendant::td[starts-with(normalize-space(.), '{$s}')][1]/descendant::*[name()='b'][normalize-space(.)][1]/following-sibling::node()[normalize-space(.)][{$text}]", $node, true, $re);
        } else {
            return $this->http->FindSingleNode("//node()[starts-with(normalize-space(.), '{$s}')][1]/following-sibling::text()[normalize-space(.)][{$text}]", $node, true, $re);
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+, (\d{1,2})\/(\d{1,2})\/(\d{2,4})$#", // Fri, 05/31/2019
        ];
        $out = [
            "$3-$2-$1",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
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
