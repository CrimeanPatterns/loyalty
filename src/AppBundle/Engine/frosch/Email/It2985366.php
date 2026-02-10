<?php

namespace AwardWallet\Engine\frosch\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class It2985366 extends \TAccountChecker
{
    public $mailFiles = "frosch/it-2985366.eml, frosch/it-2985376.eml, frosch/it-3548901.eml, frosch/it-3557203.eml, frosch/it-3558470.eml, frosch/it-3559962.eml, frosch/it-3559963.eml, frosch/it-3560061.eml, frosch/it-3930975.eml, frosch/it-6557413.eml, frosch/it-72548542.eml, frosch/it-81569809.eml, frosch/it-84766803.eml";

    private $subjects = [
        'en' => ['Itinerary for', 'Ticketed itinerary for', 'Ticketed Itinerary - Sales Invoice for'],
    ];

    private $emailSubject = '';
    private $lang = 'en';

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
        'phone'         => '[+(\d][-.\s\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        'travellerName' => '[A-Z]+(?: [A-Z]+)+', // MICHAEL SMITH
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->changeBody($parser);

        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".frosch.com/") or contains(@href,"www.frosch.com") or contains(@href,"forms.frosch.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"please notify FROSCH within") or contains(normalize-space(),"A FROSCH") or contains(.,"@frosch.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'Please review your itinerary and notify your travel consultant of any discrepancies immediately')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@frosch.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $this->emailSubject = $parser->getSubject();
        $this->changeBody($parser);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseHtml($email);

        return $email;
    }

    private function parseHtml(Email $email): void
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()->confirmation(
            $this->http->FindSingleNode('//text()[' . $this->starts('Agency Record Locator:') . ']/ancestor::tr[1]', null, true, '/Agency Record Locator:\s*(\w{5,})\b/'),
                'Agency Record Locator');

        $passenger = preg_match("/for (" . $this->patterns['travellerName'] . ") on \d+/", $this->emailSubject, $m) > 0 ? $m[1] : null;

        if (!$passenger) {
            $passenger = $this->http->FindSingleNode('//text()[' . $this->starts('Agency Record Locator:') . ']/ancestor::tr[1]/following::tr[not(.//tr) and normalize-space()][position()<4]//*[contains($style, "Franklin Gothic Medium") or contains(@face, "Franklin Gothic Medium")][1]', null, true, '/^(' . $this->patterns['travellerName'] . ')$/');
        }

        if (!$passenger) {
            $passenger = $this->http->FindSingleNode('//text()[' . $this->starts('Agency Record Locator:') . ']/ancestor::tr[1]/following::tr[not(.//tr) and normalize-space()][position()<4][contains(translate(., "0123456789", "%%%%%%%%%%"), "%%%%")][1]/preceding::tr[normalize-space()][1]', null, true, '/^(' . $this->patterns['travellerName'] . ')$/');
        }

        if (!$passenger) {
            $passenger = $this->http->FindSingleNode("(//*[contains(text(),'Passenger') and contains(text(),'Name:')]/ancestor::td[1]/following-sibling::td[2])[1]");
        }

        //#################
        //##   FLIGHT   ###
        //#################

        $xpath = "//img[contains(@src,'air.gif')]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2] | //td[normalize-space()='FLIGHT']/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]";
        $fls = $this->http->XPath->query($xpath);

        if ($fls->length == 0) {
            $xpath = "//text()[normalize-space()='FLIGHT#']/ancestor::tr[1]";
        }
        $fls = $this->http->XPath->query($xpath);

        if ($fls->length > 0) {
            $f = $email->add()->flight();

            // General
            $f->general()->noConfirmation();

            // Issued
            $tickets = $ticketNames = [];
            $ticketTexts = $this->http->FindNodes("//text()[normalize-space()='Ticket Number:']/following::text()[normalize-space()][1]");

            foreach ($ticketTexts as $tText) {
                if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])-(?<number>\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})\s*(?i)(?:[\/(]|Electronic Tkt|$)/", $tText, $m)) {
                    $ticketNames[] = $m['name'];
                    $tickets[] = $m['number'];
                }
            }

            if (count($tickets) > 0) {
                $f->issued()->tickets(array_unique($tickets), false);

                if (count(array_unique($ticketNames)) === 1) {
                    $f->issued()->name($ticketNames[0]);
                }
            }

            $travellers = $accountNumbers = [];

            // Segments
            foreach ($fls as $root) {
                $s = $f->addSegment();
                /*
                Segment type-1:
                    Air Canada
                    Status: Confirmed - Confirmation: D12345
                    ..
                    Departs: YOW - Ottawa Macdonald-Cartier Intl, ON CA    06:00 PM
                    Arrives: YYZ - Toronto Lester B. Pearson Intl, ON CA    07:05 PM    TERMINAL 1

                Segment type-2:
                    Air Canada        Status: Confirmed - Confirmation: D12345
                    ..
                    From: YOW - Ottawa Macdonald-Cartier Intl, ON CA    Depart: 06:00 PM
                    To: YYZ - Toronto Lester B. Pearson Intl, ON CA    Arrive: 07:05 PM    07 Dec    TERMINAL 1
                */

                if ($this->http->XPath->query("./following-sibling::tr[normalize-space()][1][{$this->starts('From:')}]/following-sibling::tr[normalize-space()][1][{$this->starts('To:')}]", $root)->length > 0) {
                    $this->parseFlightSegment2($s, $root, $travellers, $accountNumbers);
                /*
                } elseif ($this->http->XPath->query("./descendant::text()[contains(normalize-space(), 'From:')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'To:')]", $root)->length > 0) {
                    $this->parseFlightSegment3($s, $root, $travellers, $accountNumbers);
                */
                } else {
                    $this->parseFlightSegment1($s, $root, $travellers, $accountNumbers);
                }
            }

            if (count($travellers)) {
                $f->general()->travellers(array_unique($travellers));
            } elseif (!empty($passenger)) {
                $f->general()->traveller($passenger);
            }

            if (count($accountNumbers)) {
                $f->program()->accounts(array_unique($accountNumbers), false);
            }

            $xpathPrice = "//tr[{$this->eq('Ticket/Invoice Information')}]";
            $totalPrice = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq('Total Amount:')}] ]/*[normalize-space()][2]");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
                // 2,679.20
                $f->price()->total($this->normalizeAmount($m['amount']));

                $baseFare = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq('Total Tickets:')}] ]/*[normalize-space()][2]");

                if (preg_match('/^(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                    $f->price()->cost($this->normalizeAmount($matches['amount']));
                }

                $fees = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq(['Total Fees:', 'Total Service Fees:'])}] ]/*[normalize-space()][2]");

                if (preg_match('/^(?<amount>\d[,.\'\d ]*)$/', $fees, $matches)) {
                    $f->price()->fee('Total Service Fees', $this->normalizeAmount($matches['amount']));
                }
            }
        }

        //##############
        //##   CAR   ###
        //##############

        $xpath = "//img[contains(@src,'car.gif')]/ancestor::tr[1]/following-sibling::tr[1][not(descendant::td[normalize-space()='CAR' or normalize-space()='TRANSPORTATION'])] | //td[normalize-space()='CAR']/ancestor::tr[1]/following-sibling::tr[1] | //td[normalize-space()='Pick-up:']/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1]";
        $cars = $this->http->XPath->query($xpath);

        foreach ($cars as $root) {
            $r = $email->add()->rental();

            // General
            $confirmation = $this->http->FindSingleNode(".", $root, true, "/Confirmation\s*:\s*([-A-Z\d]{5,})$/");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("./following-sibling::tr[1]", $root, true, "/Confirmation\s*:\s*([-A-Z\d]{5,})$/");
            }
            $r->general()
                ->confirmation($confirmation)
                ->status($this->http->FindSingleNode('.', $root, true, "#Status\s*:\s*(\w+)#"));

            if (!empty($passenger)) {
                $r->general()->traveller($passenger);
            }

            /*
                Pick-up:
                Milan Linate IT
                Thu 27 Feb 2020 10:30
                Phone:
                002715123
            */
            $pattern = "/:\s*\n\s*(.+)\s*\n\s*(\w+ \d+.+)(?:\s*\n\s*Phone:\s*({$this->patterns['phone']}))?/";

            // Pick Up
            $str = implode("\n", $this->http->FindNodes("following-sibling::tr[normalize-space()][position()<3][contains(.,'Pick-up:')][1]/*", $root));

            if (preg_match($pattern, $str, $m)) {
                $r->pickup()
                    ->location($m[1])
                    ->date(strtotime(preg_replace("#(\s+\d+:\d+\s+[AP]M)#", ",$1", $m[2])));

                if (isset($m[3])) {
                    $r->pickup()->phone($m[3]);
                }
            }

            $xpathDropOff = "following-sibling::tr[normalize-space()][position()<6][{$this->contains('Drop-off:')}]";

            // DropoffLocation
            $str = implode("\n", $this->http->FindNodes($xpathDropOff . "[1]/*", $root));

            if (preg_match($pattern, $str, $m)) {
                $r->dropoff()
                    ->location($m[1])
                    ->date(strtotime(preg_replace("#(\s+\d+:\d+\s+[AP]M)#", ",$1", $m[2])));

                if (isset($m[3])) {
                    $r->dropoff()->phone($m[3]);
                }
            }
            // Drop-off: August 13, 2014 06:00 PM
            elseif (preg_match("/:\s*\n\s*(\w+ \d+.+)/", $str, $m)) {
                $r->dropoff()->same();
                $r->dropoff()->date(strtotime(preg_replace("#(\s+\d+:\d+\s+[AP]M)#", ",$1", $m[1])));
            }

            // Car
            $carType = $this->http->FindSingleNode($xpathDropOff . "/following-sibling::tr[normalize-space()][position()<10]/descendant::text()[{$this->starts('TYPE:')}]", $root, true, "/{$this->opt('TYPE:')}[ ]*(.+)$/i");

            if ($carType) {
                $r->car()->type($carType);
            }

            $membershipNumber = $this->http->FindSingleNode($xpathDropOff . "/following-sibling::tr[normalize-space()][position()<10]/descendant::text()[{$this->starts('CAR MEMBERSHIP NBR:')}]", $root, true, "/{$this->opt('CAR MEMBERSHIP NBR:')}[ ]*([-A-Z\d]{5,})$/i");

            if ($membershipNumber) {
                $r->program()->account($membershipNumber, false);
            }
        }

        //################
        //##   HOTEL   ###
        //################

        $xpath = "//img[contains(@src,'hhl.gif')]/ancestor::tr[1]/following-sibling::tr[1][not(descendant::td[normalize-space()='HOTEL'])]";
        $hotels = $this->http->XPath->query($xpath);

        if ($hotels->length === 0) {
            $hotels = $this->http->XPath->query("//td[normalize-space()='HOTEL']/ancestor::tr[1]/following-sibling::tr[1]");
        }

        if ($hotels->length === 0) {
            $hotels = $this->http->XPath->query("//td[normalize-space()='Check-In:']/ancestor::tr[1]/preceding-sibling::tr[2]");
        }

        foreach ($hotels as $root) {
            $h = $email->add()->hotel();

            $xpathCheckOut = "following-sibling::tr[position()<8][contains(.,'Check') and (contains(.,'out:') or contains(.,'Out:'))]";

            // General
            $confirmation = $this->http->FindSingleNode(".", $root, true, "/Confirmation\s*:\s*([-A-Z\d]{8,})$/");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root, true, "/Confirmation\s*:\s*([-A-Z\d]{8,})$/");
            }

            $status = $this->http->FindSingleNode('.', $root, true, "#Status\s*:\s*(\w+)#");

            if (empty($status)) {
                $status = $this->http->FindSingleNode('./preceding-sibling::tr[1]', $root, true, "#Status\s*:\s*(\w+)#");
            }
            $h->general()
                ->confirmation($confirmation)
                ->status($status)
                ->cancellation($this->http->FindSingleNode($xpathCheckOut . "/following-sibling::tr[normalize-space()][1]//p[contains(.,'cancel') or contains(.,'CANCEL')]", $root), true, true)
            ;

            if (!empty($passenger)) {
                $h->general()->traveller($passenger);
            }

            // Hotel
            $hotelName = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]/descendant::*[normalize-space()='HOTEL'][1]/preceding-sibling::*[normalize-space()]", $root);

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("descendant::td[contains(.,'Confirmation')]/preceding-sibling::*[normalize-space()]", $root);
            }

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::td[contains(.,'Confirmation')]/preceding-sibling::*[normalize-space()]", $root);
            }
            $h->hotel()->name($hotelName);

            $address = $this->http->FindSingleNode("(./following-sibling::tr/td[contains(.,'Address:')]/following-sibling::td[normalize-space(.)!=''])[1]", $root);

            if (!$address) {
                $address = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root) . " " . $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root);
            }
            $h->hotel()->address($address);

            $phoneFaxText = implode(' ', $this->http->FindNodes('following-sibling::tr[position()>1 and position()<4]/td[position()>3]', $root));

            if (preg_match('/Phone\s*:?\s*(' . $this->patterns['phone'] . ')/i', $phoneFaxText, $matches)) {
                $h->hotel()->phone($matches[1]);
            }

            if (preg_match('/Fax\s*:?\s*(' . $this->patterns['phone'] . ')/i', $phoneFaxText, $matches)) {
                $h->hotel()->fax($matches[1]);
            }

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode("(./following-sibling::tr/td[contains(.,'Check') and (contains(.,'in:') or contains(.,'In:'))]/following-sibling::td[normalize-space(.)!=''])[1]", $root)))
                ->checkOut(strtotime($this->http->FindSingleNode("(./following-sibling::tr/td[contains(.,'Check') and (contains(.,'out:') or contains(.,'Out:'))]/following-sibling::td[normalize-space(.)!=''])[1]", $root)))
                ->rooms($this->http->FindSingleNode($xpathCheckOut . "/following-sibling::tr[normalize-space()][1]", $root, true, "/rooms[ ]*:[ ]*(\d{1,3})(?:\D|$)/i"), true, true)
            ;

            // Rooms
            $rate = $this->http->FindSingleNode("following-sibling::tr[position()<7]/*[normalize-space()='Rate:']/following-sibling::*[normalize-space()][1]", $root, true, '/.*\d.*/');

            if ($rate === null) {
                $rate = $this->http->FindSingleNode("following-sibling::tr[position()<7]/*[contains(.,'Rate:')]", $root, true, '/Rate:[ ]*(.*\d.*)$/i');
            }

            if ($rate !== null) {
                $h->addRoom()->setRate($rate);
            }

            $membershipNumber = $this->http->FindSingleNode($xpathCheckOut . "/following-sibling::tr[normalize-space()][1]/descendant::text()[{$this->starts('HOTEL MEMBERSHIP:')}]", $root, true, "/{$this->opt('HOTEL MEMBERSHIP:')}[ ]*([-A-Z\d]{5,})$/i");

            if ($membershipNumber) {
                $h->program()->account($membershipNumber, false);
            }

            $this->detectDeadLine($h);
        }

        //###################
        //##   TRANSFER   ###
        //###################

        $xpath = "//img[contains(@src,'car.gif')]/ancestor::tr[1]/following-sibling::tr[1][descendant::td[normalize-space()='TRANSPORTATION']]/following-sibling::tr[1]";
        $transfers = $this->http->XPath->query($xpath);

        foreach ($transfers as $root) {
            $t = $email->add()->transfer();

            // General
            $t->general()
                ->status($this->http->FindSingleNode('.', $root, true, "#Status\s*:\s*(\w+)#"))
            ;

            if (!empty($passenger)) {
                $t->general()->traveller($passenger);
            }
            $conf = $this->http->FindSingleNode('.', $root, true, "#Confirmation\s*:\s*(\w{5,})\b#");

            if (empty($conf) && empty($this->http->FindSingleNode("(.//*[contains(., 'Confirmation')])[1]", $root))) {
                $t->general()->noConfirmation();
            } else {
                $t->general()->confirmation($conf);
            }

            $date = $this->http->FindSingleNode('./preceding-sibling::tr[3]', $root);

            $s = $t->addSegment();

            // Departure
            $dName = implode("\n", $this->http->FindNodes("./following-sibling::tr[contains(.,'Pick') and contains(.,'Up-')][1]//text()[normalize-space()]", $root));

            if (preg_match("#Pick Up-(.*?)\s*Time-#s", $dName, $m)) {
                $DepName = $m[1];
            }

            if (strtolower($DepName) === 'home') {
                $DepName = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'Location:')][1]", $root, true, "#Location\s*:\s*(.+)#");
            }

            $s->departure()
                ->name($DepName);

            $time = $this->normalizeTime($this->http->FindSingleNode("./following-sibling::tr[contains(.,'Time-')][1]//text()[contains(.,'Time-')][1]", $root, true, "#Time-(.+)#"));

            if (!empty($time)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $time));
            }

            // Arrival
            $aName = implode("\n", $this->http->FindNodes("./following-sibling::tr[contains(.,'Drop') and contains(.,'Off-')][1]//text()[normalize-space()]", $root));

            if (preg_match("#Drop Off-(.+)#", $aName, $m)) {
                $ArrName = $m[1];
            }

            if (strtolower($ArrName) === 'home') {
                $email->removeItinerary($t);
            // 'Location:' is location from
//                $ArrName = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'Location:')][1]", $root, true, "#Location\s*:\s*(.+)#");
            } else {
                $s->arrival()
                    ->name($ArrName);
            }

            $s->arrival()
                ->noDate();
        }

        //################
        //##   TRAIN   ###
        //################

        $xpath = "//img[contains(@src,'rail.gif')]/ancestor::tr[1]/following-sibling::tr[2]";
        $trains = $this->http->XPath->query($xpath);

        if ($trains->length > 0) {
            $t = $email->add()->train();

            // General
            $t->general()->noConfirmation();

            if (!empty($passenger)) {
                $t->general()->traveller($passenger);
            }

            // Segments
            foreach ($trains as $root) {
                $date = $this->http->FindSingleNode('./preceding-sibling::tr[3]', $root);

                $s = $t->addSegment();

                // Departure
                $s->departure()
                    ->name($this->http->FindSingleNode("./following-sibling::tr[contains(.,'Departs:') or contains(.,'From:')][1]/td[4]", $root))
                    ->date(strtotime($date . ', ' . $this->http->FindSingleNode("./following-sibling::tr[contains(.,'Departs:') or contains(.,'From:')][1]", $root, true, "#\d+:\d+\s*[AP]M#")))
                ;

                // Arrival
                $s->arrival()
                    ->name($this->http->FindSingleNode("./following-sibling::tr[contains(.,'Arrives:') or contains(.,'To:')][1]/td[4]", $root))
                    ->date(strtotime($date . ', ' . $this->http->FindSingleNode("./following-sibling::tr[contains(.,'Arrives:') or starts-with(normalize-space(),'To:')][1]", $root, true, "#\d+:\d+\s*[AP]M#")))
                ;

                // Extra
                $s->extra()
                    ->service($this->http->FindSingleNode('./following-sibling::tr[position() = 1 or position() = 2][contains(., "#")][1]', $root, true, "#Train\s*\#\s+(.+?)[\s\-]+\d+$#i"))
                    ->number($this->http->FindSingleNode('./following-sibling::tr[position() = 1 or position() = 2][contains(., "#")][1]', $root, true, "#Train\s*\#\s+.+?[\s\-]+(\d+)$#i"))
                ;
            }
        }
    }

    private function parseFlightSegment1(FlightSegment $s, \DOMNode $root, array &$travellers, array &$accountNumbers): void
    {
        // it-2985366.eml, it-2985376.eml, it-3557203.eml, it-3559962.eml, it-3559963.eml, it-3560061.eml, it-6557413.eml
        $this->logger->debug('Found flight segment type-1');

        $date = $this->http->FindSingleNode("preceding-sibling::tr[3]", $root, true, '/.{6,}/');

        // Airline
        $al = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'Flight')][1]", $root, true, "#\\#\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\d+#");

        if (empty($al)) {
            $al = trim(str_replace(" FLIGHT", "", $this->http->FindSingleNode('./preceding-sibling::*[1]', $root)));
        }
        $s->airline()
            ->name($al)
            ->number($this->http->FindSingleNode("./following-sibling::tr[contains(.,'Flight')][1]", $root, true, "#\\#\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])?(\d+)#"))
            ->operator($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][position()<15]//text()[{$this->contains(['OPERATED BY:'])}][1])[1]", $root, true, "/{$this->opt(['OPERATED BY:'])}\s*[\\/]?(.+)/"), true, true)
        ;
        $rl = $this->http->FindSingleNode('.', $root, true, '/Confirmation\s*:\s*([A-Z\da-z]{6})(?:Add)?/');

        if (empty($rl)) {
            $rl = strtoupper($this->http->FindSingleNode("./following-sibling::tr[contains(.,'Arrives:')][1]/following-sibling::tr[1]", $root, true, "#confirmation\s+number\s+is\s+([A-Z\da-z]{6})(?:Add)?#"));
        }

        if (!empty($rl)) {
            $s->airline()->confirmation($rl);
        }

        // Departure
        if ($date
            && ($time = $this->http->FindSingleNode("./following-sibling::tr[{$this->contains(['Departs:', 'Depart:'])}][1]", $root, true, "#\d+:\d+(?:\s*[AP]M)?#"))
        ) {
            $s->departure()
                ->code($this->http->FindSingleNode("./following-sibling::tr[{$this->starts(['Departs:', 'From:'])}][1]", $root, true, "#\s*:\s*([A-Z]{3})#"))
                ->name($this->http->FindSingleNode("./following-sibling::tr[{$this->starts(['Departs:', 'From:'])}][1]/td[normalize-space()][2]", $root, true, "#[A-Z]{3} - (.+)#"))
                ->terminal(($terminalDep = $this->http->FindSingleNode("following-sibling::tr[{$this->starts(['Departs:', 'From:'])}][1]/td[contains(.,'TERMINAL') or contains(.,'Terminal')]", $root)) !== null ? trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $terminalDep)) : null, false, true)
                ->date(strtotime($date . ', ' . $time))
            ;
        }

        // Arrival
        if ($date
            && ($time = $this->http->FindSingleNode("./following-sibling::tr[{$this->contains(['Arrives:', 'Arrive:'])}][1]", $root, true, "#\d+:\d+(?:\s*[AP]M)?#"))
        ) {
            $s->arrival()
                ->code($this->http->FindSingleNode("./following-sibling::tr[{$this->starts(['Arrives:', 'To:'])}][1]", $root, true, "#\s*:\s*([A-Z]{3})#"))
                ->name($this->http->FindSingleNode("./following-sibling::tr[{$this->starts(['Arrives:', 'To:'])}][1]/td[normalize-space()][2]", $root, true, "#[A-Z]{3} - (.+)#"))
                ->terminal(($terminalArr = $this->http->FindSingleNode("following-sibling::tr[{$this->starts(['Arrives:', 'To:'])}][1]/td[contains(.,'TERMINAL') or contains(.,'Terminal')]", $root)) !== null ? trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $terminalArr)) : null, false, true)
                ->date(strtotime($date . ', ' . $time))
            ;
        }

        // Extra
        $class = $this->http->FindSingleNode('./following-sibling::tr[contains(.,"Class:")][1]/td[starts-with(normalize-space(.),"Class:")]', $root);

        if (empty($class) || $class == 'Class:') {
            $class = $this->http->FindSingleNode('./following-sibling::tr[contains(.,"Class:")][1]/td[starts-with(normalize-space(.),"Class:")]/following-sibling::td[normalize-space()][1]', $root);
        }

        if (preg_match('/^(?:[^:]+:\s*)?([A-Z]{1,2}) - (\S+)$/', $class, $matches)) {
            $s->extra()
                ->bookingCode($matches[1])
                ->cabin($matches[2]);
        } elseif (preg_match('/^[^:]+:\s*(\S+)$/', $class, $matches)) {
            $s->extra()
                ->cabin($matches[1]);
        }

        $s->extra()
            ->miles($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][position()<15]//text()[{$this->contains(['MILES:', 'miles:'])}][1])[1]", $root, true, "/{$this->opt(['MILES:', 'miles:'])}\s*(.+)/"), true, true)
            ->meal($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][position()<15]//text()[{$this->contains(['MEAL:', 'meal:'])}][1])[1]", $root, true, "/{$this->opt(['MEAL:', 'meal:'])}\s*(.+)/"), true, true)
            ->aircraft($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][position()<15]//text()[{$this->contains(['EQUIPMENT:', 'equipment:'])}][1])[1]", $root, true, "/{$this->opt(['EQUIPMENT:', 'equipment:'])}\s*(.+)/"), true, true)
            ->duration($this->http->FindSingleNode("./following-sibling::tr[(contains(.,'hr,') or contains(.,'hrs,')) and contains(.,'min')][1]", $root, true, "#\d+\s+hrs?,\s+\d+\s+min#"), true, true);

        // Seats
        $seat = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][position()<15][{$this->contains(['Seat:', 'SEAT', 'Seat'])}][1]", $root, true, "/{$this->opt(['Seat:', 'SEAT', 'Seat'])}(?:.+)?\s+(\d{1,4}[A-Z])/");

        if ($seat) {
            $s->extra()->seat($seat);
        }

        $stops = $this->http->FindSingleNode("./following-sibling::tr[contains(.,'Flight')][1]/td[4]", $root);

        if (preg_match('/^Non[-\s]*stop$/i', $stops)) {
            $s->extra()->stops(0);
        }

        // Account
        $account = $this->http->FindSingleNode("following-sibling::tr[string-length(normalize-space())>2][5]/descendant::text()[{$this->starts('Frequent flyer #')}]/ancestor::*[1]", $root, true, "/^{$this->opt('Frequent flyer #')}\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-\s*[A-Z\d]{5,})$/");

        if (!empty($account)) {
            $accountNumbers[] = preg_replace('/\s*-\s*/', ' ', $account);
        }
    }

    private function parseFlightSegment2(FlightSegment $s, \DOMNode $root, array &$travellers, array &$accountNumbers): void
    {
        // it-72548542.eml, it-84766803.eml
        $this->logger->debug('Found flight segment type-2');

        $date = strtotime($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]", $root, true, '/.{6,}/'));

        // Status: Confirmed - Airline Confirmation: HS4QHJ
        $headerRight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[normalize-space()][last()]", $root);

        if (preg_match("/Status:\s*(Confirmed)(?:\s*-|$)/i", $headerRight, $m)) {
            $s->extra()->status($m[1]);
        }

        if (preg_match("/Airline Confirmation:\s*([-A-Z\d]{5,})(?:\s|$)/i", $headerRight, $m)) {
            $s->airline()->confirmation($m[1]);
        }

        $flight = $this->http->FindSingleNode("*[{$this->eq('FLIGHT#')}]/following-sibling::*[normalize-space()][1]", $root);

        if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);
        }

        $duration = $this->http->FindSingleNode("*[{$this->eq('Duration:')}]/following-sibling::*[normalize-space()][1]", $root, true, '/^\d[\d hrsmin]+$/i');
        $s->extra()->duration($duration);

        $stops = $this->http->FindSingleNode("*[{$this->eq('Stops:')}]/following-sibling::*[normalize-space()][1]", $root);

        if (preg_match('/^\d+$/', $stops)) {
            $s->extra()->stops($stops);
        } elseif (preg_match("/Non[-\s]*stop/i", $stops)) {
            $s->extra()->stops(0);
        }

        $class = $this->http->FindSingleNode("*[{$this->eq('Class:')}]/following-sibling::*[normalize-space()][1]", $root);

        if (preg_match("/^(?<bookingCode>[A-Z]{1,2})\s*-\s*(?<cabin>\w[\w\s]{2,})$/u", $class, $m)) {
            // K - Economy
            $s->extra()
                ->bookingCode($m['bookingCode'])
                ->cabin($m['cabin']);
        } elseif (preg_match("/^[A-Z]{1,2}$/", $class)) {
            // K
            $s->extra()->bookingCode($class);
        } elseif (preg_match("/^\w[\w\s]{2,}$/u", $class)) {
            // Economy
            $s->extra()->cabin($class);
        }

        // MSO - Missoula Johnson-Bell Field, MT US
        $patterns['codeName'] = '/^(?<code>[A-Z]{3})\s*-\s*(?<name>.{3,})$/';

        $xpathDep = "following-sibling::tr[normalize-space()][1]";

        $from = $this->http->FindSingleNode($xpathDep . "/*[{$this->eq('From:')}]/following-sibling::*[normalize-space()][1]", $root);

        if (preg_match($patterns['codeName'], $from, $m)) {
            $s->departure()
                ->code($m['code'])
                ->name($m['name']);
        }

        $timeDep = $this->http->FindSingleNode($xpathDep . "/*[{$this->eq('Depart:')}]/following-sibling::*[normalize-space()][1]", $root, true, "/^{$this->patterns['time']}$/");

        if ($date && $timeDep) {
            $s->departure()->date(strtotime($timeDep, $date));
        }

        $terminalDep = $this->http->FindSingleNode($xpathDep . "/*[{$this->eq('Depart:')}]/following-sibling::*[{$this->contains(['TERMINAL', 'Terminal'])}]", $root, true, '/^.*terminal.*$/i');

        if ($terminalDep !== null) {
            $s->departure()->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $terminalDep)));
        }

        $xpathArr = "following-sibling::tr[normalize-space()][2]";

        $to = $this->http->FindSingleNode($xpathArr . "/*[{$this->eq('To:')}]/following-sibling::*[normalize-space()][1]", $root);

        if (preg_match($patterns['codeName'], $to, $m)) {
            $s->arrival()
                ->code($m['code'])
                ->name($m['name']);
        }

        $timeArr = $this->http->FindSingleNode($xpathArr . "/*[{$this->eq('Arrive:')}]/following-sibling::*[normalize-space()][1]", $root, true, "/^{$this->patterns['time']}$/");
        $dateArr = $this->http->FindSingleNode($xpathArr . "/*[{$this->eq('Arrive:')}]/following-sibling::*[normalize-space()][2][not({$this->contains(['TERMINAL', 'Terminal'])})]", $root, true, '/.{6,}/');

        if ($dateArr && $timeArr) {
            if (preg_match("/\d{4}$/", $dateArr)) {
                $dateArrFull = strtotime($dateArr);
            } elseif ($date) {
                $dateArrFull = EmailDateHelper::parseDateRelative($dateArr, $date, true, '%D% %Y%');
            } else {
                $dateArrFull = null;
            }
            $s->arrival()->date(strtotime($timeArr, $dateArrFull));
        } elseif ($date && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $date));
        }

        $terminalArr = $this->http->FindSingleNode($xpathArr . "/*[{$this->eq('Arrive:')}]/following-sibling::*[{$this->contains(['TERMINAL', 'Terminal'])}]", $root, true, '/^.*terminal.*$/i');

        if ($terminalArr !== null) {
            $s->arrival()->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $terminalArr)));
        }

        $seats = [];
        $travellerRows = $this->http->XPath->query("following-sibling::tr[normalize-space()][3]/descendant::tr[ *[1][{$this->starts('Traveler')}] and *[3][{$this->starts('Seat')}] and *[5][{$this->starts('Frequent Flyer #')}] ]/following-sibling::tr[ *[1][normalize-space()] ]", $root);

        foreach ($travellerRows as $tRow) {
            if (($travellerName = $this->http->FindSingleNode('*[1]', $tRow, true, "/^{$this->patterns['travellerName']}$/"))) {
                $travellers[] = $travellerName;
            }

            if (($seat = $this->http->FindSingleNode('*[3]', $tRow, true, "/^\d+[A-Z]$/"))) {
                // 15A
                $seats[] = $seat;
            }

            if (($ffNumber = $this->http->FindSingleNode('*[5]', $tRow, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-\s*[A-Z\d]{5,}$/"))) {
                // DL-9310283477
                $accountNumbers[] = preg_replace('/\s*-\s*/', ' ', $ffNumber);
            }
        }

        $isSplitedSegment = $s->getStops() !== null && $s->getStops() > 0;

        $xpathExtra = "following-sibling::tr[normalize-space()][4]";

        // 1235 / 1976 KM
        $milesValues = array_unique(array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts('MILES:')}]/ancestor::*[1]", $root, "/^{$this->opt('MILES:')}\s*(\d[,.\'\d ]*?)(?:\s*\/|$)/i")));
        $miles = count($milesValues) === 1 ? array_shift($milesValues) : null;
        $s->extra()->miles($miles, false, $isSplitedSegment);

        $operatorValues = array_unique(array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts('OPERATED BY:')}]/ancestor::*[1]", $root, "/^{$this->opt('OPERATED BY:')}[\s\/]*(.{2,}?)(?:\s+DBA\s|$)/i")));
        $operator = count($operatorValues) === 1 ? array_shift($operatorValues) : null;
        $s->airline()->operator($operator, false, $isSplitedSegment);

        $equipmentValues = array_unique(array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts('EQUIPMENT:')}]/ancestor::*[1]", $root, "/^{$this->opt('EQUIPMENT:')}\s*(.{2,})$/i")));
        $equipment = count($equipmentValues) === 1 ? array_shift($equipmentValues) : null;
        $s->extra()->aircraft($equipment, false, true);

        $mealValues = array_unique(array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts('MEAL:')}]/ancestor::*[1]", $root, "/^{$this->opt('MEAL:')}\s*(.{2,})$/i")));
        $s->extra()->meals($mealValues);

        if (count($seats) === 0) {
            $seats = array_unique(array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts('SEAT')}]/ancestor::*[1]", $root, "/^{$this->opt('SEAT')}[:\s]*(\d+[A-Z])$/i")));
        }

        if (count($seats)) {
            $s->extra()->seats($seats);
        }
    }

    /*
    private function parseFlightSegment3(FlightSegment $s, \DOMNode $root, array &$travellers, array &$accountNumbers): void
    {
        // it-84766803.eml
        $this->logger->debug('Found flight segment type-3');

        $date = strtotime($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][3]", $root, true, '/.{6,}/'));

        // Status: Confirmed - Airline Confirmation: HS4QHJ
        $headerRight = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][2]", $root);

        if (preg_match("/Status:\s*(Confirmed)(?:\s*-|$)/i", $headerRight, $m)) {
            $s->extra()->status($m[1]);
        }

        if (preg_match("/Airline Confirmation:\s*([-A-Z\d]{5,})(?:\s|$)/i", $headerRight, $m)) {
            $s->airline()->confirmation($m[1]);
        }

        $flight = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains('FLIGHT#')}][1]/descendant::text()[contains(normalize-space(), 'FLIGHT#')]/following::text()[string-length()>1][1]", $root);

        if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{2,4})$/", $flight, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);
        }

        $duration = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains('FLIGHT#')}][1]/descendant::text()[contains(normalize-space(), 'Duration:')]/following::text()[string-length()>1][1]", $root, true, '/^\d[\d hrsmin]+$/i');
        $s->extra()->duration($duration);

        $stops = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains('FLIGHT#')}][1]/descendant::text()[contains(normalize-space(), 'Stops:')]/following::text()[string-length()>1][1]", $root);

        if (preg_match('/^\d+$/', $stops)) {
            $s->extra()->stops($stops);
        } elseif (preg_match("/Non[-\s]*stop/i", $stops)) {
            $s->extra()->stops(0);
        }

        $class = $this->http->FindSingleNode("./preceding-sibling::tr[{$this->contains('FLIGHT#')}][1]/descendant::text()[contains(normalize-space(), 'Class:')]/following::text()[string-length()>1][1]", $root);

        if (preg_match("/^(?<bookingCode>[A-Z]{1,2})\s*-\s*(?<cabin>\w[\w\s]{2,})$/u", $class, $m)) {
            // K - Economy
            $s->extra()
                ->bookingCode($m['bookingCode'])
                ->cabin($m['cabin']);
        } elseif (preg_match("/^[A-Z]{1,2}$/", $class)) {
            // K
            $s->extra()->bookingCode($class);
        } elseif (preg_match("/^\w[\w\s]{2,}$/u", $class)) {
            // Economy
            $s->extra()->cabin($class);
        }

        // MSO - Missoula Johnson-Bell Field, MT US
        $patterns['codeName'] = '/^(?<code>[A-Z]{3})\s*-\s*(?<name>.{3,})$/';

        $from = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'From')]/following::text()[string-length()>1][1]", $root);

        if (preg_match($patterns['codeName'], $from, $m)) {
            $s->departure()
                ->code($m['code'])
                ->name($m['name']);
        }

        $timeDep = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Depart:')]/following::text()[string-length()>1][1]", $root, true, "/^{$this->patterns['time']}$/");

        if ($date && $timeDep) {
            $s->departure()->date(strtotime($timeDep, $date));
        }

        $to = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(normalize-space(), 'To:')]/following::text()[string-length()>1][1]", $root);

        if (preg_match($patterns['codeName'], $to, $m)) {
            $s->arrival()
                ->code($m['code'])
                ->name($m['name']);
        }

        $timeArr = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(normalize-space(), 'Arrive:')]/following::text()[string-length()>1][1]", $root, true, "/^{$this->patterns['time']}$/");
        $dateArr = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[contains(normalize-space(), 'Arrive:')]/following::text()[string-length()>1][2][not({$this->contains(['TERMINAL', 'Terminal'])})]", $root, true, '/.{6,}/');

        if ($dateArr && $timeArr) {
            if (preg_match("/\d{4}$/", $dateArr)) {
                $dateArrFull = strtotime($dateArr);
            } elseif ($date) {
                $dateArrFull = EmailDateHelper::parseDateRelative($dateArr, $date, true, '%D% %Y%');
            } else {
                $dateArrFull = null;
            }
            $s->arrival()->date(strtotime($timeArr, $dateArrFull));
        } elseif ($date && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $date));
        }

        $seats = [];
        $travellerRows = $this->http->XPath->query("./following-sibling::tr[normalize-space()][2]/descendant::tr[ *[1][{$this->starts('Traveler')}] and *[3][{$this->starts('Seat')}] and *[5][{$this->starts('Frequent')}] ]/following-sibling::tr[ *[1][normalize-space()] ]", $root);

        foreach ($travellerRows as $tRow) {
            if (($travellerName = $this->http->FindSingleNode('*[1]', $tRow, true, "/^{$this->patterns['travellerName']}$/"))) {
                $travellers[] = $travellerName;
            }

            if (($seat = $this->http->FindSingleNode('*[normalize-space()][2]', $tRow, true, "/^\d+[A-Z]$/"))) {
                // 15A
                $seats[] = $seat;
            }

            if (($ffNumber = $this->http->FindSingleNode('*[5]', $tRow, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-\s*[A-Z\d]{5,}$/"))) {
                // DL-9310283477
                $accountNumbers[] = preg_replace('#\s*-\s*#', ' ', $ffNumber);
            }
        }

        if (count($seats)) {
            $s->extra()->seats($seats);
        }

        $xpathExtra = "following-sibling::tr[normalize-space()][4]";

        $miles = $this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(), 'MILES:')][1]/descendant::text()[{$this->starts('MILES:')}]/ancestor::*[normalize-space()][1]", $root, true, "/^{$this->opt('MILES:')}\s*(\d+)\s*\//i");
        $s->extra()->miles($miles);

        $operator = $this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(), 'OPERATED BY:')][1]/descendant::text()[{$this->starts('OPERATED BY:')}]/ancestor::*[normalize-space()][1]", $root, true, "/^{$this->opt('OPERATED BY:')}[\s\/]*(.{2,}?)(?:\s+DBA\s|$)/i");
        $s->airline()->operator($operator);

        $equipment = $this->http->FindSingleNode($xpathExtra . "/descendant::text()[{$this->starts('EQUIPMENT:')}]/ancestor::*[normalize-space()][1]", $root, true, "/^{$this->opt('EQUIPMENT:')}\s*(.{2,})$/i");
        $s->extra()->aircraft($equipment, false, true);
    }
    */

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^CANCEL BY (?<hour>{$this->patterns['time']}) LOCAL TIME DAY OF ARRIVAL/", $cancellationText, $m)
            || preg_match("/^CANCEL BY (?<hour>{$this->patterns['time']}) DAY OF ARRIVAL, LOCAL TIME/", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('0 days', $m['hour']);
        } elseif (preg_match("/^CANCEL (?<prior>24 HOURS?) PRIOR TO LOCAL TIME DAY OF ARRIVAL/", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        }
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function changeBody(\PlancakeEmailParser $parser)
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html)) {
                $result[] = $html;
            }
        }
        $this->http->SetEmailBody(implode("\n", $result), true);

        return;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeTime($str)
    {
        $in = [
            "#^\s*(\d{2})(\d{2})([AP])\s*$#", // 0630A
            "#^\s*(\d{1,2})([AP])\s*$#", // 7A
        ];
        $out = [
            "$1:$2 $3M",
            "$1:00 $2M",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }
}
