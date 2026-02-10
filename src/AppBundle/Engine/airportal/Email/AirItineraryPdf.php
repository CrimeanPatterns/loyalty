<?php

namespace AwardWallet\Engine\airportal\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AirItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "airportal/it-15397203.eml, airportal/it-15647666.eml, airportal/it-15647670.eml, airportal/it-16538269.eml, airportal/it-27940107.eml, airportal/it-33008030.eml, airportal/it-371363119.eml, airportal/it-464628993.eml, airportal/it-474247369.eml, airportal/it-474294384.eml"; // +1 bcdtravel(pdf)[en]
    public $reBody = [
        'en' => ['Departing:', 'Check-in:', 'Pick-up:', 'ADDITIONAL AGENT NOTES'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*\.pdf";
    public static $dict = [
        'en' => [
            'Total Tax'                => ['Total Tax', 'Estimated Tax'],
            'Total Air Fare'           => ['Total Air Fare', 'Estimated Total'],
            'Membership ID:'           => ['Membership ID:', 'Frequent Guest Number:', 'Members ID:'],
            'Additional Flight Info'   => ['Additional Flight Info', 'Agent Notes'],
            'ESTIMATED TOTAL PRICE IS' => ['ESTIMATED TOTAL PRICE IS', 'Approx rental cost is'],
        ],
    ];
    private $date;

    private $code;
    private $bodies = [//html
        'andavo' => [
            '//a[contains(@href,"www.andavotravel.com")]',
            'Andavo Travel',
        ],
        'christopherson' => [
            '//a[contains(@href,"www.cbtravel.com")]',
            'Andavo Travel',
        ],
        'airportal' => [
            '//a[contains(@href,".cbtat.com/")]',
        ],
    ];
    private static $headers = [
        'andavo' => [
            'from' => ['andavotravel.com'],
            'subj' => [
                'AirPortal - Airtinerary',
            ],
        ],
        'airportal' => [
            'from' => ['andavotravel.com'],
            'subj' => [
                'AirPortal - Airtinerary',
            ],
        ],
        'christopherson' => [
            'from' => ['cbtravel.com'],
            'subj' => [
                'AirPortal - Airtinerary',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $this->code = $code;
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    //$this->logger->debug($text);
                    if (!$this->assignLang($text)) {
                        $this->logger->notice("Can't determine a language!");

                        continue;
                    }
                    $this->parseEmail($email, $text);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        if (!empty($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((strpos($text, 'ADDITIONAL AGENT NOTES') !== false || preg_match('/Agency Locator\s+Booked by/', $text) > 0)
                && $this->assignLang($text)
            ) {
                return true;
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
        return count(self::$dict);
    }

    public function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email, $textPDF)
    {
        $textPDF = preg_replace("/^ *Page \d+ of \d+ *$/m", "", $textPDF);
        $email->ota()
            ->confirmation($this->re("#\s+([\w\/\-]{5,}) *\n[^\n]+{$this->t('Agency Locator')}#", $textPDF),
                $this->t('Agency Locator'));

        $itinerariesText = $this->findCutSection($textPDF, 'Passenger', ['Ticketing Information', 'ADDITIONAL AGENT NOTES']);

        if (empty($itinerariesText)) {
            $this->logger->alert('Itineraries not found!');

            return false;
        }

//        $flightsText = '';
        $itineraries = $this->splitText($itinerariesText, "#(^.*\w{2,}.* (?:{$this->opt($this->t('Departing:'))}|{$this->opt($this->t('Check-in:'))}|{$this->opt($this->t('Pick-up:'))}).+$)#m", true);

        foreach ($itineraries as $itinerary) {
            if (preg_match("#^.*\w{2,}.* {$this->opt($this->t('Departing:'))}.+$#m", $itinerary)) {
                $this->parseFlight($email, $itinerary);
            } elseif (preg_match("#^.*\w{2,}.* {$this->opt($this->t('Check-in:'))}.+$#m", $itinerary)) {
                $this->parseHotel($email, $itinerary);
            } elseif (preg_match("#^.*\w{2,}.* {$this->opt($this->t('Pick-up:'))}.+$#m", $itinerary)) {
                $this->parseCar($email, $itinerary);
            }
        }

        // p.currencyCode
        // p.total
        $tot = $this->getTotalCurrency($this->re("#^ *{$this->opt($this->t('Total Charged'))}: *(.+)#m", $textPDF));

        if (!empty($tot['Total'])) {
            $email->price()
                ->currency($tot['Currency'])
                ->total($tot['Total']);
        }

//        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Estimated Fare'))}: +(.+)#", $ticketBlock));
//        if (!empty($tot['Total'])) {
//            $f->price()
//                ->cost($tot['Total'])
//                ->currency($tot['Currency']);
//        }
//        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total Tax'))}: +(.+)#", $ticketBlock));
//        if (!empty($tot['Total'])) {
//            $f->price()
//                ->tax($tot['Total'])
//                ->currency($tot['Currency']);
//        }
//        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total Charged'))}: +(.+)#", $ticketBlock));
//        if (!empty($tot['Total'])) {
//            $f->price()
//                ->total($tot['Total'])
//                ->currency($tot['Currency']);
//        } else {
//            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Sub Total'))}: +(.+)#", $ticketBlock));
//            if (!empty($tot['Total'])) {
//                $f->price()
//                    ->total($tot['Total'])
//                    ->currency($tot['Currency']);
//            } else {
//                $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total Air Fare'))}: +(.+)#",
//                    $ticketBlock));
//                if (!empty($tot['Total'])) {
//                    $f->price()
//                        ->total($tot['Total'])
//                        ->currency($tot['Currency']);
//                }
//            }
//        }

        return true;
    }

    private function parseFlight(Email $email, $text)
    {
        $f = $email->add()->flight();
        $s = $f->addSegment();

        $pattern1 = "#"
            . "^ *(?<depCode>[A-Z]{3}) +{$this->opt($this->t('to'))} +(?<arrCode>[A-Z]{3}) +{$this->opt($this->t('Departing:'))}[^\n]+$"
            . "\s+(?<info>.+?)"
            . "(?:\s+^ *{$this->opt($this->t('Additional Flight Info'))}(?:[ ]{2}[^\n]+$|$)"
            . "\s+.+?)?"
            . "\s+^ *{$this->opt($this->t('Seat Information'))}(?:[ ]{2}[^\n]+$|$)"
            . "\s+(?<seats>.+)"
            . "#ms";

        if (!preg_match($pattern1, $text, $m)) {
            $this->logger->alert('Incorrect flight info-1!');

            return false;
        }

        // depCode
        // arrCode
        $s->departure()->code($m['depCode']);
        $s->arrival()->code($m['arrCode']);

        $table1 = $this->splitCols($m['info'], $this->colsPos($this->re("#(.+)#", $m['info']), 10));

        if (count($table1) !== 2) {
            $this->logger->alert('Incorrect flight table-1!');

            return false;
        }

        $pattern2 = "#"
            . "\s+^ *Status: *(?<status>[^\n]+)"
            . "\s+^(?<airports> *{$this->opt($this->t('Departure:'))}.+)"
            . "#ms";

        if (!preg_match($pattern2, $table1[0], $matches)) {
            $this->logger->alert('Incorrect flight info-2!');

            return false;
        }

        // status
        $f->general()->status($matches['status']);

        $table2 = $this->splitCols($this->re("#[^\n]+\s+(.+)#s", $matches['airports']), $this->colsPos($this->re("#(.+)#", $matches['airports']), 10));

        if (count($table2) !== 2) {
            $this->logger->alert('Incorrect flight table-2!');

            return false;
        }

        // depName
        // depDate
        $departureParts = explode("\n", trim($table2[0]));

        if (count($departureParts) === 2) {
            $s->departure()
                ->name($departureParts[0])
                ->date($this->normalizeDate($departureParts[1]));
        } elseif (count($departureParts) > 2 && preg_match("/\b20\d{2}\b.*\b\d{1,2} ?: ?\d{2}\D*$/", $departureParts[count($departureParts) - 1])
        ) {
            $s->departure()
                ->name(implode(' ', array_slice($departureParts, 0, count($departureParts) - 1)))
                ->date($this->normalizeDate($departureParts[count($departureParts) - 1]));
        }

        // arrName
        // arrDate
        $arrivalParts = explode("\n", trim($table2[1]));

        if (count($arrivalParts) === 2) {
            $s->arrival()
                ->name($arrivalParts[0])
                ->date($this->normalizeDate($arrivalParts[1]));
        } elseif (count($arrivalParts) > 2 && preg_match("/\b20\d{2}\b.*\b\d{1,2} ?: ?\d{2}\D*$/", $arrivalParts[count($arrivalParts) - 1])
        ) {
            $s->arrival()
                ->name(implode(' ', array_slice($arrivalParts, 0, count($arrivalParts) - 1)))
                ->date($this->normalizeDate($arrivalParts[count($arrivalParts) - 1]));
        }

        // airlineName
        // flightNumber
        if (preg_match("#^ *(.+?) +Flight +(\d+)#", $table1[1], $matches)) {
            // American Airlines Flight 390
            $s->airline()
                ->name($matches[1])
                ->number($matches[2]);
        }

        // duration
        $duration = $this->re("#^ *Duration: *(\d.+)$#m", $table1[1]);
        $s->extra()->duration($duration, false, true);

        // confirmation number
        $confNo = $this->re("#^ *Confirmation: *([A-Z\d]{5,})$#m", $table1[1]);

        if (empty($confNo) && strpos($text, 'Confirmation:') === false) {
            $f->general()->noConfirmation();
        } else {
            $f->general()->confirmation($confNo);
        }

        // aircraft
        $aircraft = $this->re("#^ *Aircraft: *(.+)$#m", $table1[1]);
        $s->extra()->aircraft($aircraft, false, true);

        // operatedBy
        $operator = $this->re("#^ *OPERATED BY +(.+)#im", $text);

        if (!$operator) {
            $operator = $this->re("#^ *Additional Flight Info: *Operated by +(.+)#im", $table1[1]);
        }
        $s->airline()->operator($operator, false, true);

        $table3 = $this->splitCols($this->re("#[^\n]+\s+(.+)#s", $m['seats']), $this->colsPos($this->re("#(.+)#", $m['seats']), 10));

        if (count($table3) !== 4) {
            $this->logger->alert('Incorrect flight table-3!');

            return false;
        }

        // travellers
        if (preg_match_all("#^ *([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$#m", $table3[0], $passengerMatches)) {
            $f->general()->travellers(array_unique($passengerMatches[1]));
        }

        // seats
        if (preg_match_all("#^ *(\d{1,5}[A-Z])$#m", $table3[1], $seatMatches)) {
            $s->extra()->seats($seatMatches[1]);
        }

        // cabin
        // bookingCode
        if (preg_match_all("#^ *(.+?) *\(([A-Z]{1,2})\)$#m", $table3[2], $classMatches)) {
            // Business (D)
            $s->extra()->cabin(implode('; ', array_unique($classMatches[1])));
            $s->extra()->bookingCode(implode('; ', array_unique($classMatches[2])));
        } elseif (preg_match_all("#^ *([A-Z]{1,2})$#m", $table3[2], $classMatches)) {
            // D
            $s->extra()->bookingCode(implode('; ', array_unique($classMatches[1])));
        }

        // accountNumbers
        if (preg_match_all("#^ *([-A-Z\d]{5,})$#m", $table3[3], $ffMatches)) {
            $f->program()->accounts($ffMatches[1], false);
        }
    }

    private function parseHotel(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $pattern1 = "#"
            . "^ *(?<name>[^\n]+?) +{$this->opt($this->t('Check-in:'))}[^\n]+$"
            . "\s+(?<info>.+?)"
            . "(?:"
            . "\s+^ *{$this->opt($this->t('Agent Notes'))}(?:[ ]{2}[^\n]+$|$)"
            . "\s+(?<agentNotes>.*?)"
            . ")?"
            . "\s+^ *{$this->opt($this->t('Rate Details'))}(?:[ ]{2}[^\n]+$|$)"
            . "\s+(?<rate>.+)"
            . "#ms";

        if (!preg_match($pattern1, $text, $m)) {
            $this->logger->alert('Incorrect hotel info-1!');

            return false;
        }

        // hotelName
        $h->hotel()->name($m['name']);

        $table1 = $this->splitCols($m['info'], $this->colsPos($this->re("#(.+)#", $m['info']), 10));

        if (count($table1) !== 2) {
            $this->logger->alert('Incorrect hotel table-1!');

            return false;
        }
        /*Status: CONFIRMED
        Check-in:                               Check-out:
        Sun Sep 08, 2019 - Time Not Available - Wed Sep 11, 2019 - Time Not Available -*/
        $pattern2 = "#"
            . "^ *(?<address>.+?)?"
            . "(?:\s+^ *(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])$)?" // +1 (443) 552-1400
            . "\s*^ *Status: *(?<status>[^\n]+)"
            . "\s+^(?<dates> *{$this->opt($this->t('Check-in:'))}.+)"
            . "#ms";

        if (!preg_match($pattern2, $table1[0], $matches)) {
            $this->logger->alert('Incorrect hotel info-2!');

            return false;
        }

        // address
        if ($matches['address'] == '') {
            $h->hotel()->noAddress();
        } else {
            $h->hotel()->address(preg_replace('/\s+/', ' ', $matches['address']));
        }

        // phone
        $h->hotel()->phone($matches['phone'], true);
        // status
        $h->general()->status($matches['status']);

        $table2 = $this->splitCols($this->re("#[^\n]+\s+(.+)#s", $matches['dates']), $this->colsPos($this->re("#(.+)#", $matches['dates']), 10));

        if (count($table2) !== 2) {
            $this->logger->alert('Incorrect hotel table-2!');

            return false;
        }

        // checkInDate
        $h->booked()->checkIn2(str_ireplace('- Time Not Available -', '', $table2[0]));

        // checkOutDate
        $h->booked()->checkOut2(str_ireplace('- Time Not Available -', '', $table2[1]));

        // confirmation number
        $confNo = $this->re("#^ *Confirmation +([A-Z\d]{5,})$#m", $table1[1]);

        if ($confNo !== null) {
            $h->general()->confirmation($confNo);
        } elseif ($confNo === null && !preg_match("#\bConfirmation\b#", $text)) {
            $h->general()->noConfirmation();
        }

        $r = $h->addRoom();

        // r.rate
        $rate = $this->re("#^ *{$this->opt($this->t('Rate Info:'))} *(.+)$#m", $table1[1]);
        $r->setRate($rate, false, true);

        // cancellation
        $cancellation = $this->re("#(?:^|\n) *{$this->opt($this->t('Cancellation Policy:'))} *([\s\S]+?)(?:{$this->opt($this->t('Room:'))}|{$this->opt($this->t('Membership ID:'))}|$)#", $table1[1]);

        if ($cancellation) {
            $cancellation = trim(preg_replace('/\s+/', ' ', $cancellation));
            $h->general()->cancellation($cancellation);
            $this->detectDeadLine($h, $cancellation);
        }

        // r.description
        $room = $this->re("#(?:^|\n) *{$this->opt($this->t('Room:'))} *([\s\S]+?)(?:{$this->opt($this->t('Membership ID:'))}|$)#", $table1[1]);

        if ($room !== null) {
            $r->setDescription(preg_replace('/\s+/', ' ', $room), true);
        }

        if ($rate === null && $room === null) {
            $h->removeRoom($r);
        }

        // accountNumbers
        $memberId = $this->re("#^ *{$this->opt($this->t('Membership ID:'))} *([Xx\d]{5,})$#m", $table1[1]);

        if ($memberId) {
            $h->program()->account($memberId, preg_match("/^[Xx]+\d+$/", $memberId) > 0);
        }

        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Approx total is'))} *(.+)#", $table1[1]));

        if (!empty($tot['Total'])) {
            $h->price()
                ->currency($tot['Currency'])
                ->total($tot['Total']);
        }

        $table3 = $this->splitCols($m['rate'], $this->colsPos($this->re("#(.+)#", $m['rate']), 10));

        if (count($table3) !== 6) {
            $this->logger->alert('Incorrect hotel table-3!');

            return false;
        }

        // travellers
        $guestNames = array_unique(array_map('trim', preg_replace('/\s+/', ' ', $this->splitText($table3[0], '/^ *([[:alpha:]]+ *\/ *[[:alpha:]]+|[A-Z][-.\'A-Z ]*[A-Z])/m', true))));
        // BRADLEY/RICHARD JOHN    |    BACHMANN GEORG
        foreach ($guestNames as $guestName) {
            $h->addTraveller($guestName);
        }

        // roomsCount
        $nights = $this->re("/Duration: *(\d+) nights\s*\n/", $table1[1]);
        preg_match_all('/^[\d\\/]+\d{4}$/m', $table3[2], $datesMatches);

        if (empty($datesMatches[0])) {
            if (preg_match_all('/^(\d{1,3})$/m', $table3[3], $roomsMatches)) {
                $h->booked()->rooms(array_sum($roomsMatches[1]));
            }

            // guestCount
            if (preg_match_all('/^(\d{1,3})$/m', $table3[4], $guestsMatches)) {
                $h->booked()->guests(array_sum($guestsMatches[1]));
            }

            // kidsCount
            if (preg_match_all('/^(\d{1,3})$/m', $table3[5], $kidsMatches)) {
                $h->booked()->kids(array_sum($kidsMatches[1]));
            }
        } elseif (count($datesMatches[0]) == $nights) {
            if (preg_match('/^(\d{1,3})$/m', $table3[3], $roomsMatches)) {
                $h->booked()->rooms($roomsMatches[1]);
            }

            // guestCount
            if (preg_match('/^(\d{1,3})$/m', $table3[4], $guestsMatches)) {
                $h->booked()->guests($guestsMatches[1]);
            }

            // kidsCount
            if (preg_match('/^(\d{1,3})$/m', $table3[5], $kidsMatches)) {
                $h->booked()->kids($kidsMatches[1]);
            }
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/^PERMITTED UP TO\s*(\d+)\s*DAYS?\s*BEFORE ARRIVAL$/i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }
        $h->booked()
            ->parseNonRefundable("/^NONREFUNDABLE$/");
    }

    private function parseCar(Email $email, $text)
    {
        $r = $email->add()->rental();

        $pattern1 = "#"
            . "^ *(?<company>[^\n]+?) +{$this->opt($this->t('Pick-up:'))}[^\n]+$"
            . "\s+(?<info>.+?)"
            . "(?:"
            . "\s+^ *{$this->opt($this->t('Agent Notes'))}(?:[ ]{2}[^\n]+$|$)"
            . "\s+(?<agentNotes>.*?)"
            . ")?"
            . "\s+^ *{$this->opt($this->t('Rate Details'))}(?:[ ]{2}[^\n]+$|$)"
            . "\s+(?<rate>.+)"
            . "#ms";

        if (!preg_match($pattern1, $text, $m)) {
            $this->logger->alert('Incorrect car info-1!');

            return false;
        }

        // company
        $r->extra()->company($m['company']);

        $table1 = $this->splitCols($m['info'], $this->colsPos($this->re("#(.+)#", $m['info']), 10));

        if (count($table1) !== 2) {
            $this->logger->alert('Incorrect car table-1!');

            return false;
        }

        $pattern2 = "#"
            . "(?:\s+^ *(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])$)?" // +1 (443) 552-1400
            . "\s+^ *Status: *(?<status>[^\n]+)"
            . "\s+^ *Pick-up: *(?<pickUp>.+)"
            . "\s+^ *Drop-off: *(?<dropOff>.+)"
            . "#ms";
        $pattern2_2 = "#"
            . "\s*^ *(?<location1>.+, [A-Z]{2} *\([A-Z]{3}\))"
            . "(?:\s+^ *(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])$)?" // +1 (443) 552-1400
            . "\s+^ *Rental Location: *(?<location2>(?:[^\n]+\n){1,2})"
            . "\s*^ *Pick-up: *(?<pickUp>.+)"
            . "\s+^ *Drop-off: *(?<dropOff>.+)"
            . "#ms";

        if (!preg_match($pattern2, $table1[0], $matches) and !preg_match($pattern2_2, $table1[0], $matches)) {
            $this->logger->alert('Incorrect car info-2!');

            return false;
        }

        if (isset($matches['location1'])) {
            $matches['location2'] = preg_replace("/ AP ON Site\s*$/", '', trim($matches['location2']));
            $matches['location2'] = preg_replace("/^(.+)$/s", '$1 Airport', $matches['location2']);
            $matches['location2'] = preg_replace("/\s*\n\s*/", ', ', $matches['location2']);
            $matches['pickUp'] = trim($matches['location2']) . ", " . trim($matches['location1']) . "\n" . $matches['pickUp'];
            $matches['dropOff'] = trim($matches['location2']) . ", " . trim($matches['location1']) . "\n" . $matches['dropOff'];
        }

        // pickUpLocation
        // pickUpDateTime
        $pickUpParts = explode("\n", trim($matches['pickUp']));
        $pickUpParts[2] = preg_replace('/^\s*-\s*Time Not Available\s*-\s*$/i', '', $pickUpParts[2] ?? '');

        if (preg_match("/^\s*Vendor\\/location Not Found\s*$/i", $pickUpParts[0])) {
            $this->logger->debug('remove rental. Empty pick up location');
            $email->removeItinerary($r);

            return false;
        }

        if (count($pickUpParts) === 3) {
            $r->pickup()
                ->location($pickUpParts[0])
                ->date2($pickUpParts[1] . ' ' . $pickUpParts[2]);
        } elseif (count($pickUpParts) > 3 && preg_match("/\b20\d{2}\b/", $pickUpParts[count($pickUpParts) - 2])
            && preg_match("/^(\D*\b\d{1,2}:\d{2}\D*|)$/", $pickUpParts[count($pickUpParts) - 1])
        ) {
            $r->pickup()
                ->location(implode(', ', array_slice($pickUpParts, 0, count($pickUpParts) - 2)))
                ->date2($pickUpParts[count($pickUpParts) - 2] . ' ' . $pickUpParts[count($pickUpParts) - 1]);
        }

        // dropOffLocation
        // dropOffDateTime
        $dropOffParts = explode("\n", trim($matches['dropOff']));
        $dropOffParts[2] = preg_replace('/^\s*-\s*Time Not Available\s*-\s*$/i', '', $dropOffParts[2] ?? '');

        if (preg_match("/^\s*Vendor\\/location Not Found\s*$/i", $dropOffParts[0])) {
            $this->logger->debug('remove rental. Empty drop off location');
            $email->removeItinerary($r);

            return false;
        }

        if (count($dropOffParts) === 3) {
            $r->dropoff()
                ->location($dropOffParts[0])
                ->date2($dropOffParts[1] . ' ' . $dropOffParts[2]);
        } elseif (count($dropOffParts) > 3 && preg_match("/\b20\d{2}\b/", $dropOffParts[count($dropOffParts) - 2])
            && preg_match("/^(\D*\b\d{1,2}:\d{2}\D*|)$/", $dropOffParts[count($dropOffParts) - 1])
        ) {
            $r->dropoff()
                ->location(implode(', ', array_slice($dropOffParts, 0, count($dropOffParts) - 2)))
                ->date2($dropOffParts[count($dropOffParts) - 2] . ' ' . $dropOffParts[count($dropOffParts) - 1]);
        }

        if (!empty($matches['phone'])) {
            $r->pickup()
                ->phone($matches['phone']);

            if ($r->getPickUpLocation() == $r->getDropOffLocation()) {
                $r->dropoff()
                    ->phone($matches['phone']);
            }
        }

        // confirmation number
        $confNo = $this->re("#^ *Confirmation +([A-Z\d][A-Z\d ]{5,}[A-Z\d])$#m", $table1[1]);
        $r->general()->confirmation(preg_replace('/ +[A-Z]{3,4}$/i', '', $confNo));

        // carType
        $carType = $this->re("#^ *Car Type: *(.+)$#m", $table1[1]);
        $r->car()->type($carType);

        // pickUpHours
        $pickUpHours = $this->re("#^ *Pick-Up Location Hours: *(.+?)\s+Member ID:#ms", $table1[1]);

        if (!empty($pickUpHours)) {
            $r->pickup()->openingHours(preg_replace('/\s+/', ' ', $pickUpHours));
        }

        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('ESTIMATED TOTAL PRICE IS'))} *(.+)#", $table1[1]));

        if (!empty($tot['Total'])) {
            $r->price()
                ->currency($tot['Currency'])
                ->total($tot['Total']);
        }

        $memberId = $this->re("#^ *{$this->opt($this->t('Membership ID:'))} *([Xx\d]{5,})$#m", $table1[1]);

        if ($memberId) {
            $r->program()->account($memberId, preg_match("/^[Xx]+\d+$/", $memberId) > 0);
        }
    }

    private function normalizeDate($strDime)
    {
        $in = [
            //Tue Jul 03, 2018 at 1 :43 PM
            '#^[\w\-]+\s+(\w+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+) *(:\d+\s*([ap]m)?)\s*$#ui',
        ];
        $out = [
            '$2 $1 $3, $4$5',
        ];

        $str = preg_replace($in, $out, $strDime);

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text)
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
