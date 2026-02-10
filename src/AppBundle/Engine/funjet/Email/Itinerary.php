<?php

namespace AwardWallet\Engine\funjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "funjet/it-10104874.eml, funjet/it-10184150.eml, funjet/it-10184175.eml, funjet/it-10184724.eml, funjet/it-10185686.eml, funjet/it-10190140.eml, funjet/it-10191379.eml, funjet/it-12356661.eml, funjet/it-12610924.eml, funjet/it-1582220.eml, funjet/it-1716381.eml, funjet/it-2409256.eml, funjet/it-37811264.eml, funjet/it-538656695.eml, funjet/it-9.eml"; // +1 bcdtravel(html)[en]
    public static $dict = [
        'en' => [
            'Reservation Number:' => ['Reservation Number:', 'Your reservation number:', 'Your confirmation number is'],
            'Bulk Fare'           => ['Published Fare United', 'Bulk Fare United', 'Bulk Fare', 'Published Fare'],
            'DEPARTURE:'          => ['Depart', 'Return', 'DEPARTURE:', 'RETURN:', 'Departure:', 'Return:'],
            'Leaves'              => ['Leaves', 'Departs'],
            'Seat(s)'             => ['Seat(s)', 'SEATS'],
            'Hotel -'             => ['Hotel -', 'Resort -'],
        ],
    ];

    private $reFrom = [
        'funjet'       => ['@ucruisevacation.com', '@kommemorativevacations.com', '@u.vacations', '@hiattmagicalvacations.com'],
        'mileageplus'  => ['@unitedvacations.com'],
        'rapidrewards' => [
            '@southwestvacations.com', '@travelbta.com', '@travelfwa.com',
        ],
        'alaskaair'      => ['@alaskaair.com'],
        'appleva'        => ['@applevacations.com'],
        'tedge'          => ['travelbrandsagent.com'],
        'virtuoso'       => ['@largaytravel.com'],
        'travimp'        => ['@travimp.com'],
        'goldpassport'   => ['@hyattic.com'],
    ];

    private $reSubject = [
        'Reservation Confirmation', // funjet, travimp, appleva
        'Universal Parks & Resorts Vacations Travel Confirmation', // funjet
        'Itinerary Notification', // mileageplus, travimp
    ];

    private $providerCode = '';
    private $providerDetect = [
        'ccaribbean'       => ['@cheapcaribbean.com', 'Thank you for booking your vacation with CheapCaribbean'],
        'mileageplus'      => ['unitedvacations.com', 'United'],
        'rapidrewards'     => ['southwestvacations.com', 'southwest.com'],
        'alaskaair'        => ['Alaska Airlines Vacations', '.asvacations.com'],
        'appleva'          => ['Thank you for choosing Apple Vacations', 'the time to price an Apple Vacations getaway', '.applevacations.com', 'with Apple Vacations'],
        'tedge'            => ['TRAVEL EDGE'],
        'travimp'          => ['.travimp.com', 'the time to price a Travel Impressions'],
        'hawaiian'         => ['Your Hawaiian Getaway'],
        'goldpassport'     => ['info@hyattic.com'],
        'virtuoso'         => [
            'www.virtuoso.com',
            'largaytravel.com',
            'LargayTravel',
            'LARGAY TRAVEL',
        ],
        'funjet'     => [
            'Funjet Vacations',
            'Sold and Serviced by Universal Vacations',
            'ucruisevacation.com',
            'kommemorativevacations.com',
            'KOMMEMORATIVE VACATIONS',
            'Kommemorative Vacations',
            'triptopiatravel.com',
            'u.vacations',
            'ULTIMATE CRUISE AND VACATION',
            'PALM COAST TRAVEL', // not funjet, default prov
            'TRAVEL LEADERS TRAVEL QUEST', // not funjet, default prov
            'SANBORNS INTL TVL SVC', // not funjet, default prov
            'HIATT MAGICAL VACATIONS', // not funjet, default prov
            'amresorts.com', // not funjet, default prov
            'Total Amount of Payments', // not funjet, default prov
            'trip with EXPEDIA CRUISES', // not funjet, default prov
            'Travel Protection Plus: Full Travel Credit', // not funjet, default prov
        ],
    ];

    private $reBody = [
        'en'  => ['Reservation Number', 'Passengers'],
        'en2' => ['Leaves', ' Flights - departing'],
        'en3' => ['Departs', ' Flights - departing'],
        'en4' => ['Hotel - check in', 'Thank you for taking the time to price'],
        'en5' => ['Hotel - check in', 'Thank you for booking your'],
        'en6' => ['Hotel - check in', 'Applicable promotions will display with your room selection'],
    ];
    private $lang = '';
    private $mainRoot;

    public static function getEmailProviders()
    {
        return ['ccaribbean', 'funjet', 'mileageplus', 'rapidrewards', 'alaskaair', 'tedge', 'hawaiian', 'travimp', 'appleva', 'virtuoso', 'goldpassport'];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //get main email text
        $main = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'Replies to this email will be sent to')]/ancestor::*[self::div or self::body][1]");

        if ($main->length == 1 && $this->http->XPath->query(".//text()[normalize-space()]", $main->item(0))->length < 5) {
            $main = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'Replies to this email will be sent to')]/ancestor::*[self::div or self::body][2]");
            $this->mainRoot = $main->item(0);
        } elseif ($main->length == 1) {
            $this->mainRoot = $main->item(0);
        } else {
            $this->mainRoot = null;
        }

        $this->assignProvider();
        $this->assignLang();

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $tripNumber = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Reservation Number:'))}][1]/following::text()[normalize-space()][1]", $this->mainRoot, true, "/^([A-Z\d]{5,})[\s.]*$/");
        $email->obtainTravelAgency();

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber);
        }
        $earnPoints = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price'))}]/following::p[{$this->contains($this->t('points per member for air'))}]", null, true, "/^{$this->opt($this->t('Earn'))}\s*(\d[,.\d\s]*)/");

        if ($earnPoints === null) {
            $earnPoints = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price'))}]/following::span[{$this->contains($this->t('Points per Member'))}]", null, true, "/^{$this->opt($this->t('Earn'))}\s*(\d[,.\d\s]*)/");
        }

        if ($earnPoints !== null) {
            $email->ota()->earnedAwards(trim($earnPoints) . ' points');
        }

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseCars($email);

        $travellers = array_filter(preg_split('/\s*,\s*/', $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Passengers'))}]", $this->mainRoot, true, "#{$this->opt($this->t('Passengers'))}[\s:]+(.+)#")));

        if (empty($travellers)) {
            $nodes = $this->http->XPath->query("//*[contains(text(), 'Traveler #')]/ancestor::table[1]");
            $names = [];

            foreach ($nodes as $item) {
                $info = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()!='']", $item));
                $name = [];
                $name[] = $this->re("/\n\s*First Name[\s:]+([^\n]+)/i", $info);
                $name[] = $this->re("/\n\s*Middle Initial\/Name[\s:]+((?!Last Name)[^\n]+)/i", $info);
                $name[] = $this->re("/\n\s*Last Name[\s:]+([^\n]+)/i", $info);
                $names[] = implode(' ', array_filter($name));
            }
            $travellers = $names;
        }

        if (!empty($travellers)) {
            foreach ($email->getItineraries() as $key => $value) {
                $email->getItineraries()[$key]->general()->travellers($travellers);
            }
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Total Price'))}]/ancestor::td[1]/following-sibling::td[1]", $this->mainRoot));

        if ($tot['Total'] !== null) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency'])
            ;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->assignProvider() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromFlag = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $code => $reFroms) {
                foreach ($reFroms as $reFrom) {
                    if (stripos($headers['from'], $reFrom) !== false) {
                        $fromFlag = true;
                        $this->providerCode = $code;

                        break 2;
                    }
                }
            }
        }

        if ($fromFlag && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFroms) {
            foreach ($reFroms as $reFrom) {
                if (stripos($from, $reFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 4; // flights - 2; car - 1; hotel - 1;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang))) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseFlights(Email $email): void
    {
        $xpath = "//text()[{$this->starts($this->t('Leaves'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath, $this->mainRoot);

        if ($nodes->length === 0) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");

            return;
        }

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        if ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('DEPARTURE:'))}]", $nodes->item(0))->length === 0) {
            $this->parseFlightSegment_1($f, $nodes);
            $this->logger->debug('Detected flight segments type-1');
        } else {
            $this->parseFlightSegment_2($f, $nodes);
            $this->logger->debug('Detected flight segments type-2');
        }
    }

    private function parseFlightSegment_1(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = str_replace(['†'], '', $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[1]", $root));

            if (preg_match("#(?<al>.+?)\s*\#\s*(?<fn>\d+)[\s\–]*(?:[^\s\w]*[ ]*{$this->opt($this->t('Operated by'))}?\s*:\s*(?:(?<alOper>[A-Z\d]{2})\s*(?<fnOper>\d{1,5}) )?(?<oper>.+?))?(?:{$this->opt($this->t('Seat(s)'))}[\s:]+(?<seats>.+)|$)#",
                $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $rl = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[{$this->starts($this->t('Airline Confirmation Number'))}]",
                    $root, true, "#{$this->opt($this->t('Airline Confirmation Number'))}[\s:]+([A-Z\d]{5,})#");

                if (!empty($rl)) {
                    $s->airline()->confirmation($rl);
                }

                if (!empty($m['seats']) && preg_match_all("#\b(\d+[A-Z])\b#i", $m['seats'], $match)) {
                    //30A-30C
                    if (preg_match("/^(?<firstNumber>\d+)(?<firstLetter>[A-Z])\s*\-\s*(?<lastNumber>\d+)(?<lastLetter>[A-Z])/", $m['seats'], $v)) {
                        $arrayLetter = range($v['firstLetter'], $v['lastLetter']);

                        if ($v['firstNumber'] === $v['lastNumber']) {
                            foreach ($arrayLetter as $letter) {
                                $s->extra()
                                    ->seat($v['firstNumber'] . $letter);
                            }
                        }
                    } else {
                        $s->extra()->seats($match[1]);
                    }
                }

                if (!empty($m['alOper']) && !empty($m['fnOper'])) {
                    $s->airline()
                        ->carrierName($m['alOper'])
                        ->carrierNumber($m['fnOper']);
                }

                if (!empty($m['oper'])) {
                    $s->airline()->operator(preg_replace("/COMMERCIAL\s*DUPLICATE\s*\-\s*/iu", "", $m['oper']));
                }
            } elseif (preg_match("#^(?<al>.+?)\s*\#\s*(?<fn>\d+)\s*Operated by\s*\:$#",
                $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            // Departure
            $s->departure()->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]",
                    $root) . ' ' . $this->http->FindSingleNode("./td[3]", $root))));
            $node = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $s->departure()
                    ->code($m[2])
                    ->name($m[1]);
            }

            // Arrival
            $s->arrival()->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]",
                    $root) . ' ' . $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root))));
            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root);

            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $s->arrival()
                    ->code($m[2])
                    ->name($m[1]);
            }

            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[2]", $root);

            if (preg_match("#{$this->opt($this->t('Bulk Fare'))}\s+(.*?)\s*([A-Z]{1,2})?\s*($|{$this->opt($this->t('Bulk Fare'))}\s*$)#",
                $node, $m) || preg_match("/^(\w+)\s*([A-Z])/", $node, $m)) {
                // may be: Bulk Fare United Economy W Bulk Fare
                if (!empty($m[1])) {
                    $s->extra()->cabin($m[1]);
                }

                if (!empty($m[2])) {
                    $s->extra()->bookingCode($m[2]);
                }
            }

            $duration = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Travel time:')]", $root, true, "/{$this->opt($this->t('Travel time:'))}\s*(.+)/");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }
        }
    }

    private function parseFlightSegment_2(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // Departure+Arrival
            $node = implode("\n", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space()!='']", $root));
            $regExp = "#{$this->opt($this->t('DEPARTURE:'))}\s+(?<dDate>.+)\s+(?<dTime>.+)\s+" .
                "{$this->opt($this->t('Leaves'))}\s+(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\)\s+" .
                "{$this->opt($this->t('Arrives in'))}\s+(?<aName>.+?)\s*\((?<aCode>[A-Z]{3})\)\s+" .
                "(?<aTime>.+)" .
                "#";

            if (preg_match($regExp, $node, $m)) {
                $date = strtotime($this->normalizeDate($m['dDate']));
                $s->departure()
                    ->date(strtotime($m['dTime'], $date))
                    ->name($m['dName'])
                    ->code($m['dCode']);
                $s->arrival()
                    ->date(strtotime($m['aTime'], $date))
                    ->name($m['aName'])
                    ->code($m['aCode']);
            }
            // airline
            $airline = $this->http->FindSingleNode("descendant::img/@alt", $root, true, "/^[^:><.,?!]+$/")
                ?? $this->http->FindSingleNode("descendant::img/@src", $root, true, "/carriers\/([A-Z\d][A-Z]|[A-Z][A-Z\d])logo\./");

            if (!empty($airline)) {
                $s->airline()
                    ->name($airline);
            }
            // flight, seats, cabin
            $flightHtml = $this->http->FindHTMLByXpath('*[normalize-space() or descendant::img][3]', null, $root);
            $flightInfo = $this->htmlToText($flightHtml);
            $regExp = "#^"
                . "[ ]*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])?\s*\#(?<fl>\d+)"
                . "\s+(?<class>.*?)\s*(?:{$this->opt($this->t('Bulk Fare'))})?\s*{$this->opt($this->t('Seat(s):'))}\s*(?<seats>.+)"
                . "\s+{$this->opt($this->t('Bulk Fare'))}\s*(?<cabin>.*)"
                . "#";

            if (preg_match($regExp, $flightInfo, $m)) {
                if (!empty($m['al'])) {
                    $s->airline()->name($m['al']);
                }
                $s->airline()->number($m['fl']);

                if (preg_match_all("#\b(\d+[A-z])\b#", $m['seats'], $v)) {
                    $s->extra()->seats($v[1]);
                }

                if (isset($m['class']) && preg_match("#^(.*?)\s*([A-Z]{1,2})?$#", $m['class'], $v)) {
                    if (!empty($v[1])) {
                        $s->extra()->cabin($v[1]);
                    }

                    if (isset($v[2]) && !empty($v[2])) {
                        $s->extra()->bookingCode($v[2]);
                    }
                }

                if (preg_match("#^((?!Non).*?)\s*([A-Z]{1,2})?$#", trim($m['cabin']), $v)) {
                    if (!empty($v[1])) {
                        $s->extra()->cabin($v[1]);
                    }

                    if (isset($v[2]) && !empty($v[2])) {
                        $s->extra()->bookingCode($v[2]);
                    }
                }
            } elseif (preg_match("#^[ ]*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])?\s*\#(?<fl>\d+)\b#", $flightInfo, $m)) {
                // AS #618    |    #3434
                if (!empty($m['al'])) {
                    $s->airline()->name($m['al']);
                }
                $s->airline()->number($m['fl']);
            }

            if (preg_match("#\bNon[-\s]*Stop\b#i", $flightInfo)) {
                $s->extra()->stops(0);
            } elseif (preg_match('/\b(\d{1,3}) Stops?\b/i', $flightInfo, $m)) {
                $s->extra()->stops($m[1]);
            }
            $confNo = $this->http->FindSingleNode("*[normalize-space() or descendant::img][4]", $root, true, "#{$this->opt($this->t('Airline Confirmation Number:'))}\s*([-A-Z\d]{5,})$#");

            if ($confNo) {
                // it-9.eml
                $s->airline()->confirmation($confNo);
            } elseif (preg_match("#{$this->opt($this->t('Airline Confirmation Number:'))}\s*([-A-Z\d]{5,})[ ]*$#m", $flightInfo, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            if (!$s->getAirlineName()) {
                $s->airline()->noName();
            }
        }
    }

    private function parseHotels(Email $email): void
    {
        $xpath = ".//text()[{$this->starts($this->t('Hotel -'))}]/ancestor::tr[1]";
        $hotels = $this->http->XPath->query($xpath, $this->mainRoot);

        foreach ($hotels as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation()
                ->cancellation($this->http->FindSingleNode("./following-sibling::tr/descendant::text()[{$this->contains($this->t('Hotel Cancel Policy:'))}]", $root, true, "#{$this->opt($this->t('Hotel Cancel Policy:'))}[\s:]*(.+)#"), true, true)
            ;

            if ($nonRef = $this->http->FindSingleNode("./following-sibling::tr/descendant::text()[contains(., 'Non-refundable Rate')]", $root)) {
                $h->booked()
                    ->nonRefundable();
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#(.+?)\s*(?:$|{$this->opt($this->t('- All Inclusive'))})#"));

            $address = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][2][{$this->contains($this->t('Location'))}]", $root, true, "#{$this->opt($this->t('Location'))}[\s:]+(.+)#");
            $dopAddr = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Destination'))}][1]", $root, true, "# \d+ - (.+)#");

            if (!empty($dopAddr)) {
                $address = !empty($address) ? $address . ', ' . $dopAddr : $dopAddr;
            }

            if (empty($address) && !empty($h->getHotelName())) {
                $h->hotel()->noAddress();
            } else {
                $h->hotel()->address($address);
            }

            // Booked
            $quests = null;
            $kids = null;
            $rCnt = null;

            $roomsTexts = $this->http->FindNodes("./following-sibling::tr/*[normalize-space()!=''][1][ ./descendant::text()[{$this->starts($this->t('Room'))}] and ({$this->contains($this->t('Adult'))} or {$this->contains($this->t('Child'))}) ]/descendant::text()[normalize-space(.)]", $root);
            $roomsText = implode("\n", $roomsTexts);
            $rooms = preg_split("/^{$this->opt($this->t('Room'))}[ ]*\d{1,3}\b/m", $roomsText);
            unset($rooms[0]);

            foreach ($rooms as $room) {
                $rCnt = $rCnt !== null ? $rCnt + 1 : 1;
                $str = $this->re("#\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}#i", $room);

                if ($str !== null) {
                    $quests = $quests !== null ? $quests + $str : $str;
                }
                $str = $this->re("#\b(\d{1,3})\s*{$this->opt($this->t('Child'))}#i", $room);

                if ($str !== null) {
                    $kids = $kids !== null ? $kids + $str : $str;
                }
                $roomType = $this->re("#{$this->opt($this->t('Room Type'))}[-\s]+(.+)#", $room);

                if ($roomType) {
                    $h->addRoom()->setType($roomType);
                }
            }

            $h->booked()
                ->guests($quests)
                ->kids($kids, true, true)
                ->rooms($rCnt)
            ;

            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#{$this->opt($this->t('check in'))}\s+(.+)\s+{$this->opt($this->t('and check out'))}\s+(.+)#", $node, $m)) {
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($m[1])))
                    ->checkOut(strtotime($this->normalizeDate($m[2])))
                ;
            }

            $this->detectDeadLine($h);
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (preg_match("#Cancel before (\d{1,2}/\d{1,2}/\d{4}) to avoid penalties.#i", $cancellationText, $m)
        || preg_match("#Cancellation Deadline: (\w+ \w+ \d{4})\.#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m[1]));

            return true;
        }

        if (preg_match("#^Cancelling between (\d+\w+?\d+) and \d+\w+?\d+ incurs a penalty of .+?\.$#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m[1])));

            return true;
        }

        if (preg_match("#^Cancellations or changes made after (?<time>[\d\:]+\s*A?P?M) \(\(GMT[+][\d\:]+\)\) on (?<date>.+? \d{4}) are subject to#i", $cancellationText, $m)
            || preg_match("#^Cancellations or changes made after (?<time>.+) \(.+?\) on (?<date>.+? \d{4}) are subject#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));

            return true;
        }

        if (preg_match("#Cancellation Deadline: (\d) Days prior to arrival. Late Cancellation Penalty: .+#i", $cancellationText, $m)
            || preg_match("#^(\d+) DAY CANCELLATION REQUIRED#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');

            return true;
        }

        return false;
    }

    private function parseCars(Email $email): void
    {
        $xpath = ".//text()[contains(normalize-space(.),'Rental Car -')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath, $this->mainRoot);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()->noConfirmation();

            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#{$this->opt($this->t('pick up'))}\s+(.+)\s+{$this->opt($this->t('and drop off'))}\s+(.+)#", $node, $m)) {
                $r->pickup()->date(strtotime($this->normalizeDate($m[1])));
                $r->dropoff()->date(strtotime($this->normalizeDate($m[2])));
            }

            $carImageUrl = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]/td[1]/descendant::img[1]/@src', $root);
            $r->car()->image($carImageUrl, false, true);

            // Pick Up
            $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[normalize-space()]//text()[{$this->starts($this->t('Pick up '))}]/ancestor::td[1]//text()", $root));

            if (preg_match("#Pick up\s+-\s+(.+?)\s+(\d+:\d+([ ]*[APM]{2})?)\s+#s", $node, $m)) {
                $r->pickup()->location($m[1]);

                if (!empty($r->getPickUpDateTime())) {
                    $r->pickup()->date(strtotime($m[2], $r->getPickUpDateTime()));
                }
            }

            // Drop Off
            if (preg_match("#Drop off\s+-\s+(.+?)\s+(\d+:\d+([ ]*[APM]{2})?)\s+#s", $node, $m)) {
                $r->dropoff()->location($m[1]);

                if (!empty($r->getDropOffDateTime())) {
                    $r->dropoff()->date(strtotime($m[2], $r->getDropOffDateTime()));
                }
            }

            // Price
            if (preg_match("#{$this->opt($this->t('Estimated Taxes/Fees Due At Counter'))}[:\s]+(.*\d.*)#", $node, $m)) {
                // $40.45 USD
                $tax = $this->getTotalCurrency($m[1]);

                if ($tax['Total'] !== null) {
                    $r->price()
                        ->tax($tax['Total'])
                        ->currency($tax['Currency']);
                }
            }

            $text1 = $this->http->FindSingleNode("following-sibling::tr[1]/td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^(\w+)\s+([^\-]+?)(?:\s+-|$)/", $text1, $m)) {
                // Hertz Economy Car - Taxes Excluded
                if (($code = $this->normalizeProvider($m[1]))) {
                    $r->program()->code($code);
                } else {
                    $r->extra()->company($m[1]);
                }
                $r->car()->type($m[2]);
            }
        }
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'rentacar'   => ['Enterprise'],
            'alamo'      => ['Alamo'],
            'hertz'      => ['Hertz'],
            'appleva'    => ['Apple Vacations'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\S+\s+(\w+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#i', // Wednesday, January 3, 2018 5:50 AM
            '#^\s*\S+\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#i', // Wednesday, January 3
            '#^\s*(\d{2})\s*(\w{3})\s*(\d{2})\s*$#i', // 04DEC17
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2 $1 $3',
            '$1 $2 20$3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignProvider(): bool
    {
        if (!empty($this->providerCode)) {
            return true;
        }

        foreach ($this->providerDetect as $code => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//*[contains(normalize-space(),'{$value}')]")->length > 0) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Please review the']/following-sibling::a[contains(normalize-space(),'Terms and Conditions')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src,'FJ1_logoCart') or contains(@src, '.vaxvacationaccess.com')]")->length > 0
        ) {
            $this->providerCode = 'funjet';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
