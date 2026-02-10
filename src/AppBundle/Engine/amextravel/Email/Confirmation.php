<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-1.eml, amextravel/it-11099673.eml, amextravel/it-11362452.eml, amextravel/it-1641536.eml, amextravel/it-17.eml, amextravel/it-1724156.eml, amextravel/it-1724157.eml, amextravel/it-1753079.eml, amextravel/it-8383056.eml, amextravel/it-52164697.eml, amextravel/it-101132064.eml, amextravel/it-103187175.eml, amextravel/it-1484644.eml, amextravel/it-3035965.eml, amextravel/it-34656001.eml, amextravel/it-3840601.eml, amextravel/it-64851602.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'tripId'          => ['Trip ID', 'AMEX TRAVEL TRIP ID'],
            'Adult'           => ['Adult', 'Child', 'Infant In Lap', 'Infant'],
            'Loyalty Program' => ['Loyalty Program', 'TSA Known Traveler #:'],
            'Taxes & Airline' => ['Taxes & Airline', 'Estimated Taxes and Fees'],
            'Cancellation'    => ['Cancellation', 'Room Restrictions and Cancellation Policy'],
            'Total Cost'      => ['Total Cost', 'Cost', 'Total Cost:'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Reservation Confirmation for Trip ID', 'Change for Trip ID', 'Flight Schedule Change for Trip ID'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'American Express Travel') !== false
            || stripos($from, '@amextravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['subject'], 'My American Express Travel Itinerary') !== false) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'American Express Travel') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[' . $this->contains(["You understand and agree that American Express Travel Related Services Company", 'Thank you for booking with American Express Travel', 'Amex assists you in finding travel suppliers', 'AMEX TRAVEL TRIP ID:', '@amextravel.com']) . ']')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"myamextravel.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if (
            $this->http->XPath->query('//node()[contains(normalize-space(.),"Your Seats:") or contains(normalize-space(.),"Your Seats :") or contains(normalize-space(.),"Your seats:") or contains(normalize-space(.),"Your seats :")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(.),"Drop Off Date & Time")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(.),"Your Selected Hotel")]')->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        //General
        $f->general()
            ->travellers($this->http->FindNodes("//text()[normalize-space()='TRAVELERS']/following::text()[starts-with(normalize-space(), 'Passenger')]/following::text()[normalize-space()][1]"), true);

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your itinerary has changed')]");

        if (!empty($status)) {
            $f->general()
                ->status('changed');
        }
        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'email confirms that your booking has been cancelled')]");

        if (!empty($status)) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        }

        $airlinePNRs = [];

        // Step 1: parse PNRs at top
        $pnrTitles = $this->http->XPath->query("//*[ normalize-space()='' and descendant::img[contains(@src,'/record_locator-Plane.')] ]/following-sibling::*[1]/descendant::li[contains(.,':')]");

        foreach ($pnrTitles as $pnrTitle) {
            $title = $this->http->FindSingleNode('.', $pnrTitle, true, '/^(.+?)[\s:：]*$/u');

            foreach ($this->http->XPath->query("following-sibling::li[normalize-space()]", $pnrTitle) as $numberRow) {
                $number = $this->http->FindSingleNode('.', $numberRow);

                if (preg_match("/^.+:$/", $number)) {
                    continue 2;
                } elseif (preg_match("/^[A-Z\d]{5,}$/", $number)) {
                    if (empty($airlinePNRs[$title])) {
                        $airlinePNRs[$title] = $number;
                    } elseif ($airlinePNRs[$title] !== $number) {
                        $airlinePNRs[$title] = array_merge((array) $airlinePNRs[$title], [$number]);
                    }
                }
            }
        }

        // Step 2: parse PNRs at bottom
        $pnrRows = [];
        $travelersPrecedingRows = array_reverse($this->http->FindNodes("//tr[ {$this->eq($this->t('TRAVELERS'))} and preceding::tr[{$this->eq($this->t('RECORD LOCATORS'))}] ]/preceding::tr[not(.//tr) and normalize-space()]"));

        foreach ($travelersPrecedingRows as $tpRow) {
            if ($tpRow === 'RECORD LOCATORS') {
                break;
            } else {
                $pnrRows[] = $tpRow;
            }
        }
        $pnrRows = array_filter(array_unique($pnrRows), function ($item) {
            return strpos($item, ' ') !== false;
        });

        foreach ($pnrRows as $pnrRow) {
            if (preg_match("/^\s*(.{2,}?)\s+([A-Z\d]{5,})\s*$/", $pnrRow, $m)) {
                $title = $m[1];
                $number = $m[2];

                if (empty($airlinePNRs[$title])) {
                    $airlinePNRs[$title] = $number;
                } elseif ($airlinePNRs[$title] !== $number) {
                    $airlinePNRs[$title] = array_merge((array) $airlinePNRs[$title], [$number]);
                }
            }
        }

        $f->general()->noConfirmation();

        //Accounts
        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='TRAVELERS']/following::text()[" . $this->starts($this->t('Loyalty Program')) . "]/ancestor::tr[1]/td[2]")));

        if (empty($accounts)) {
            $accounts = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='TRAVELERS']/following::text()[" . $this->eq($this->t('Loyalty Program')) . "]/following::text()[1]")));
        }
        $accounts = array_diff($accounts, ['--']);

        foreach ($accounts as $account) {
            $pax = $this->http->FindSingleNode("//text()[{$this->contains($account)}]/ancestor::table[2]/descendant::text()[starts-with(normalize-space(), 'Passenger ')]/following::text()[normalize-space()][1]");

            if (!empty($pax)) {
                $f->program()
                    ->account($account, (preg_match('/^\*{3,}/', $account)) ? true : false, $pax);
            } else {
                $f->program()
                    ->account($account, (preg_match('/^\*{3,}/', $account)) ? true : false);
            }
        }

        //Ticket Numbers
        $ticketNumbers = array_filter($this->http->FindNodes("//text()[normalize-space()='TRAVELERS']/following::text()[starts-with(normalize-space(), 'Ticket Number')]/following::text()[normalize-space()][1]", null, "/^([\d\,]+)$/"));

        foreach ($ticketNumbers as $ticketNumber) {
            $tickets = [];

            if (stripos($ticketNumber, ',') !== false) {
                $tickets = array_merge($tickets, explode(',', $ticketNumber));
            } else {
                $tickets[] = $ticketNumber;
            }

            foreach (array_unique(array_filter($tickets)) as $ticket) {
                $pax = $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Passenger ')]/following::text()[normalize-space()][1]");

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        //Segments
        $xpath = '//img[contains(@src, "your-flight-details")]/ancestor::tr[1]/following-sibling::tr/td/table/descendant::tr[count(descendant::tr)=0 and contains(.,"Seats")] | //img[contains(@src, "your-rental-details")]/ancestor::*/following-sibling::table[1] | //text()[normalize-space()="Your Seats:"]/ancestor::tr[1][./preceding-sibling::tr]';
        $nodes = $this->http->XPath->query($xpath);
        $i = 0;

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode("./ancestor::table[2]/descendant::tr[1]/td[1]", $root);

            if (preg_match("/(?<AirlineName>[\D]+?)\s*(?<FlightNumber>\d+)\s*Operated\s+by\s*(?<Operator>.*)/", $airlineText, $m)) {
                $s->airline()
                    ->name($m['AirlineName'])
                    ->number($m['FlightNumber'])
                    ->operator($m['Operator']);

                if (isset($airlinePNRs[$m['AirlineName']]) && !empty($airlinePNRs[$m['AirlineName']]) && is_array($airlinePNRs[$m['AirlineName']])) {
                    $s->airline()
                        ->confirmation($airlinePNRs[$m['AirlineName']][$key]);
                } elseif (!empty($airlinePNRs[$m['AirlineName']])) {
                    $s->airline()
                        ->confirmation($airlinePNRs[$m['AirlineName']]);
                } else {
                    $key = array_key_first($airlinePNRs);

                    if (stripos($key, $m['AirlineName']) !== false) {
                        $confs = array_values($airlinePNRs[$key]);

                        if (is_array($confs)) {
                            $s->airline()
                                ->confirmation($confs[$i]);
                        } else {
                            $s->airline()
                                ->confirmation($airlinePNRs[$key]);
                        }
                    }
                }

                ++$i;

                $seats = explode(',', $this->http->FindSingleNode(".", $root, true, "/\:\s*(.+)$/"));

                if (count($seats) > 0) {
                    $s->extra()
                        ->seats($seats);
                }
            }

            $depArrDate = null;
            $depArrDates = array_filter($this->http->FindNodes("preceding::tr[contains(normalize-space(),',') and not(contains(normalize-space(),'('))]", $root, "/^[-[:alpha:]]+\s*,\s*[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{2,4}/u"));

            if (count($depArrDates)) {
                $depArrDate = array_reverse($depArrDates)[0];
            }

            $departText = $this->http->FindSingleNode("./preceding::tr[contains(normalize-space(), ':')][1]/td[1]", $root);

            if (preg_match("/^(?<depTime>[\d\:]+\s*a?p?m)\s*\D+\((?<depCode>[A-Z]{3})\)\s*$/", $departText, $m)) {
                $s->departure()
                    ->date(strtotime($depArrDate . ' ' . $m['depTime']))
                    ->code($m['depCode']);
            }
            $arrivText = $this->http->FindSingleNode("./preceding::tr[contains(normalize-space(), ':')][1]/td[normalize-space()][2]", $root);

            if (preg_match("/^(?<arrTime>[\d\:]+\s*a?p?m)\s*\D+\((?<arrCode>[A-Z]{3})\)\s*$/", $arrivText, $m)) {
                $s->arrival()
                    ->date(strtotime($depArrDate . ' ' . $m['arrTime']))
                    ->code($m['arrCode']);
            }

            $cabinText = $this->http->FindSingleNode("./preceding::tr[contains(normalize-space(), ',')][2]/td[2]", $root);

            // like duration, does not apply to a specific flight
//            if (preg_match("/^non[-\s]*stop(?:\s*\||$)/i", $cabinText)) {
//                $s->extra()->stops(0);
//            } elseif (preg_match("/^(\d{1,3})\s*stops?(?:\s*\||$)/i", $cabinText, $m)) {
//                $s->extra()->stops($m[1]);
//            }

            if (preg_match("/(?:^|\|\s*)(Premium Economy|Economy|Business|First)(?:\s*\||$)/i", $cabinText, $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match("/(?:^|\|)\s*(?<nextday>[-+] ?\d Days?)\s*(?:\||$)/", $cabinText, $m)) {
                if (!empty($s->getArrDate())) {
                    $s->arrival()->date(strtotime($m['nextday'], $s->getArrDate()));
                }
            }

            $durationXpath = [
                "./ancestor::table[1]/descendant::img[contains(@src,'/clock.')]/following::text()[normalize-space(.)][1]",
                "./ancestor::table[1]/descendant::text()[contains(normalize-space(.),'Departure Flight')]/following::text()[normalize-space(.)][1]",
            ];
            $duration = null;

            foreach ($durationXpath as $dXpath) {
                $duration = $this->http->FindSingleNode($dXpath, $root, true, '/^(\d+h\s+\d+m)\b/i');

                if (!empty($duration)) {
                    break;
                }
            }
            $key++;

            if (!empty($duration)) {
                if (!isset($nodes[$key])) {
                    $s->extra()
                        ->duration($duration);
                } else {
                    $durationNext = null;

                    foreach ($durationXpath as $dXpath) {
                        $durationNext = $this->http->FindSingleNode($dXpath, $nodes[$key], true,
                            '/^(\d+h\s+\d+m)\b/i');

                        if (!empty($durationNext)) {
                            break;
                        }
                    }

                    if (!empty($durationNext)) {
                        $s->extra()
                            ->duration($duration);
                    }
                }
            }
        }
    }

    public function parseRental(Email $email): void
    {
        $r = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{10,})$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//img[contains(@src, 'record_locator-Car')]/following::text()[normalize-space()][not(contains(normalize-space(), ':'))][1]", null, true, "/^([A-Z\d]{10,})$/");
        }

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation, $this->t('Confirmation Number'));
        } else {
            $r->general()
                ->noConfirmation();
        }

        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Main Contact'))}]/following::text()[normalize-space()][1]"), true);

        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'email confirms that your booking has been cancelled')]");

        if (!empty($status)) {
            $r->general()
                ->cancelled()
                ->status('cancelled');
        }

        $r->pickup()
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick Up Date'))}]/following::text()[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick Up Location'))}]/following::text()[normalize-space()][1]/ancestor::p[1]"));

        $pickUpHours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick Up Location'))}]/following::text()[{$this->eq($this->t('Hours of Operation'))}][1]/following::text()[normalize-space()][1]/ancestor::p[1]");

        if (!empty($pickUpHours)) {
            $r->pickup()
                ->openingHours($pickUpHours);
        }

        $r->dropoff()
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Drop Off Date'))}]/following::text()[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop Off Location'))}]/following::text()[normalize-space()][1]/ancestor::p[1]"));

        $dropOffHours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop Off Location'))}]/following::text()[{$this->eq($this->t('Hours of Operation'))}][1]/following::text()[normalize-space()][1]/ancestor::p[1]");

        if (!empty($dropOffHours)) {
            $r->dropoff()
                ->openingHours($dropOffHours);
        }

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Doors:'))}]/preceding::text()[{$this->contains($this->t('Car'))}][1]"))
            ->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('Doors:'))}]/preceding::text()[{$this->contains($this->t('Car'))}][1]/following::text()[normalize-space()][1]"));

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Main Contact'))}]/following::text()[normalize-space()][2]", null, true, "/^([X]+\d+)$/");

        if (!empty($number)) {
            $r->program()->account($number, true);
        }

        $costText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Cost'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\s*(?<currency>[^\s\d]{1})\s*(?<cost>\d[\d\,\.]+)\s*$/", $costText, $m)) {
            $r->price()
                ->cost((str_replace(',', '', $m['cost'])))
                ->currency($this->normalizeCurrency($m['currency']));
        }
    }

    public function parseHotel(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Your Selected Hotel'))}]/ancestor::*[{$this->contains($this->t('ROOM DETAILS'))}][1][count(.//text()[{$this->eq($this->t('Your Selected Hotel'))}]) = 1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $email->add()->hotel();
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            if ($this->http->XPath->query(".//text()[{$this->eq($this->t('Your Selected Hotel'))}]/preceding::text()[normalize-space()][position() < 3][{$this->starts($this->t('This hotel reservation has been cancelled'))}]")->length > 0) {
                $h->general()
                    ->cancelled()
                    ->status('cancelled');
            }

            $xpathHotel = ".//text()[{$this->eq($this->t('Your Selected Hotel'))}]/ancestor::*[{$this->eq($this->t('Your Selected Hotel'))}][following-sibling::*[3][count(.//img) = 2]]/following-sibling::*";

            $hotelName = $this->http->FindSingleNode($xpathHotel . "[1][not(.//img)]", $root);
            $address = trim($this->http->FindSingleNode($xpathHotel . "[2][not(.//img)]", $root), " .,");

            $h->hotel()
                ->name($hotelName)
                ->address($address)
            ;

            $confNos = array_values(array_unique(array_filter($this->http->FindNodes("(.//text()[{$this->eq($hotelName)}])[1]/ancestor::tr[1]/descendant::text()[{$this->starts($this->t('Room '))}]/following::text()[normalize-space()!=''][1]",
                null, "#^([A-Z\d\-]+)$#"))));

            if (count($confNos) > 0) {
                foreach ($confNos as $confNo) {
                    $h->general()
                        ->confirmation($confNo);
                }
            }

            if (empty($confNos) && $nodes->length === 1) {
                $roomConfNumbers = [];
                $roomConfNumberRows = $this->http->XPath->query("//td[ descendant::text()[normalize-space()][1][{$this->eq($this->t('HOTEL:'))}] ]/descendant::text()[{$this->starts($this->t('Room'))}]");

                foreach ($roomConfNumberRows as $roomConfRow) {
                    $number = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $roomConfRow, true,
                        '/^[-A-Z\d]{5,}$/');
                    $title = $this->http->FindSingleNode(".", $roomConfRow, true, '/^(.+?)[\s:：]*$/u');

                    if (!$number || !$title) {
                        break;
                    }

                    if (empty($roomConfNumbers[$number])) {
                        $roomConfNumbers[$number] = [$title];
                    } else {
                        $roomConfNumbers[$number][] = $title;
                    }
                }

                foreach ($roomConfNumbers as $number => $titles) {
                    $h->general()->confirmation($number, implode(' + ', array_unique($titles)));
                }
            }

            if (empty($confNos)) {
                if (empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('AMEX TRAVEL TRIP ID:'))}])[1]"))
                    && empty($this->http->FindSingleNode("(//img[contains(@alt, 'Record_locator_banner') or contains(@src, 'record_locator_banner')]/following::text()[normalize-space()])[1]"))) {
                    $h->general()
                        ->noConfirmation();
                }
            }

            $checkIn = $this->http->FindSingleNode($xpathHotel . "[3][count(.//td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][1]",
                $root, true, "/^.*\b\d{4}\b.*$/");
            $checkOut = $this->http->FindSingleNode($xpathHotel . "[3][count(.//td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][2]",
                $root, true, "/^.*\b\d{4}\b.*$/");
            $h->booked()
                ->checkIn2($checkIn)
                ->checkOut2($checkOut);

            $roomsCount = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('ROOM DETAILS'))}]/ancestor::*[{$this->eq($this->t('ROOM DETAILS'))} and preceding-sibling::*][1]/preceding-sibling::*[normalize-space()][1]",
                $root, true, "/^\s*(\d{1,2})\s*{$this->opt($this->t('Room'))}/");
            $h->booked()->rooms($roomsCount);

            $roomDetails = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('ROOM DETAILS'))}]/ancestor::*[{$this->eq($this->t('ROOM DETAILS'))} and following-sibling::*][1]/following-sibling::*[normalize-space()][1]", $root);

            if ($roomDetails) {
                $room = $h->addRoom();
                $room->setDescription($roomDetails);
            }

            $cancellation = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t('Cancellation'))}]/ancestor::*[{$this->eq($this->t('Cancellation'))} and following-sibling::*][1])[1]/following-sibling::*[normalize-space()][1]", $root);

            if ($cancellation) {
                $h->general()->cancellation($cancellation);

                $this->detectDeadLine($h);
            }

            $guestNames = array_filter($this->http->FindNodes(".//text()[{$this->contains($this->t('main guest:'))}][1]/following::text()[normalize-space()!=''][1]",
                $root, "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*$/u"));

            if (empty($guestNames)) {
                $guestNames = array_filter($this->http->FindNodes("./following::text()[{$this->eq($this->t('Guest'))}][1]/ancestor::*[not({$this->eq($this->t('Guest'))})][1]//text()[{$this->contains($this->t('main guest:'))}][1]/following::text()[normalize-space()!=''][1]",
                    $root, "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*$/u"));
            }

            if (empty($guestNames) && count($email->getItineraries()) > 0 && $email->getItineraries()[0]->getType() === 'flight') {
                $guestNames = array_column($email->getItineraries()[0]->getTravellers(), 0);
            }

            if (count($guestNames)) {
                $h->general()->travellers(array_unique($guestNames), true);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $tripID = $this->http->FindSingleNode("//text()[{$this->starts($this->t('tripId'))}]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('tripId'))}]/ancestor::*[contains(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆')][1]", null, true, "/^.*\d.*$/")
        ;

        if (preg_match("/^({$this->opt($this->t('tripId'))}).*:\s*([-A-Z\d]{5,})$/", $tripID, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        if ($this->http->XPath->query("//*[self::h1 or self::p][{$this->eq($this->t('TRAVELERS'))}]")->length > 0) {
            $this->parseFlight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Pick Up Location'))}]")->length === 1) {
            $this->parseRental($email);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('ROOM DETAILS'))}]")->length === 1) {
            $this->parseHotel($email);
        }

        $this->parsePrice($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $cancellationText = $h->getCancellation();

        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("/CANCEL BY (?<prior>\d+ DAYS?) PRIOR TO AVOID \d+ NIGHTS PENALTY/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1], '00:00');
        }

        if (preg_match("#^\s*Cancellations or changes made after (?<time>\d+:\d+) \(hotel local time\) on (?<date>.+?) or no-shows are subject to a property fee equal to the first nights rate plus taxes and fees\.\s*$#",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("/Cancell?ations (?i)of your package will incur a cancellation fee\./", $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }
        $h->booked()
            ->parseNonRefundable("/This reservation is non-refundable/")
            ->parseNonRefundable("/Cancellations or changes made within (?:\d+ days?) prior to (?:\d+:\d+ [ap]m) local hotel time on the day of arrival are subject to a .+? charge./i");
    }

    private function parsePrice(Email $email)
    {
        //Price
        $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Taxes & Airline'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D*(\d[\d\.,]*)\D*$/");

        if (!empty($tax)) {
            $email->price()
                ->tax($tax);
        }

        $fees = $this->http->XPath->query("//text()[{$this->eq($this->t('Travel Insurance'))}]/ancestor::tr[1]");

        foreach ($fees as $froot) {
            $amount = $this->http->FindSingleNode("td[2]", $froot, true, "/^\D*(\d[\d\.,]*)\D*$/");

            if (!empty($amount)) {
                $email->price()
                    ->fee($this->http->FindSingleNode("td[1]", $froot), $amount);
            }
        }

        if (count($email->getItineraries()) === 1 && $email->getItineraries()[0]->getType() === 'flight') {
            $cost = array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Adult'))}]/ancestor::tr[1]/descendant::td[2]",
                null, "/^\D*(\d[\d\.,]*)\D*$/"));

            if (!empty($cost)) {
                $email->price()
                    ->cost($cost);
            }
        }

        $spentAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points Used'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($spentAwards)) {
            $email->price()
                ->spentAwards($spentAwards);
            $totalText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points Used'))}]/following::text()[{$this->contains($this->t('to Pay'))}]/ancestor::tr[1]/descendant::td[2]");

            if (preg_match("/^\s*\S?(?<currency>[^\s\d]{1})\s*(?<total>\d[\d\,\.]+)\s*$/", $totalText, $m)) {
                $email->price()
                    ->total((str_replace(',', '', $m['total'])))
                    ->currency($this->normalizeCurrency($m['currency']));
            }
        } else {
            $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost'))}]/ancestor::tr[1]/descendant::td[2]");

            if (preg_match("/^\s*[^\s\d]?(?<currency>[^\s\d]{1})\s*(?<total>\d[\d\,\.]*)\s*$/", $totalText, $m)) {
                $email->price()
                    ->total(str_replace(',', '', $m['total']))
                    ->currency($this->normalizeCurrency($m['currency']));
            }
        }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
