<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class MyTrip extends \TAccountChecker
{
    public $mailFiles = "chase/it-563324776.eml, chase/it-563345470.eml, chase/it-584435693.eml, chase/it-587789510.eml, chase/it-602382161.eml, chase/it-603254103.eml, chase/it-603609470.eml, chase/it-604176317.eml, chase/it-604767081.eml, chase/it-605067349.eml, chase/it-612693581.eml, chase/it-699171208.eml, chase/it-699332618.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Airline confirmation: '    => 'Airline confirmation:',
            'Hotel confirmation:'       => 'Hotel confirmation:',
            'Car confirmation:'         => 'Car confirmation:',
            'Activity confirmation:'    => 'Activity confirmation:',
            'Flight'                    => ['Flight', 'Depart', 'Return'],
            'Hybrid'                    => ['Hybrid', 'Electric'],
        ],
    ];

    private $detectFrom = "donotreply@chasetravel.com";
    private $detectSubject = [
        // en
        'Travel Reservation Center Trip ID #',
        'Get ready! Your trip is almost here',
    ];
    private $detectBody = [
        'en' => [
            'Please carefully review your itinerary below',
            'Your itinerary has been updated',
            'Please carefully review your updated itinerary below to verify all information is correct.',
            'has shared their trip details with you.',
            'Your trip is coming up in a few days',
        ],
    ];
    private $getReadyTrip = false; // в таких письмах меньше информации

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]chasetravel\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
        ) {
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
            $this->http->XPath->query("//a[{$this->contains(['.chase.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['choosing Chase Travel', 'call the Travel Rewards Center'])}]")->length === 0
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
        // $this->assignLang();
        // if (empty($this->lang)) {
        //     $this->logger->debug("can't determine a language");
        //     return $email;
        // }

        if ($this->http->XPath->query("//*[{$this->starts($this->t('Your trip is coming up in a few days'))}]")->length > 0) {
            $this->getReadyTrip = true;
        }

        $tripId = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip ID:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([\dA-Z]{5,})\s*$/");

        if (empty($tripId) && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip ID:'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('See trip'))}]"))
            && preg_match("/(?: Trip ID #|Get ready! Your trip is almost here)\s*$/", $parser->getSubject())
        ) {
        } else {
            $email->ota()
                ->confirmation($tripId);
        }

        $this->parseFlight($email);
        $this->parseHotel($email);
        $this->parseRental($email);
        $this->parseActivity($email);

        $cancelled = false;

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('Your trip has been canceled'))} or {$this->contains($this->t('Cancellation summary'))}]")->length > 0) {
            $cancelled = true;

            foreach ($email->getItineraries() as $it) {
                $it->general()
                    ->cancelled()
                    ->status('Cancelled');
            }
        }

        if ($cancelled !== true) {
            $total = $this->http->FindSingleNode("//td[{$this->eq($this->t('Trip total'))}]/following-sibling::td[normalize-space()][1]");

            if (preg_match("/(?:^|\+)\s*(.+?points?)\s*(?:\+|$)/iu", $total, $m)) {
                $email->price()
                    ->spentAwards($m[1]);
                $total = str_replace($m[0], '', $total);
            }

            $price = $this->getTotal($total);

            if (!empty($price['amount']) && !empty($price['currency'])) {
                $email->price()
                    ->total($price['amount'])
                    ->currency($price['currency']);
            }

            if (!$email->price()) {
                $email->price()
                    ->total(null);
            }
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Airline confirmation:"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Airline confirmation:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Hotel confirmation:"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Hotel confirmation:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Car confirmation:"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Car confirmation:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Activity confirmation:"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Activity confirmation:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Main Cabin Fare:'))}][1]/ancestor::td[not({$this->starts($this->t('Main Cabin Fare:'))})][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->getReadyTrip === true) {
            $xpath = "//img[contains(@src, 'arrow-right.png')]/ancestor::table[normalize-space()][1][count(.//text()[normalize-space()]) > 2]/following::text()[normalize-space()][2]/ancestor::*[contains(@style, 'font-weight:600')]/ancestor::td[not(.//img)][last()]";
            // $this->logger->debug('$xpath = '.print_r( $xpath,true));
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0 && empty($this->http->FindSingleNode("(//*[{$this->starts($this->t('Airline confirmation:'))}])[1]"))) {
            return true;
        }

        $f = $email->add()->flight();

        // General
        $confs = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Airline confirmation:'))}]/following::text()[normalize-space()][1]", null, "/^\s*([A-Z\d]{5,7})\s*$/")));

        if (empty($confs)) {
            $confs = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Airline confirmation:'))}]",
                null, "/:\s*([A-Z\d]{5,7})\s*$/")));
        }

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        if (empty($confs)
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Airline confirmation:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Airline confirmation:'))}]/following::text()[normalize-space()][1][contains(., '(') and contains(., ')')]")->length
                === $this->http->XPath->query("//text()[{$this->eq($this->t('Airline confirmation:'))}]")->length
        ) {
            $f->general()
                ->noConfirmation();
        }

        $f->general()
            ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Traveler'))}]/ancestor::tr[1]", null, "/^\s*{$this->opt($this->t('Traveler'))} \d+:\s*(.+)/"))), true);

        foreach ($nodes as $root) {
            unset($s1, $s2);
            $sText = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()]", $root));

            $tableXpath = "preceding::text()[normalize-space()][position() < 10]/ancestor::tr[position() = 1 or position() = 2][count(*[normalize-space()]) = 2][*[1][.//img]][last()]";

            $info = $this->http->FindSingleNode($tableXpath . "/following-sibling::*[normalize-space()]", $root);
            $transitCodes = $this->res("/\(\s*([A-Z]{3})\s*\W[^()]+\)/", $info);

            $flRe = "([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(\d{1,5})";
            $addAirline = '';

            if (preg_match("/^\s*{$this->opt($this->t('Multiple Airlines'))}/", $sText) || !empty($this->http->FindSingleNode("./preceding::text()[normalize-space()][1][{$this->contains($this->t('Multiple Airlines'))}]", $root))) {
                $addAirline = '\n.+';
            }

            if (preg_match("/^(?:.*\n)?\s*((?:{$flRe}\n(.+){$addAirline}\n)+){$this->opt($this->t('Main Cabin Fare:'))}/", $sText, $match)
                || ($this->getReadyTrip && preg_match("/^(?:.*\n)?\s*((?:{$flRe}\n(.+){$addAirline}\n)+)$/", $sText . "\n", $match))
            ) {
                $mPart = $this->split("/\n((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\n.+{$addAirline})/", "\n" . $match[1]);

                foreach ($mPart as $i => $mp) {
                    if ($i == 0) {
                        if (preg_match("/^\s*{$flRe}\n(.+)/", $mp, $m)) {
                            $s1 = $f->addSegment();
                            $s1->airline()
                                ->name($m[1])
                                ->number($m[2]);
                            $s1->extra()
                                ->aircraft($m[3]);

                            $this->setStatusForFlight($m[1], $m[2], $s1);
                        }
                    } elseif ($i == count($mPart) - 1) {
                        if (preg_match("/^\s*{$flRe}\n(.+)/", $mp, $m)) {
                            $s2 = $f->addSegment();
                            $s2->airline()
                                ->name($m[1])
                                ->number($m[2]);
                            $s2->extra()
                                ->aircraft($m[3]);
                            $this->setStatusForFlight($m[1], $m[2], $s2);
                        }
                    } elseif (isset($transitCodes[$i - 1]) && isset($transitCodes[$i])) {
                        if (preg_match("/^\s*{$flRe}\n(.+)/", $mp, $m)) {
                            $s = $f->addSegment();
                            $s->airline()
                                ->name($m[1])
                                ->number($m[2]);
                            $s->departure()
                                ->code($transitCodes[$i - 1])
                                ->noDate()
                            ;
                            $s->arrival()
                                ->code($transitCodes[$i])
                                ->noDate()
                            ;
                            $s->extra()
                                ->aircraft($m[3]);
                            $this->setStatusForFlight($m[1], $m[2], $s);
                            unset($s);
                        }
                    } else {
                        $f->addSegment();
                    }
                }
            } else {
                $s1 = $f->addSegment();
            }

            if (!isset($s1)) {
                $f->addSegment();
                $s1 = $f->addSegment();
            }

            $date = $this->http->FindSingleNode($tableXpath . "/preceding::tr[not(.//tr)][normalize-space()][1][preceding::tr[not(.//tr)][normalize-space()][1][{$this->starts($this->t('Flight'))}]]", $root);

            if (empty($date)) {
                $date = $this->http->FindSingleNode($tableXpath . "/preceding::tr[not(.//tr)][normalize-space()][1][{$this->starts($this->t('Flight'))}]", $root, true, "/^(?:.+:)?\s*(.+)/");
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode($tableXpath . "/preceding::tr[not(.//tr)][normalize-space()][5][following::tr[not(.//tr)][normalize-space()][1][{$this->contains($this->t('traveler'))}]]", $root);
            }
            $date = preg_replace("/\(.+?\)\s*$/", '', $date);
            $date = $this->normalizeDate($date);

            if (!isset($s2)) {
                $s1->extra()
                    ->duration($this->re("/^\s*([^\|]+?)\s*(?:\|\s*|$)/", $info));
            }

            $depart = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time>\d{1,2}:\d{2}.*)\n(?<code>[A-Z]{3})(?:\n\s*.+)?\s*$/", $depart, $m)) {
                $s1->departure()
                    ->code($m['code'])
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null);

                if (isset($s2)) {
                    $s2->departure()
                        ->code($transitCodes[count($transitCodes) - 1])
                        ->noDate();
                }
            }
            $arrive = implode("\n", $this->http->FindNodes($tableXpath . "/*[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time>\d{1,2}:\d{2}.*)\n(?<code>[A-Z]{3})\s*(?:{$this->opt($this->t('Different airport'))}\s*)?(?:(?<overnight1>{$this->opt($this->t('Next day arrival'))})|(?<overnight2>{$this->opt($this->t('2nd day arrival'))})|\n\s*.+)?\s*$/", $arrive, $m)) {
                if (isset($s2)) {
                    $s2->arrival()
                        ->code($m['code'])
                        ->date(!empty($date) ? strtotime($m['time'], $date) : null);

                    if (!empty($m['overnight1']) && !empty($s2->getArrDate())) {
                        $s2->arrival()
                            ->date(strtotime('+1 day', $s2->getArrDate()));
                    } elseif (!empty($m['overnight2']) && !empty($s2->getArrDate())) {
                        $s2->arrival()
                            ->date(strtotime('+2 day', $s2->getArrDate()));
                    }

                    $s1->arrival()
                        ->code($transitCodes[0])
                        ->noDate();
                } else {
                    $s1->arrival()
                        ->code($m['code'])
                        ->date(!empty($date) ? strtotime($m['time'], $date) : null);

                    if (!empty($m['overnight1']) && !empty($s1->getArrDate())) {
                        $s1->arrival()
                            ->date(strtotime('+1 day', $s1->getArrDate()));
                    } elseif (!empty($m['overnight2']) && !empty($s1->getArrDate())) {
                        $s1->arrival()
                            ->date(strtotime('+2 day', $s1->getArrDate()));
                    }
                }
            }

            $this->logger->debug('$this->getReadyTrip = ' . print_r($this->getReadyTrip ? 'true' : 'false', true));

            if ($this->getReadyTrip !== true) {
                $cabin = $this->re("/{$this->opt($this->t('Main Cabin Fare:'))}\n(?:.+\n)?(.+?) *\([A-Z]{1,2}\)(?:\n|$)/",
                    $sText);
                $bookingCode = $this->re("/{$this->opt($this->t('Main Cabin Fare:'))}\n(?:.+\n)?.+? *\(([A-Z]{1,2})\)(?:\n|$)/",
                    $sText);
                $s1->extra()
                    ->cabin($cabin)
                    ->bookingCode($bookingCode);

                if (isset($s2)) {
                    $s2->extra()
                        ->cabin($cabin)
                        ->bookingCode($bookingCode);
                }
            }

            if (isset($s1) && !empty($s1->getDepCode()) && !empty($s1->getArrCode())) {
                $name = $s1->getDepCode() . ' - ' . $s1->getArrCode();
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Seat assignment'))}]/following::tr[1]//text()[{$this->starts($name)}]",
                    null, "/{$name}\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    $s1->extra()
                        ->seats($seats);
                }
            }

            if (isset($s2) && !empty($s2->getDepCode()) && !empty($s2->getArrCode())) {
                $name = $s2->getDepCode() . ' - ' . $s2->getArrCode();
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Seat assignment'))}]/following::tr[1]//text()[{$this->starts($name)}]",
                    null, "/{$name}\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    $s2->extra()
                        ->seats($seats);
                }
            }
        }

        return true;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Hotel confirmation:'))}][1]/ancestor::*[{$this->contains($this->t('Check-out:'))}][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && !empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('Hotel confirmation:'))}])[1]"))) {
            $email->add()->hotel();

            return true;
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $conf = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Hotel confirmation:'))}]/following::text()[normalize-space()][1]", $root, true,
                "/^\s*([A-Z\d\-]{5,})\s*$/");

            if (empty($confs)) {
                $conf = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Hotel confirmation:'))}]", $root, true,
                    "/:\s*([A-Z\d\-]{5,})\s*$/");
            }
            $h->general()
                ->confirmation($conf);

            if ($this->http->XPath->query("./preceding::tr[3][starts-with(normalize-space(), 'Hotel')][1]/descendant::td[2][contains(normalize-space(), 'Canceled')]", $root)->length > 0) {
                $h->general()
                    ->cancelled();
            }

            $traveller = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Primary guest:'))}]/ancestor::tr[1]", $root, true,
                "/{$this->opt($this->t('Primary guest:'))}\s*(\D+)\s*$/");

            if (empty($traveller) && $this->getReadyTrip) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip ID:'))}]/following::text()[{$this->starts($this->t('Hi '))}][1]",
                    null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*(\D+)\s*,\s*$/");
            }
            $h->general()
                ->traveller($traveller);

            $cancellation = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Non-refundable'))}]", $root);

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Free cancellation'))}]/ancestor::tr[1]", $root);
            }
            $h->general()
                ->cancellation($cancellation, true, true);

            $h->hotel()
                ->name($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Check-in:'))}]/ancestor::tr[1]/preceding::tr[not(.//tr)][2]", $root));
            $address = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Check-in:'))}]/ancestor::*[.//img][1]/following::tr[not(.//tr)][1][.//a]", $root);

            if (empty($address)) {
                $address = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Check-out:'))}]/ancestor::tr[not(.//tr)][1]/following::tr[not(.//tr)][normalize-space()][1][.//a]", $root);
            }
            $h->hotel()
                ->address($address);

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Check-in:'))}]/ancestor::tr[1]", $root, true,
                    "/{$this->opt($this->t('Check-in:'))}\s*(.+)\s*$/")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Check-out:'))}]/ancestor::tr[1]", $root, true,
                    "/{$this->opt($this->t('Check-out:'))}\s*(.+)\s*$/")));

            $guestsText = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Hotel confirmation:'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root);
            $h->booked()
                ->guests($this->re("/^\s*(\d+) ?guest/", $guestsText), $this->getReadyTrip, $this->getReadyTrip)
                ->kids($this->re("/(?:^|,)\s*(\d+) ?child/", $guestsText), true, true)
            ;

            $h->addRoom()
                ->setType($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Check-in:'))}]/ancestor::tr[1]/preceding::tr[not(.//tr)][1]", $root));

            $name = $h->getHotelName();

            if (!empty($name)) {
                $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment summary'))}]/following::td[{$this->eq($name)}]/following-sibling::td[normalize-space()][1]");

                if (preg_match("/(?:^|\+)\s*(.+?points?)\s*(?:\+|$)/iu", $total, $m)) {
                    $h->price()
                        ->spentAwards($m[1]);
                    $total = str_replace($m[0], '', $total);
                }

                $price = $this->getTotal($total);

                if (!empty($price['amount']) && !empty($price['currency'])) {
                    $h->price()
                        ->total($price['amount'])
                        ->currency($price['currency']);
                }
            }
        }

        return true;
    }

    private function parseRental(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Drop-off:'))}][1]/ancestor::*[{$this->contains($this->t('Car confirmation:'))}][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && !empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('Car confirmation:'))}])[1]"))) {
            $email->add()->rental();

            return true;
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Car confirmation')]/ancestor::tr[1]/preceding::tr[3][starts-with(normalize-space(), 'Car')][1]/descendant::td[2][contains(normalize-space(), 'Canceled')]")->length > 0) {
                $r->general()
                    ->cancelled();
            }

            // General
            $conf = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Car confirmation:'))}]/following::text()[normalize-space()][1]", $root, true,
                "/^\s*([A-Z\d\-]{5,})\s*$/");

            if (empty($confs)) {
                $conf = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Car confirmation:'))}]", $root, true,
                    "/:\s*([A-Z\d\-]{5,})\s*$/");
            }
            $r->general()
                ->confirmation($conf);

            $traveller = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Driver:'))}]/ancestor::tr[1]", $root, true,
                "/{$this->opt($this->t('Driver:'))}\s*(\D+)\s*$/");
            $r->general()
                ->traveller($traveller);

            $location = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Pick-up / drop-off location:'))}]/following::tr[not(.//tr)][1]", $root);

            if (!empty($location)) {
                $phone = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Pick-up / drop-off location:'))}]/following::tr[not(.//tr)][2]", $root, null,
                    "/^[\d\W]+$/");

                if (strlen(preg_replace("/\D+/", '', $phone)) > 5) {
                    $r->pickup()
                        ->phone($phone);
                }
                $r->pickup()
                    ->location($location);
                $r->dropoff()
                    ->same();
            } else {
                $location = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Pick-up location:'))}]/following::tr[not(.//tr)][1]", $root);
                $phone = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Pick-up location:'))}]/following::tr[not(.//tr)][2]", $root, null,
                        "/^[\d\W]+$/");
                $r->pickup()
                    ->location($location);

                if (strlen(preg_replace("/\D+/", '', $phone)) > 5) {
                    $r->pickup()
                        ->phone($phone);
                }

                $location = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Drop-off location:'))}]/following::tr[not(.//tr)][1]", $root);
                $phone = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Drop-off location:'))}]/following::tr[not(.//tr)][2]", $root, null,
                        "/^[\d\W]+$/");
                $location = preg_replace("/\s*" . preg_quote("\"/>", '/') . "\s*$/", '', $location);
                $r->dropoff()
                    ->location($location);

                if (strlen(preg_replace("/\D+/", '', $phone)) > 5) {
                    $r->dropoff()
                        ->phone($phone);
                }
            }
            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::tr[1]", $root, true,
                    "/{$this->opt($this->t('Pick-up:'))}\s*(.+)\s*$/")));
            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Drop-off:'))}]/ancestor::tr[1]", $root, true,
                    "/{$this->opt($this->t('Drop-off:'))}\s*(.+)\s*$/")));

            $r->extra()
                ->company($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::tr[1]/preceding::tr[not(.//tr)][not({$this->eq($this->t('Hybrid'))})][2]", $root, true,
                    "/^(.+) - /"));
            $r->car()
                ->type($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::tr[1]/preceding::tr[not(.//tr)][not({$this->eq($this->t('Hybrid'))})][2]", $root, true,
                    "/^.+ - (.+)/"))
                ->model($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::tr[1]/preceding::tr[not(.//tr)][not({$this->eq($this->t('Hybrid'))})][1]", $root));

            $name = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::tr[1]/preceding::tr[not(.//tr)][2]", $root);

            if (!empty($name)) {
                $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment summary'))}]/following::td[{$this->eq($name)}]/following-sibling::td[normalize-space()][1]");

                if (preg_match("/(?:^|\+)\s*(.+?points?)\s*(?:\+|$)/iu", $total, $m)) {
                    $r->price()
                        ->spentAwards($m[1]);
                    $total = str_replace($m[0], '', $total);
                }

                $price = $this->getTotal($total);

                if (!empty($price['amount']) && !empty($price['currency'])) {
                    $r->price()
                        ->total($price['amount'])
                        ->currency($price['currency']);
                }
            }
        }

        return true;
    }

    private function parseActivity(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('View and print voucher'))}][1]/ancestor::*[{$this->contains($this->t('Activity confirmation:'))}][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && !empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('Activity confirmation:'))}])[1]"))) {
            $email->add()->rental();

            return true;
        }

        foreach ($nodes as $root) {
            $event = $email->add()->event();

            $event->type()->event();

            // General
            $conf = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Activity confirmation:'))}]/following::text()[normalize-space()][1]", $root, true,
                "/^\s*([A-Z\d\-]{5,})\s*$/");

            if (empty($confs)) {
                $conf = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Activity confirmation:'))}]", $root, true,
                    "/:\s*([A-Z\d\-]{5,})\s*$/");
            }
            $event->general()
                ->confirmation($conf);

            $event->general()
                ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Traveler'))}]/ancestor::tr[1]", null, "/^\s*{$this->opt($this->t('Traveler'))} \d+:\s*(.+)/"))), true);

            $event->place()
                ->name($this->http->FindSingleNode(".//tr[not(.//tr)][{$this->starts($this->t('Time:'))}]/preceding::tr[not(.//tr)][normalize-space()][1]", $root));

            $date = $this->http->FindSingleNode(".//tr[not(.//tr)][{$this->starts($this->t('Activity confirmation:'))}]/preceding::tr[not(.//tr)][normalize-space()][2]", $root);

            $time = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Time:'))}]/ancestor::tr[1]", $root, true,
                "/{$this->opt($this->t('Time:'))}\s*(.+)\s*$/");
            $duration = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Duration:'))}]/ancestor::tr[1]", $root, true,
                "/{$this->opt($this->t('Duration:'))}\s*(.+)\s*$/");

            $event->booked()
                ->start((!empty($date) && !empty($time)) ? $this->normalizeDate($date . ' ' . $time) : null);
            // 6.30 to 8 hrs -> 8 hrs
            $duration = preg_replace("/.+ to (.*\d.*)/", '$1', $duration);
            // 8 hrs -> 8 hours
            $duration = preg_replace("/\bhrs\b/", 'hours', $duration);

            if (!empty($duration) && !empty($event->getStartDate())) {
                $event->booked()
                    ->end(strtotime('+' . $duration, $event->getStartDate()));
            } elseif ($event->getStartDate()) {
                $event->booked()->noEnd();
            }

            $guestsText = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Activity confirmation:'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root);
            $adult = $this->re("/^\s*(\d+) ?adult/i", $guestsText);
            $adult += ($this->re("/(?:^|,)\s*(\d+) ?senior/i", $guestsText) ?? 0);
            $event->booked()
                ->guests($adult)
            ;
            $kids = $this->re("/(?:^|,)\s*(\d+) ?child/i", $guestsText);

            if (!empty($kids)) {
                $event->booked()
                    ->kids($kids)
                ;
            }

            $name = $event->getName();

            $url = $this->http->FindSingleNode(".//a[{$this->eq($this->t('View and print voucher'))}]/@href", $root);
            // $this->logger->debug('$url = ' . print_r($url, true));

            if (stripos($url, 'viator') !== false) {
                // the same as viator/GetTicket
                $http2 = clone $this->http;
                $this->http->brotherBrowser($http2);

                // $http2->SetProxy($this->proxyDOP());
                $http2->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
                $http2->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36');

                if (stripos($url, 'www.viator.com') !== false) {
                    $http2->setDefaultHeader('Origin', 'https://www.viator.com');
                } elseif (stripos($url, 'api.viator.com') !== false) {
                    $http2->setDefaultHeader('Origin', 'https://api.viator.com');
                }

                $http2->GetURL($url);

                if (isset($http2->Response['headers']['location'])) {
                    $url2 = $http2->Response['headers']['location'];

                    $http2->setMaxRedirects(5);
                    $http2->GetURL($url2);
                }

                if (stripos($http2->currentUrl(), 'viatorapi') !== false) {
                    $http2->GetURL(str_replace('viatorapi', 'www', $http2->currentUrl()));
                }

                if ($http2->XPath->query("//*[{$this->contains($name)}]")->length > 0) {
                    $address = $http2->FindSingleNode("//text()[{$this->eq($this->t('Meeting and pickup'))}]/following::text()[position() < 5]/ancestor::a[contains(@href, 'maps.google.com')]/@href",
                        null, true, "/\?q=(.+)/");

                    if (empty($address)) {
                        $address = $http2->FindSingleNode("//text()[{$this->eq($this->t('Redemption Point'))}]/following::text()[normalize-space()][1]/ancestor::a[contains(@href, 'maps.google.com')]/@href",
                            null, true, "/\?q=(.+)/");
                    }
                    $event->place()
                        ->address($address);
                }
            }

            if (!empty($name)) {
                $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment summary'))}]/following::td[{$this->eq($name)}]/following-sibling::td[normalize-space()][1]");

                if (preg_match("/(?:^|\+)\s*(.+?points?)\s*(?:\+|$)/iu", $total, $m)) {
                    $event->price()
                        ->spentAwards($m[1]);
                    $total = str_replace($m[0], '', $total);
                }

                $price = $this->getTotal($total);

                if (!empty($price['amount']) && !empty($price['currency'])) {
                    $event->price()
                        ->total($price['amount'])
                        ->currency($price['currency']);
                }
            }
        }

        return true;
    }

    private function setStatusForFlight(string $airName, string $flNumber, FlightSegment $s)
    {
        if (!empty($airName) && !empty($flNumber)) {
            if ($this->http->XPath->query("//text()[{$this->eq($airName . ' ' . $flNumber)}]/preceding::text()[starts-with(normalize-space(), 'Airline confirmation:')][1]/preceding::tr[{$this->starts($this->t('Flight'))}][1]/descendant::td[2][contains(normalize-space(), 'Canceled')]")->length > 0) {
                $s->setCancelled(true);
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d,.\s][^\d]{0,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d,.\s][^\d]{0,5})\s*$#u", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#u", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if (preg_match("#^\s*(?:\D{1,3}\s)?\b(?<c>[A-Z]{3})\b(?:\s\D{1,3})?\s*$#u", $s, $m)) {
            return $m['c'];
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
