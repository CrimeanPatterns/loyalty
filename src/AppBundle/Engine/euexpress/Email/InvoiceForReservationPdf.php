<?php

namespace AwardWallet\Engine\euexpress\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InvoiceForReservationPdf extends \TAccountChecker
{
    public $mailFiles = "euexpress/it-521702427.eml, euexpress/it-529198613.eml, euexpress/it-537120865.eml, euexpress/it-547338898.eml, euexpress/it-554603602.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            // type 1 - Custom Itinerary
            'Custom Itinerary' => 'Custom Itinerary',
            'Created Date:'    => 'Created Date:',
            'Itinerary:'       => 'Itinerary:',
            // type 2 - Client/Agent Copy
            'First Name'  => 'First Name',
            'Middle Name' => 'Middle Name',
            'Last Name'   => 'Last Name',
        ],
    ];

    private $detectFrom = "@europeexpress.com";
    private $detectSubject = [
        // en
        'Europe Express - Invoice for Reservation #',
    ];
    private $detectBody = [
        //        'en' => [
        //            '',
        //        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]europeexpress\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Europe Express') === false
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text, $type = null)
    {
        // detect provider
        if ($this->containsText($text, ['@europeexpress.com']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (empty($type) || $type === 1) {
                // Custom Itinerary
                if (!empty($dict['Custom Itinerary'])
                    && !empty($dict['Created Date:'])
                    && !empty($dict['Itinerary:'])
                    && $this->containsText($text, $dict['Custom Itinerary']) === true
                    && $this->containsText($text, $dict['Created Date:']) === true
                    && $this->containsText($text, $dict['Itinerary:']) === true
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (empty($type) || $type === 2) {
                // Client/Agent Copy
                if (!empty($dict['First Name'])
                    && !empty($dict['Middle Name'])
                    && !empty($dict['Last Name'])
                    && $this->containsText($text, $dict['First Name']) === true
                    && $this->containsText($text, $dict['Middle Name']) === true
                    && $this->containsText($text, $dict['Last Name']) === true
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text, 1) == true) {
                $type = '1';
                $this->parseEmailPdfType1($email, $text);

                break;
            }
        }

        if (count($email->getItineraries()) > 0) {
            $confs = array_column($email->getTravelAgency()->getConfirmationNumbers(), 0);
            $conf = array_shift($confs);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectPdf($text, 2) == true) {
                    if (!empty($conf) && preg_match("/\n *Quote ?# :? ?{$conf}\b/", $text)
                        && preg_match("/\n *Total Price \((?<currency>[A-Z]{3})\): *(?<total>\d.*)/", $text, $m)
                    ) {
                        $email->price()
                            ->total(PriceHelper::parse($m['total'], $m['currency']))
                            ->currency($m['currency'])
                        ;
                    }
                }
            }
        }

        if (count($email->getItineraries()) === 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectPdf($text, 2) == true) {
                    $type = '2';
                    $this->parseEmailPdfType2($email, $text);

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

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

    private function parseEmailPdfType1(Email $email, ?string $textPdf = null)
    {
        $email->ota()
            ->confirmation($this->re("/ {3,}Reservation #([\d\-]{5,})\s*\n/", $textPdf));

        $hotelsSegments = [];
        $hotelsAddress = [];

        $travellers = preg_split("/\s*\n\s*/", trim($this->re("/\n *Passengers:(?: {5,}.*)\n((.+\n)+)\s* *Itinerary:/", $textPdf)));
        $travellers = array_filter(preg_replace("/^(?:TBD|TBA) .+/", '', $travellers));
        $itinerariesFull = preg_replace("/^.+\n *Itinerary:\n/s", '', $textPdf);
        $itinerariesFull = preg_replace("/\n {20,}[\d\-]+\n {20,}[\d\-]+\n {20,}\S+@europeexpress.com\n/", "\n", $itinerariesFull);

        $days = $this->split("/\n( {0,10}\S.*)/", "\n\n" . $itinerariesFull);

        foreach ($days as $dayText) {
            $itinerariesDate = strtotime($this->re("/^ *(\S.*)/", $dayText));
            $itineraries = preg_replace("/^.+\n+/", '', $dayText);

            $hotelsSearchName = array_unique(array_column($hotelsSegments, 'name'));
            $re = "/\n( *[A-Z]{3} (?:.+\n){1,2} *Depart ?:|.*Transfer ?:(?:.+\n){1,2} *Pickup ?:|.+\n *Hotel Address ?:|.+\n *Highlights| *(?:\w ?)+ - (?:\w ?)+ - (?:\w ?)+ .+?\d{1,2}:\d{2}(?: *[apAP][mM])?\b" .
                (!empty($hotelsSearchName) ? '| *' . $this->opt($hotelsSearchName) : '') . ")/";
            $segments = $this->split($re, "\n\n" . $itineraries);

            foreach ($segments as $sText) {
                //  FLIGHT SEGMENT
                if (preg_match("/^(.*\n){1,3} *Depart ?:/", $sText)) {
                    $this->logger->debug('type: Flight');
                    //  EWR Newark, NJ           MXP Milan Malpensa,      Sun 02 Oct 2022           United Airlines
                    //  Depart: 7:15PM           Italy                    Arrive: 9:10AM            Flight #19
                    $fl = $this->re("/^(\s*\S.+[\s\S]+?)(?:\s+operated by|\n\n|\s*$)/", $sText);
                    $table = $this->createTable($fl, $this->columnPositions($this->inOneRow($fl)));
                    // $this->logger->debug('$table = ' . print_r($table, true));
                    if (!isset($flighs)) {
                        $flighs = $email->add()->flight();

                        $flighs->general()
                            ->noConfirmation()
                            ->travellers($travellers)
                        ;
                    }

                    $s = $flighs->addSegment();

                    // Airline
                    $s->airline()
                        ->name($this->nice($this->re("/^\s*(.+?)\s*\n\s*Flight\s*#/s", $table[3] ?? '')))
                        ->number($this->re("/^\s*.+?\s*\n\s*Flight\s*#\s*(\d{1,5})\s*$/s", $table[3] ?? ''))
                    ;

                    $fDate = $this->normalizeDate($this->re("/^\s*(.+?)\n\s*Arrive:/", $table[2] ?? ''));

                    // Departure
                    $dTime = $this->re("/\n\s*Depart:\s*(.+?)\s*$/", $table[0] ?? '');
                    $s->departure()
                        ->code($this->re("/^\s*([A-Z]{3}) \S+/", $table[0] ?? ''))
                        ->name($this->nice($this->re("/^\s*[A-Z]{3} (.+?)\s+Depart:/s", $table[0] ?? '')))
                        ->date((!empty($fDate) && !empty($dTime)) ? strtotime($dTime, $fDate) : null)
                    ;

                    // Arrival
                    $aTime = $this->re("/\n\s*Arrive:\s*(.+?)\s*$/", $table[2] ?? '');
                    $s->arrival()
                        ->code($this->re("/^\s*([A-Z]{3}) \S+/", $table[1] ?? ''))
                        ->name($this->nice($this->re("/^\s*[A-Z]{3} (.+?)\s*$/s", $table[1] ?? '')))
                        ->date((!empty($fDate) && !empty($aTime)) ? strtotime($aTime, $fDate) : null)
                    ;

                    continue;
                }

                //  HOTEL SEGMENT Continue Part
                if (!empty($hotelsSearchName) && preg_match("/^\s*(?<name>{$this->opt($hotelsSearchName)})\s*\n *• *(?<type>.+)/", $sText, $m)) {
                    $this->logger->debug('type: Hotel Continue');
                    //  H10 Palazzo Canova, Venice, Italy
                    //     • Superior Room - with Full American Buffet Breakfast, 1 Double Room

                    foreach ($hotelsSegments as $i => $hs) {
                        if ($hs['name'] == $m['name'] && $hs['type'] == $m['type'] && strtotime('+ 1 day', $hs['date']) === $itinerariesDate) {
                            $hotelsSegments[$i]['date'] = $itinerariesDate;
                        }
                    }

                    continue;
                }

                //  HOTEL SEGMENT
                if (preg_match("/^(.*\n){1,3} *Hotel Address ?:/", $sText)) {
                    $this->logger->debug('type: Hotel');
                    //  H10 Palazzo Canova, Venice, Italy
                    //  Hotel Address : Riva del Vin, 744, Venice 30125, ITALY
                    //  Telephone : +39 041 520 0172
                    //     • Superior Room - with Full American Buffet Breakfast, 1 Double Room

                    $hotel = $email->add()->hotel();

                    $hotel->general()
                        ->noConfirmation()
                        ->travellers($travellers)
                    ;

                    // Hotel
                    $hotel->hotel()
                        ->name($this->re("/^\s*(.+?)\s*\n\s*Hotel Address *:/s", $sText))
                        ->address($this->re("/\n *Hotel Address *: *(.+)/", $sText))
                        ->phone($this->re("/\n *Telephone *: *(.+)/", $sText), true, true)
                    ;
                    $hotelsAddress[preg_replace("/^(.+?),.*/", '$1', $hotel->getHotelName())] = $hotel->getAddress();

                    // Booked
                    $hotel->booked()
                        ->checkIn($itinerariesDate);

                    $r = $hotel->addRoom();
                    $r->setType($this->re("/\n *• *(.+)/", $sText));

                    $hotelsSegments[] = [
                        'name' => $hotel->getHotelName(),
                        'type' => $r->getType(),
                        'date' => $itinerariesDate,
                        'id'   => $hotel->getId(),
                    ];

                    continue;
                }

                //  TRANSFER SEGMENT
                if (preg_match("/^(.*\n){1,3} *Pickup ?:/", $sText)) {
                    $this->logger->debug('type: Transfer');
                    // Private Transfer: Santa Lucia Station to Venice Hotel - Valid for Trains Arriving between7:01AM and
                    // 8:29PM
                    // Pickup: Comfort - Flexible - Prima 11:35-14:03 Flexi
                    // Dropoff: H10 Palazzo Canova | Riva del Vin, 744 | Venice

                    if (!isset($transfers)) {
                        $transfers = $email->add()->transfer();

                        $transfers->general()
                            ->noConfirmation()
                            ->travellers($travellers)
                        ;
                    }

                    $points = [
                        'pickUp'  => $this->nice($this->re("/\n *Pickup ?:(.+?)\n\s*Dropoff/s", $sText)),
                        'dropOff' => $this->nice($this->re("/\n *Dropoff ?:(.+?)(?:\n\n|\s*$)/s", $sText)),
                    ];
                    $pointsFromHeader = [
                        'pickUp'  => $this->nice($this->re("/^\s*[\w\s]+:(.+?) to .+?\n *Pickup ?:/is", $sText)),
                        'dropOff' => $this->nice($this->re("/^\s*[\w\s]+:.+? to (.+?)(?: \- |\s*\n\s*Pickup ?:)/is", $sText)),
                    ];

                    $routes = [];

                    foreach ($points as $key => $point) {
                        if (preg_match("/^(?<dCode>[A-Z]{3})-(?<aCode>[A-Z]{3}).*@ (?<time>\d{1,2}:\d{2}.*)/", $point, $m)
                            || preg_match("/^ *[A-Z\d]+; *(?<dCode>[A-Z]{3})-(?<aCode>[A-Z]{3}) *@ (?<time>\d{1,2}:\d{2}.*)/", $point, $m)
                        ) {
                            // EWR-MXP - UA - 19 @ 9:10AM
                            // DL168; JFK-BCN @ 12:05pm
                            $routes[$key . 'Code'] = ($key == 'pickUp') ? $m['aCode'] : $m['dCode'];
                            $routes[$key . 'Date'] = strtotime($m['time'], $itinerariesDate);
                            $routes[$key . 'Date'] = strtotime(($key == 'pickUp') ? '+ 30 minute' : '- 3 hour', $routes[$key . 'Date']);
                        } elseif (preg_match("/^(\w ?)+ - (\w ?)+ - (\w ?)+ .+?(?<dTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b(?<dName>.*)[\-\\/] ?(?<aTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b(?<aName>.*)/i", $point, $m)) {
                            // (Pickup|Dropoff): Comfort - Flexible - Prima 11:35-14:03 Flexi
                            // (Pickup|Dropoff): Comfort - Semi Flex - Standard Premier EUROSTAR (9018) 10:24AM London St. Pancras
                            //  International / 1:58PM Paris Gare du Nord

                            if (empty($routes[$key . 'dName']) || empty($routes[$key . 'aName'])) {
                                $routes[$key . 'Name'] = $pointsFromHeader[$key];
                            } else {
                                $routes[$key . 'Name'] = ($key == 'pickUp') ? $m['aName'] : $m['dName'];
                            }
                            $routes[$key . 'Date'] = ($key == 'pickUp') ? strtotime($m['aTime'], $itinerariesDate) : strtotime($m['dTime'], $itinerariesDate);
                            $routes[$key . 'Date'] = strtotime(($key == 'pickUp') ? '+ 30 minute' : '- 30 minute', $routes[$key . 'Date']);
                        } elseif (preg_match("/^(\w ?)+ \| (\w ?)+ @ (?<dTime>\d{1,2}:\d{2}(?: *[ap]m)?)$/i", $point, $m)) {
                            // (Pickup|Dropoff): Norwegian | Prima @ 5:00pm

                            if (count($transfers->getSegments())) {
                                $email->removeItinerary($transfers);

                                continue 2;
                            }
                        } else {
                            // (Pickup|Dropoff): H10 Palazzo Canova | Riva del Vin, 744 | Venice
                            $routes[$key . 'Name'] = preg_replace("/\s+\|\s+/", ', ', $point);
                        }
                    }

                    $s = $transfers->addSegment();

                    // Departure
                    if (!empty($routes['pickUpCode'])) {
                        $s->departure()
                            ->code($routes['pickUpCode']);
                    }

                    if (!empty($routes['pickUpName'])) {
                        $s->departure()
                            ->address($routes['pickUpName']);
                    }

                    if (!empty($routes['pickUpDate'])) {
                        $s->departure()
                            ->date($routes['pickUpDate']);
                    } else {
                        $s->departure()
                            ->noDate();
                    }

                    // // Arrival
                    if (!empty($routes['dropOffCode'])) {
                        $s->arrival()
                            ->code($routes['dropOffCode']);
                    }

                    if (!empty($routes['dropOffName'])) {
                        $s->arrival()
                            ->address($routes['dropOffName']);
                    }

                    if (!empty($routes['dropOffDate'])) {
                        $s->arrival()
                            ->date($routes['dropOffDate']);
                    } else {
                        $s->arrival()
                            ->noDate();
                    }

                    if (empty($routes['pickUpDate']) && empty($routes['dropOffDate'])) {
                        $transfers
                            ->removeSegment($s);
                    }

                    continue;
                }

                //  TRAIN SEGMENT
                if (preg_match("/^ *(?:\w ?)+ - (?:\w ?)+ - (?:\w ?)+.*\b(?<dTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b(?<dName>.*)\s*[\-\\/]\s*(?<aTime>\d{1,2}:\d{2}(?: *[ap]m)?)/si", $sText)) {
                    $this->logger->debug('type: Train');
                    // Comfort - Semi Flex - Standard Premier EUROSTAR (9018) 10:24AM London St. Pancras International /
                    // 1:58PM Paris Gare du Nord
                    //
                    // Premier - Semi Flex - Business 10:48-13:15 Economy

                    if (preg_match("/^ *(?:\w ?)+ - (?:\w ?)+ - (?<cabin>(?:\w ?)+?) (?<dTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b\s*[\-\\/]\s*(?<aTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b(?<aName>.*?)(\n *Confirmation Details:|\n\n|\s*$)/si", $sText, $m)) {
                        continue;
                    }

                    if (!isset($trains)) {
                        $trains = $email->add()->train();

                        $trains->general()
                            ->noConfirmation()
                            ->travellers($travellers)
                        ;
                    }

                    $s = $trains->addSegment();

                    if (preg_match("/^ *(?:\w ?)+ - (?:\w ?)+ - (?<cabin>(?:(?:\w ?)+ - )?(?:\w ?)+?) (?<info>[A-Z][A-Z \d\(\)]+?)\b(?<dTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b(?<dName>.*?)\s*[\-\\/]\s*(?<aTime>\d{1,2}:\d{2}(?: *[ap]m)?)\b(?<aName>.*?)(\n *Confirmation Details:|\n\n|\s*$)/si", $sText, $m)) {
                        // Departure
                        $s->departure()
                            ->name($this->nice($m['dName']))
                            ->date(strtotime($m['dTime'], $itinerariesDate))
                        ;

                        // Arrival
                        $s->arrival()
                            ->name($this->nice($m['aName']))
                            ->date(strtotime($m['aTime'], $itinerariesDate))
                        ;

                        // Extra
                        $s->extra()
                            ->service($this->re("/^\s*(.+?) *\(/", $m['info']))
                            ->number($this->re("/\(\s*(\d+)\s*\)\s*$/", $m['info']))
                            ->cabin(trim($m['cabin']))
                        ;
                    }

                    continue;
                }
            }

            foreach ($hotelsSegments as $i => $hs) {
                if (strtotime('+ 1 day', $hs['date']) === $itinerariesDate) {
                    foreach ($email->getItineraries() as $it) {
                        if ($it->getId() === $hs['id']) {
                            $it->booked()
                                ->checkOut($itinerariesDate);
                        }
                    }
                    unset($hotelsSegments[$i]);
                }
            }
        }

        if (!empty($transfers)) {
            foreach ($transfers->getSegments() as $seg) {
                if (isset($hotelsAddress[$seg->getDepAddress()])) {
                    $seg->departure()
                        ->address($seg->getDepAddress() . ', ' . $hotelsAddress[$seg->getDepAddress()]);
                }

                if (isset($hotelsAddress[$seg->getArrAddress()])) {
                    $seg->arrival()
                        ->address($seg->getArrAddress() . ', ' . $hotelsAddress[$seg->getArrAddress()]);
                }
            }

            if (count($transfers->getSegments()) === 0) {
                $email->removeItinerary($transfers);
            }
        }

        return $email;
    }

    private function parseEmailPdfType2(Email $email, ?string $textPdf = null)
    {
        $email->ota()
            ->confirmation(str_replace(' ', '', $this->re("/\n *Quote #:? *([\d\- ]{5,})\s*\n/", $textPdf)));

        $travellers = [];

        if (preg_match("/\n( *First Name +Middle Name +Last Name) +.+\n+((?:.+\n+?)+?)\n *(?:Depart|Date) {2,}/", $textPdf, $m)) {
            $cols = $this->columnPositions($this->inOneRow($m[2]));
            $pos = strlen($m[1]);

            foreach ($cols as $c) {
                if ($c > $pos) {
                    $pos = $c;

                    break;
                }
            }
            $tTable = $this->createTable($m[2], [0, $pos]);
            $travellers = array_filter(array_map('trim', explode("\n", $tTable[0])));
            $travellers = preg_replace('/\s+/', ' ', $travellers);
        }

        $travellers = array_filter(preg_replace("/^(?:TBD|TBA) .+/", '', $travellers));

        $textPdf = preg_replace("/\n {20,}[\d\-]+\n {20,}[\d\-]+\n {20,}\S+@europeexpress.com\n/", "\n", $textPdf);
        $textPdf = preg_replace("/\n *Issue: .+ Page \d+ of \d+\n/", "\n", $textPdf);

        $relativeDate = strtotime($this->re("/\n *Date {3,}Item +.+\n *(\S.+?) {2}/", $textPdf));
        $itinerariesFlightFull = preg_replace("/\n *Depart {3,}Flight {3,}\n(.+?)\n *Date {3,}Item +/s", '', $textPdf);

        $segments = $this->split("/\n( {0,10}\w+ \w+ {2,}\d{1,2}:\d{2})/", "\n\n" . $itinerariesFlightFull);

        if (!empty($segments)) {
            $flighs = $email->add()->flight();

            $flighs->general()
                ->noConfirmation()
                ->travellers($travellers)
            ;
        }

        foreach ($segments as $sText) {
            $fl = $this->re("/^(.+)/", $sText);
            $table = $this->createTable($fl, $this->columnPositions($this->inOneRow($fl)));

            $s = $flighs->addSegment();

            // Airline
            $s->airline()
                ->name($this->nice($this->re("/^\s*\[\s*([A-Z\d]{2}) ?\\/ ?\d{1,5} *\]/", $table[2] ?? '')))
                ->number($this->nice($this->re("/^\s*\[\s*[A-Z\d]{2} ?\\/ ?(\d{1,5}) *\]/", $table[2] ?? '')))
            ;

            // Departure
            $dDate = EmailDateHelper::parseDateRelative($table[0] ?? null, $relativeDate);
            $s->departure()
                ->code($this->re("/^\s*\[([A-Z]{3})\] \S+/", $table[3] ?? ''))
                ->name($this->re("/^\s*\[[A-Z]{3}\] (\S+.+)/", $table[3] ?? ''))
                ->date((!empty($dDate)) ? strtotime($table[1] ?? null, $dDate) : null)
            ;

            // Arrival
            if (preg_match('/^ *(\w+ \w+) +(\d{1,2}:\d{2}.*)/', $table[5] ?? '', $m)) {
                $table[5] = $m[1];
                $table[6] = $m[2];
            }
            $aDate = EmailDateHelper::parseDateRelative($table[5] ?? null, $relativeDate);
            $s->arrival()
                ->code($this->re("/^\s*\[([A-Z]{3})\] \S+/", $table[4] ?? ''))
                ->name($this->re("/^\s*\[[A-Z]{3}\] (\S+.+)/", $table[4] ?? ''))
                ->date((!empty($aDate)) ? strtotime($table[6] ?? null, $aDate) : null)
            ;
        }

        $itinerariesFull = $this->re("/\n( *Date {3,}Item +.+\n[\s\S]+?)\n +Total Price \(/", $textPdf);

        $days = $this->split("/\n( {0,10}\S.+)/", "\n\n" . $itinerariesFull);
        $hotels = [];
        $trains = [];
        $headerPos = [0];

        foreach ($days as $dayText) {
            $itinerariesDate = strtotime($this->re("/^ *(\S.*?) {2,}/", $dayText));
            $rows = array_filter(explode("\n", $dayText));
            $hSeg = '';
            $tSeg = '';
            $trainStart = false;
            $hotelStart = false;

            foreach ($rows as $row) {
                if (preg_match("/^( *Date {3,}Item +.+)/", $row, $m)) {
                    $headerPos = $this->columnPositions($m[1]);

                    continue;
                }
                $rTable = $this->createTable($row, $headerPos);
                // $this->logger->debug('$rTable = '.print_r( $rTable,true));
                if (isset($rTable[3]) && preg_match("/^\d Night/s", $rTable[3] ?? '')) {
                    if (!empty($hSeg)) {
                        $hotels[] = $hSeg;
                    }
                    $hSeg = $row;
                    $hotelStart = true;
                    $trainStart = false;

                    continue;
                }

                if (isset($rTable[2]) && preg_match("/^\d Passenger/s", $rTable[2] ?? '')) {
                    if (!empty($tSeg)) {
                        $trains[] = $tSeg;
                    }
                    $tSeg = $row;
                    $trainStart = true;
                    $hotelStart = false;

                    continue;
                }

                if ($hotelStart === true && preg_match("/^\d /", $rTable[2] ?? '')) {
                    $hotelStart = false;

                    continue;
                }

                if ($trainStart === true && preg_match("/^\d /", $rTable[2] ?? '')) {
                    $trainStart = false;

                    continue;
                }

                if ($hotelStart === true) {
                    $hSeg .= $row;

                    continue;
                }

                if ($trainStart === true) {
                    $tSeg .= $row;

                    continue;
                }
            }

            if (!empty($hSeg)) {
                $hotels[] = $hSeg;
            }

            if (!empty($tSeg)) {
                $trains[] = $tSeg;
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        // $date = preg_replace($in, $out, $date);
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

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }

    private function unionColumns($col1, $col2)
    {
        $col1Rows = explode("\n", $col1);
        $col2Rows = explode("\n", $col2);
        $newCol = '';

        for ($c = 0; $c < max(count($col1Rows), count($col2Rows)); $c++) {
            $newCol .= ($col1Rows[$c] ?? '') . ' ' . ($col2Rows[$c] ?? '') . "\n";
        }

        return $newCol;
    }
}
