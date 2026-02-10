<?php

namespace AwardWallet\Engine\classicvacations\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class StatementForBooking extends \TAccountChecker
{
    public $mailFiles = "classicvacations/it-661017904.eml, classicvacations/it-663145365.eml, classicvacations/it-664715322.eml, classicvacations/it-673777043.eml";

    public $infoForTransfers;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            ['Confirmation #:' => 'Confirmation #:', 'CONFIRMATION #:'],
        ],
    ];

    private $detectFrom = "statement@classicvacations.com";
    private $detectSubject = [
        // en
        'Travel Advisor Statement for Booking#',
    ];
    private $detectBody = [
        'en' => [
            'Please review the below itinerary for accuracy',
            'Please review the attached itinerary for accuracy',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]classicvacations\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['classicvacations.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['choosing Classic Vacations'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts('Booking details for booking')}]", null, true,
            "/Booking details for booking (\d{5,})\s*$/");
        $email->ota()
            ->confirmation($conf);

        $xpath = "//tr[count(*) = 2][descendant::*[normalize-space()][1][contains(., '|')]][*[2]/descendant::text()[normalize-space()][1][{$this->eq('Status')}]]/ancestor::table[1]/ancestor::*[1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        $transfers = [];

        foreach ($nodes as $root) {
            $header = strtolower($this->http->FindSingleNode("descendant::*[normalize-space()][1][contains(., '|')]", $root, true,
                "/^\s*(.+?)\s*\|/"));

            // $this->logger->debug('$header = '.print_r( $header,true));

            switch ($header) {
                case in_array($header, ['flight']):
                    $this->parseFlight($email, $root);

                    break;

                case in_array($header, ['hotel']):
                    $this->parseHotel($email, $root);

                    break;

                case in_array($header, ['arrival transfer', 'departure transfer']):
                    $transfers[] = $root;

                    break;

                case in_array($header, ['excursion']):
                    $this->parseEvent($email, $root);

                    break;

                default:
                    $this->logger->debug('type segment error');
                    $email->add()->cruise();
            }
        }

        foreach ($transfers as $root) {
            // after hotel and flight
            $this->parseTransfer($email, $root);
        }

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'transfer' && count($it->getSegments()) === 0) {
                $email->removeItinerary($it);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price'))}]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
            || preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $email->price()
                ->total($m['amount'])
                ->currency($m['currency']);
        } else {
            $email->price()
                ->total(null);
        }

        return true;
    }

    private function parseFlight(Email $email, $root)
    {
        $confs = array_unique($this->http->FindNodes("descendant::text()[{$this->eq($this->t('Confirmation #:'))}]/ancestor::*[not({$this->eq($this->t('Confirmation #:'))})][{$this->starts($this->t('Confirmation #:'))}][1]",
            $root, "/^\s*{$this->opt($this->t('Confirmation #:'))}\s*(.+)/"));
        $travellers = array_unique($this->http->FindNodes("descendant::text()[{$this->starts($this->t('Passenger Seat Assignments'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/*/*[1]",
            $root, "/^\s*(?:(?:Mr|Mrs|Ms|Mstr|Dr)\.\s*)?(.+?)\s*(?:\(.+\))?\s*$/i"));

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight') {
                $f = $it;

                foreach ($confs as $conf) {
                    if (!in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                        $f->general()
                            ->confirmation($conf);
                    }
                }

                foreach ($travellers as $name) {
                    if (!in_array($name, array_column($it->getTravellers(), 0))) {
                        $f->general()
                            ->traveller($name, true);
                    }
                }

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
            $f->general()
                ->travellers($travellers, true)
                ->status($this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::td[1]/following-sibling::td[1]", $root, null,
                    "/^\s*{$this->opt($this->t('Status'))}\s*(.+)/"));
        }

        // Segments

        $xpath = "descendant::tr[*[1][{$this->starts($this->t('Departure'))}]][*[2][{$this->starts($this->t('Arrival'))}]][preceding-sibling::tr[.//text()[{$this->eq($this->t('Flight'))}]]]/ancestor::table[1]";

        $nodes = $this->http->XPath->query($xpath, $root);

        foreach ($nodes as $sRoot) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Flight'))}]/ancestor::*[not({$this->eq($this->t('Flight'))})][{$this->starts($this->t('Flight'))}][1]",
                $sRoot, null, "/^\s*{$this->opt($this->t('Flight'))}\s*(.+)/");

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $re = "/^\s*.+(?<overnight>\n[\-+]\d\s*)?\n\s*(?<code>[A-Z]{3})\n\s*(?<date>.+)\n\s*(?<name>.+)\s*$/";
            // Departure
            $depart = implode("\n",
                $this->http->FindNodes("descendant::tr[*[1][{$this->starts($this->t('Departure'))}]][*[2][{$this->starts($this->t('Arrival'))}]]/*[1]//text()[normalize-space()]",
                    $sRoot));

            if (preg_match($re, $depart, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date']));

                if (!empty($m['overnight']) && !empty($s->getDepDate())) {
                    $s->departure()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getDepDate()));
                }
                $d = $s->getDepDate() ? strtotime('00:00', $s->getDepDate()) : null;
                $this->infoForTransfers[$d]['flightDep'][] = $s->getDepCode() . ' - ' . $s->getDepName() . ' - ' . ($s->getDepDate() ? date('H:i',
                        $s->getDepDate()) : '');
            }

            // Arrival
            $arrive = implode("\n",
                $this->http->FindNodes("descendant::tr[*[1][{$this->starts($this->t('Departure'))}]][*[2][{$this->starts($this->t('Arrival'))}]]/*[2]//text()[normalize-space()]",
                    $sRoot));

            if (preg_match($re, $arrive, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date']));

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getArrDate()));
                }
                $d = $s->getArrDate() ? strtotime('00:00', $s->getArrDate()) : null;
                $this->infoForTransfers[$d]['flightArr'][] = $s->getArrCode() . ' - ' . $s->getArrName() . ' - ' . ($s->getDepDate() ? date('H:i',
                        $s->getArrDate()) : '');
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("descendant::tr[*[1][{$this->starts($this->t('Departure'))}]][*[2][{$this->starts($this->t('Arrival'))}]]/*[3]",
                    $sRoot, null, "/^\s*{$this->opt($this->t('Duration'))}\s*(.+)/"))
                ->cabin($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Class'))}]/ancestor::*[not({$this->eq($this->t('Class'))})][{$this->starts($this->t('Class'))}][1]",
                    $sRoot, null, "/^\s*{$this->opt($this->t('Class'))}\s*(.+)/"));

            $seats = array_filter($this->http->FindNodes("following::text()[normalize-space()][position() < 3][{$this->starts($this->t('Passenger Seat Assignments'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/*/*[2]",
                $sRoot, "/^\s*(\d{1,3}[A-Z])\s*$/"));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    private function parseHotel(Email $email, $root)
    {
        $h = $email->add()->hotel();

        // General
        $conf = str_replace(' ', '', $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation #:'))}]/ancestor::*[not({$this->eq($this->t('Confirmation #:'))})][{$this->starts($this->t('Confirmation #:'))}][1]",
            $root, null, "/^\s*{$this->opt($this->t('Confirmation #:'))}\s*(.+)/"));

        if (empty($conf) && empty($this->http->FindSingleNode("(./descendant::node()[{$this->contains($this->t('Confirmation #:'))}])[1]", $root))) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($conf);
        }
        $h->general()
            ->status($this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::td[1]/following-sibling::td[1]", $root, null,
                "/^\s*{$this->opt($this->t('Status'))}\s*(.+)/"))
        ;
        $travellers = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/*",
            $root, "/^\s*(?:(?:Mr|Mrs|Ms|Mstr|Dr)\.\s*)?(.+?)\s*(?:\(.+\))?\s*$/i");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Who\'s Checking In?'))}]/ancestor::*[not({$this->eq($this->t('Who\'s Checking In?'))})][{$this->starts($this->t('Who\'s Checking In?'))}][1]",
                $root, "/^\s*{$this->opt($this->t('Who\'s Checking In?'))}\s*(.+)/");
        }

        $h->general()
            ->travellers($travellers, true);

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1][count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][contains(., ' | ')]]/descendant::text()[normalize-space()][2]", $root))
            ->phone($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Phone:'))}]/ancestor::*[not({$this->eq($this->t('Phone:'))})][{$this->starts($this->t('Phone:'))}][1]",
                $root, null, "/^\s*{$this->opt($this->t('Phone:'))}\s*(.+)/"));

        $address = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Address:'))}]/ancestor::*[not({$this->eq($this->t('Address:'))})][{$this->starts($this->t('Address:'))}][1]",
            $root, null, "/^\s*{$this->opt($this->t('Address:'))}\s*(.+)/");

        // decode country for transfers
        $address = preg_replace(["/, GR\s*$/", "/, IT\s*$/"], [', Greece', ', Italy'], $address);

        $h->hotel()
            ->address($address);

        // Booked
        $date = strtotime($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check In'))}]/ancestor::td[1]", $root, null, "/^\s*{$this->opt($this->t('Check In'))}\s*(.+)/"));
        $time = $this->http->FindSingleNode(".//text()[{$this->starts('Check-in from')}]", $root, true, "/{$this->opt('Check-in from')}\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:\s*-|$)/i");

        if (!empty($date) && !empty($time)) {
            $h->booked()
                ->checkIn(strtotime($time, $date));
        } else {
            $h->booked()
                ->checkIn($date);
        }
        $date = strtotime($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check Out'))}]/ancestor::td[1]", $root, null, "/^\s*{$this->opt($this->t('Check Out'))}\s*(.+)/"));
        $time = $this->http->FindSingleNode(".//text()[{$this->starts('Check-out before -')}]", $root, true, "/{$this->opt('Check-out before -')}\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:\s*-|$)/i");

        if (!empty($date) && !empty($time)) {
            $h->booked()
                ->checkOut(strtotime($time, $date));
        } else {
            $h->booked()
                ->checkOut($date);
        }

        $d = $h->getCheckInDate() ? strtotime('00:00', $h->getCheckInDate()) : null;
        $this->infoForTransfers[$d]['hotelCheckIn'][] = $h->getAddress();

        $d = $h->getCheckOutDate() ? strtotime('00:00', $h->getCheckOutDate()) : null;
        $this->infoForTransfers[$d]['hotelCheckOut'][] = $h->getAddress();

        // Room
        $r = $h->addRoom();
        $type = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Accommodations'))}]/ancestor::td[1]", $root, null, "/^\s*{$this->opt($this->t('Accommodations'))}\s*(.+)/");

        if (preg_match("/^\s*(\S.{5,}) - (.+)/", $type, $m)) {
            $r->setType($m[1])
                ->setRateType($m[2]);
        }
    }

    private function parseTransfer(Email $email, $root)
    {
        $conf = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation #:'))}]/ancestor::*[not({$this->eq($this->t('Confirmation #:'))})][{$this->starts($this->t('Confirmation #:'))}][1]",
            $root, null, "/^\s*{$this->opt($this->t('Confirmation #:'))}\s*(.+)/");
        $travellers = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/*",
            $root, "/^\s*(?:(?:Mr|Mrs|Ms|Mstr|Dr)\.\s*)?(.+?)\s*(?:\(.+\))?\s*$/i");

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'transfer') {
                $t = $it;

                if ($conf !== '--' && !in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    $t->general()
                        ->confirmation($conf);
                }

                foreach ($travellers as $name) {
                    if (!in_array($name, array_column($it->getTravellers(), 0))) {
                        $t->general()
                            ->traveller($name, true);
                    }
                }

                break;
            }
        }

        if (!isset($t)) {
            $t = $email->add()->transfer();

            if ($conf == '--') {
                $t->general()
                    ->noConfirmation();
            } else {
                $t->general()
                    ->confirmation($conf);
            }
            $t->general()
                ->travellers($travellers, true)
                ->status($this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::td[1]/following-sibling::td[1]", $root, null,
                    "/^\s*{$this->opt($this->t('Status'))}\s*(.+)/"));
        }

        // Segment
        $s = $t->addSegment();

        $dDate = null;
        $aDate = null;
        $date = strtotime($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space()][1]", $root));

        $route = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Departure Transfer'))} or {$this->eq($this->t('Arrival Transfer'))}]/ancestor::*[not({$this->eq($this->t('Departure Transfer'))}) and not({$this->eq($this->t('Arrival Transfer'))})][{$this->starts($this->t('Departure Transfer'))} or {$this->starts($this->t('Arrival Transfer'))}][1]",
            $root, null, "/^\s*(?:{$this->opt($this->t('Departure Transfer'))}|{$this->opt($this->t('Arrival Transfer'))})\s*(.+)/");

        if (preg_match("/from (.+?) to (.+?)( by .+)? for /", $route, $m)) {
            $dName = $m[1];
            $aName = $m[2];
            $s->departure()
                ->name($dName);

            if (preg_match("/^\s*[A-Z]{3}\s*$/", $dName)) {
                $s->departure()
                    ->code($dName);

                if (!empty($date)
                    && !empty($this->infoForTransfers[$date])
                    && !empty($this->infoForTransfers[$date]['flightArr'])
                    && preg_match_all("/^\s*({$dName}) - .* - (.*)$/m", implode("\n", $this->infoForTransfers[$date]['flightArr']), $m)
                    && count($m[1]) == 1
                ) {
                    $dDate = $m[2][0];
                }
            } elseif (preg_match("/\bHotel\b/i", $dName) && !empty($date)
                && !empty($this->infoForTransfers[$date]) && count(array_unique($this->infoForTransfers[$date]['hotelCheckOut'])) === 1
            ) {
                $s->departure()
                    ->address($this->infoForTransfers[$date]['hotelCheckOut'][0]);

                if (preg_match("/, ([A-Z]{2})\s*$/", $this->infoForTransfers[$date]['hotelCheckOut'][0], $m)) {
                    $s->departure()
                        ->geoTip($m[1]);
                }
            } elseif (preg_match("/\bAirport\b/i", $dName) && !empty($date)
                && !empty($this->infoForTransfers[$date])
                && !empty($this->infoForTransfers[$date]['flightArr'])
                && preg_match_all("/^\s*([A-Z]{3}) - {$dName} - (.*)$/m", implode("\n", $this->infoForTransfers[$date]['flightArr']), $m)
                && count($m[1]) == 1
            ) {
                $s->departure()
                    ->code($m[1][0]);
                $dDate = $m[2][0];
            } else {
                $addName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Pick-up'))}]/ancestor::*[{$this->starts($this->t('Pick-up'))}][count(.//text()[normalize-space()]) = 3]/descendant::text()[normalize-space()][2]", $root);

                if (!empty($addName)) {
                    $s->departure()
                        ->name($addName . ', ' . $dName);
                }
            }

            if (empty($s->getDepAddress()) && empty($s->getDepCode()) && !preg_match('/\d+/', $dName)) {
                // большая вероятность, что неверно определится или не определиться адрес и всю письмо уйдет в мусор
                $t->removeSegment($s);

                return true;
            }

            $s->arrival()
                ->name($aName);

            if (preg_match("/^\s*[A-Z]{3}\s*$/", $aName)) {
                $s->arrival()
                    ->code($aName);

                if (!empty($date)
                    && !empty($this->infoForTransfers[$date]) && !empty($this->infoForTransfers[$date]['flightDep'])
                    && preg_match_all("/^\s*({$aName}) - .* - (.*)$/m", implode("\n", $this->infoForTransfers[$date]['flightDep']), $m)
                    && count($m[1]) == 1
                ) {
                    $aDate = $m[2][0];
                }
            } elseif (preg_match("/\bHotel\b/i", $aName) && !empty($date)
                && !empty($this->infoForTransfers[$date]) && count(array_unique($this->infoForTransfers[$date]['hotelCheckIn'])) === 1
            ) {
                $s->arrival()
                    ->address($this->infoForTransfers[$date]['hotelCheckIn'][0]);

                if (preg_match("/, ([A-Z]{2})\s*$/", $this->infoForTransfers[$date]['hotelCheckIn'][0], $m)) {
                    $s->arrival()
                        ->geoTip($m[1]);
                }
            } elseif (preg_match("/\bAirport\b/i", $aName) && !empty($date)
                && !empty($this->infoForTransfers[$date]) && !empty($this->infoForTransfers[$date]['flightDep'])
                && preg_match_all("/^\s*([A-Z]{3}) - {$aName} - (.*)$/m", implode("\n", $this->infoForTransfers[$date]['flightDep']), $m)
                && count($m[1]) == 1
            ) {
                $s->arrival()
                    ->code($m[1][0]);
                $aDate = $m[2][0];
            }

            if (empty($s->getArrAddress()) && empty($s->getArrCode()) && !preg_match('/\d+/', $aName)) {
                // большая вероятность, что неверно определится или не определиться адрес и всю письмо уйдет в мусор
                $t->removeSegment($s);

                return true;
            }
        }

        if (!empty($date)) {
            $comments = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Comments'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/Departure (\d{1,2}:\d{2}[ap]m)/", $comments, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date));
            } elseif (preg_match("/FLT \d+ ARRIVES [A-Z]{3} (\d{1,2}):?(\d{2}[ap]m)/i", $comments, $m)) {
                // A3 FLT 375 ARRIVES ATH 1245PM
                $s->departure()
                    ->date(strtotime($m[1] . ':' . $m[2], $date));
            } elseif (preg_match("/Pick up at (\d{1,2}):?(\d{2}[ap]m?)/i", $comments, $m)) {
                // Pick up at 1000aBA 579 at 100p
                $m[2] = preg_replace("/ap$/", '$1m', $m[2]);
                $s->departure()
                    ->date(strtotime($m[1] . ':' . $m[2], $date));
            }

            if (!empty($dDate) && !empty($aDate)) {
                $s->departure()
                    ->date(strtotime($dDate, $date));
                $s->arrival()
                    ->date(strtotime("- 3 hours", strtotime($aDate, $date)));
            } elseif (!empty($dDate)) {
                $s->departure()
                    ->date(strtotime($dDate, $date));
            } elseif (!empty($aDate)) {
                $s->arrival()
                    ->date(strtotime("- 3 hours", strtotime($aDate, $date)));
            }

            if (empty($s->getArrDate()) && empty($s->getDepDate())) {
                $t->removeSegment($s);
            }

            if (empty($s->getDepDate())) {
                $s->departure()
                    ->noDate();
            }

            if (empty($s->getArrDate())) {
                $s->arrival()
                    ->noDate();
            }
        }
    }

    private function parseEvent(Email $email, $root)
    {
        $comments = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Comments'))}]/following::text()[normalize-space()][1]", $root);

        if (preg_match("/Departure (\d{1,2}:\d{2}[ap]m)/", $comments)) {
        } else {
            $this->logger->debug('event time not specified');

            return true;
        }
        $ev = $email->add()->event();

        $ev->type()
            ->event();

        // General
        $ev->general()
            ->confirmation($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation #:'))}]/ancestor::*[not({$this->eq($this->t('Confirmation #:'))})][{$this->starts($this->t('Confirmation #:'))}][1]",
                $root, null, "/^\s*{$this->opt($this->t('Confirmation #:'))}\s*(.+)/"))
            ->travellers($this->http->FindNodes("descendant::text()[{$this->eq($this->t('Participants'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/*",
                $root, "/^\s*(?:(?:Mr|Mrs|Ms|Mstr|Dr)\.\s*)?(.+?)\s*(?:\(.+\))?\s*$/i"), true)
            ->status($this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::td[1]/following-sibling::td[1]", $root, null,
                "/^\s*{$this->opt($this->t('Status'))}\s*(.+)/"))
        ;

        // Place
        $ev->place()
            ->name($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1][count(.//text()[normalize-space()]) = 2][descendant::text()[normalize-space()][1][contains(., ' | ')]]/descendant::text()[normalize-space()][2]", $root))
            ->address($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Location'))}]/ancestor::*[not({$this->eq($this->t('Location'))})][{$this->starts($this->t('Location'))}][1]",
                $root, null, "/^\s*{$this->opt($this->t('Location'))}\s*(.+)/"));

        // Booked
        $date = strtotime($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Date'))}]/ancestor::*[not({$this->eq($this->t('Date'))})][{$this->starts($this->t('Date'))}][1]",
            $root, null, "/^\s*{$this->opt($this->t('Date'))}\s*(.+)/"));

        if (!empty($date)) {
            if (preg_match("/Departure (?<startDate>\d{1,2}:\d{2}[ap]m),\s*Arrival (?<endDate>\d{1,2}:\d{2}[ap]m)/", $comments, $m)) {
                if (!empty($m['startDate'])) {
                    $ev->booked()
                        ->start(strtotime($m['startDate'], $date));
                } else {
                    $ev->booked()
                        ->noStart();
                }

                if (!empty($m['endDate'])) {
                    $ev->booked()
                        ->end(strtotime($m['endDate'], $date));
                } else {
                    $ev->booked()
                        ->noEnd();
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
