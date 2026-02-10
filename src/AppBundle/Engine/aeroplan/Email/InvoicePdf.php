<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-125171164.eml, aeroplan/it-426350541.eml, aeroplan/it-535919079.eml, aeroplan/it-657285829.eml, aeroplan/it-671657929-cruise.eml";
    public $subjects = [
        '/Invoice\s*\d[\d\s]{3}\d/',
        '/Invoice\s*:\s*Air Canada Vacations Booking\s+\d[\d\s]{3}\d/i',
        '/Facture : Vacances Air Canada, réservation no/',
    ];

    public $lang = 'en';
    public $code;
    public $pdfNamePattern = ".*pdf";

    public $travellers;
    public $hotelsPassengers;

    public static $dictionary = [
        "en" => [
            'Invoice Details'      => 'Invoice Details',
            'Confirmation Details' => 'Confirmation Details',
            // 'Flight Information' => '',
            'Hotel Details' => ['Hotel Details', 'Package Details'],
            // 'Total Invoice Amount' => '',
            // 'Booking' => '',
            // 'Booking Date' => '',
            'Passenger Information' => ['Passenger Information', 'Passenger(s) Information'],
            // 'First name(s) and name(s)' => '',
            'FlightPartEnd' => ['Package Details', 'Hotel Details', 'Package Details', 'Product Details', 'Other Product Details', 'Invoice Details'],
            // 'From' => '',
            // 'Passengers' => '',
            // 'CANCELLED' => '',
            // 'Departure' => '',
            // 'Arrival' => '',
            // 'Operated by' => '',
            'HotelPartEnd' => ['Product Details', 'Other Product Details', 'Invoice Details'],
            // 'Hotel Description' => '',
            // 'Transfers Description' => '',
            'Confirmation Number' => ['Confirmation Number', 'CRUISE CONFIRMATION', 'CRUISELINE CONFIRMATION', 'CONFIRMATION'],
            // 'Cruise Description' => '',
        ],
        "fr" => [
            'Invoice Details'           => 'Détails de la facture',
            'Confirmation Details'      => 'Détails de la confirmation',
            'Flight Information'        => 'Information sur les vols',
            'Hotel Details'             => 'Détails sur le forfait',
            'Total Invoice Amount'      => 'Total de la facture',
            'Booking'                   => 'Réservation',
            'Booking Date'              => 'Date réservation',
            'Passenger Information'     => 'Information passagers',
            'First name(s) and name(s)' => 'Prénoms et noms',
            'FlightPartEnd'             => ['Détails sur le forfait', 'Détails des produits'],
            'From'                      => 'De',
            'Passengers'                => 'Passagers',
            // 'CANCELLED' => '',
            'Departure'                 => 'Départ',
            'Arrival'                   => 'Arrivée',
            'Operated by'               => 'Opere par ',
            'HotelPartEnd'              => 'Détails de la facture',
            'Hotel Description'         => 'Description d\'hôtel',
            'Transfers Description'     => 'Description transfert',
            'Cruise Description'        => 'Description Croisiere',

            'Remarks and Special Requests' => 'Commentaires et demandes spéciales',
            'Confirmation Number'          => 'CRUISELINE CONFIRMATION',
            'Itinerary'                    => 'Itineraire',
            'Embarkation'                  => 'Embarquement',
            'Disembarkation'               => 'Debarquement',
            'Cabin'                        => 'Cabine',
            'Deck'                         => 'Pont',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('from', $headers) && $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) === true
            || array_key_exists('subject', $headers) && strpos($headers['subject'], 'Air Canada Vacations') !== false
        ) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text): bool
    {
        if (empty($text)) {
            return false;
        }

        if ($this->detectProv($text) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Invoice Details']) && !empty($dict['Confirmation Details'])
                && $this->containsText($text, $dict['Invoice Details']) === true
                && $this->containsText($text, $dict['Confirmation Details']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectProv($text): bool
    {
        if (strpos($text, 'WestJet Vacations') !== false) {
            $this->code = 'westjet';
        }

        if (strpos($text, 'Porter Escapes') !== false) {
            $this->code = 'porter';
        }

        if (strpos($text, 'Vacances Air Canada') === false && empty($this->code)) {
            return false;
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:vacv|vacancesaircanada|aircanadavacations)\.com$/i', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $flightText = $this->re("/\n *{$this->opt($this->t('Flight Information'))} *\n(.+?)\n *(?:{$this->opt($this->t('FlightPartEnd'))}|{$this->opt($this->t('Hotel Details'))}|{$this->opt($this->t('Product Details'))}|{$this->opt($this->t('Invoice Details'))})\n/s", $text);

        $flightText = preg_replace("/\n {20,}\d{5,} Page \d+\s*(?:\n|$)/", "\n", $flightText);

        $segments = $this->split("/\n(.+ {2,}{$this->opt($this->t('From'))} {2,}.* {2,}{$this->opt($this->t('Passengers'))}\n)/", "\n\n" . $flightText);

        foreach ($segments as $sText) {
            $tableText = preg_replace("/{$this->opt($this->t('Operated by'))}\s+[\s\S]+/", '', $sText);

            $sText = trim(preg_replace("/^.+\s+/", '', $sText));
            $f = $email->add()->flight();

            $confs = array_filter(preg_split("/\s*,\s*/", $this->re("/\n *{$this->opt($this->t('Booking'))} *\d+ *PNR ?: ?([A-Z\d,]{5,})(?: {3}|\n)/", $text)));

            if (count($confs) > 0) {
                foreach ($confs as $conf) {
                    $f->general()
                        ->confirmation($conf);
                }
            } else {
                $f->setNoConfirmationNumber(true);
            }

            $f->general()
                ->date(strtotime($this->re("/{$this->opt($this->t('Booking Date'))} *(\d+\-\w+\-\d{4})(?: {3}|\n)/", $text)));

            if (preg_match("/\s+(\d)\-(\d)\n/", $sText, $match)) {
                $passengerKeys = range($match[1], $match[2]);

                foreach ($passengerKeys as $key) {
                    $f->general()
                        ->traveller($this->travellers[$key]);
                }
            } else {
                $f->general()
                    ->travellers($this->travellers, true);
            }

            if (preg_match("/^\s*[A-Z\d]{2} ?\d{1,5} +(?<status>{$this->opt($this->t('CANCELLED'))}\b)/u", $sText, $m)) {
                $f->general()
                    ->status(trim($m['status']));

                $tableText = preg_replace("/^(.+\n\s*[A-Z\d]{2} ?\d{1,5} +){$this->opt($this->t('CANCELLED'))}\b/u", '$1' . str_pad('', strlen($m['status']), ' '), $tableText);
            }
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            $s = $f->addSegment();

            $re = "/^\s*(?<aName>[A-Z\d]{2}) ?(?<aNumber>\d{1,4}) *(?<cabin>\D+)[\d\-]+"
                . "\n\s*(?<depName>\D+)\((?<depCode>[A-Z]{3})\) *(?<arrName>\D+)\((?<arrCode>[A-Z]{3})\)"
                . "\n(\D+(?<depDate>[\w\-]+))\D+(?<arrDate>[\w\-]+)"
                . "\n\s*(?<conf>[A-Z\d]{6})?\s*{$this->opt($this->t('Departure'))}\s*(?<depTime>[\d\:]+)\s*{$this->opt($this->t('Arrival'))}\s*(?<arrTime>[\d\:]+)"
                . "(?:\n\s*{$this->opt($this->t('Operated by'))} *(?<operator>.+)|\s*$)/u";

            //MONTREAL, QC (YUL) (YUL)   HALIFAX, NS (YHZ) (YHZ)
            $re2 = "/^\s*(?<aName>[A-Z\d]{2}) ?(?<aNumber>\d{1,4}) *(?<cabin>\D+)[\d\-]+"
                . "\n\s*(?<depName>\D+)\((?<depCode>[A-Z]{3})\) *\([A-Z]{3}\)\s+(?<arrName>\D+)\((?<arrCode>[A-Z]{3})\)\s*\([A-Z]{3}\)"
                . "\n(\D+(?<depDate>[\w\-]+))\D+(?<arrDate>[\w\-]+)"
                . "\n\s*(?<conf>[A-Z\d]{6})?\s*{$this->opt($this->t('Departure'))}\s*(?<depTime>[\d\:]+)\s*{$this->opt($this->t('Arrival'))}\s*(?<arrTime>[\d\:]+)"
                . "(?:\n\s*{$this->opt($this->t('Operated by'))} *(?<operator>.+)|\s*$)/u";

            $re3 = "/^(?<cabin>\D+)\s+[\d\-]+\s*(?<aName>[A-Z\d]{2}) ?(?<aNumber>\d{2,4})\s+(?<depName>.*)\((?<depCode>[A-Z]{3})\)\s+(?<arrName>.*)\((?<arrCode>[A-Z]{3})\)\s+(?<operator>.*)\s+[A-Z]{3}\s+(?<depDate>[\d\-\w]+\d{4})\s+[A-Z]{3}\s+(?<arrDate>[\d\-\w]+\d{4})\s+\D*(?<depTime>[\d\:]+)\s+\D*(?<arrTime>[\d\:]+)$/su";
            $re4 = "/^(?<cabin>\D+)\s+[\d\-]+\s*(?<aName>[A-Z\d]{2}) ?(?<aNumber>\d{2,4})\s+(?<depName>.*Republic)\s+(?<arrName>.*)\((?<arrCode>[A-Z]{3})\)\s+(?<operator>.*Airlines)\s*\((?<depCode>[A-Z]{3})\)\s+[A-Z]{3}\s+(?<arrDate>[\d\-\w]+\d{4})\s+[A-Z]{3}\s+(?<depDate>[\d\-\w]+\d{4})\s+\D*(?<arrTime>[\d\:]+)\s+\D*(?<depTime>[\d\:]+)$/su";

            /*
             * PD2682               XO                       1-2
               SAULT STE. MARIE, ON (YAM)   TORONTO, ON (YTZ) (YTZ)
               Confirmation #        (YAM)                        MON 06-NOV-2023
               UCJJ5K                MON 06-NOV-2023              Arrival 08:45
               Departure 07:25
             * */
            $re5 = "/^\s*(?<aName>[A-Z\d]{2}) ?(?<aNumber>\d{2,4}) *(?<cabin>\D+)[\d\-]+\n\s*(?<depName>\D+)"
                . "\((?<depCode>[A-Z]{3})\)[ ]{3,}(?<arrName>\D+)\((?<arrCode>[A-Z]{3})\)\nConfirmation [#]\s*\D+"
                . "(?<arrDate>[\w\-]+)\n\s*(?<conf>[A-Z\d]{6})?\s*(\D+(?<depDate>[\w\-]+))\s*{$this->opt($this->t('Arrival'))}\s*"
                . "(?<arrTime>[\d\:]+)\s*{$this->opt($this->t('Departure'))}\s*(?<depTime>[\d\:]+)(?:\n\s*{$this->opt($this->t('Operated by'))} *(?<operator>.+)|\s*$)/u";

            if (preg_match($re2, $sText, $m) || preg_match($re, $sText, $m) || preg_match($re4, $sText, $m) || preg_match($re3, $sText, $m) || preg_match($re5, $sText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['aNumber']);

                if (isset($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                    ->strict();

                if (isset($m['depName']) && !empty($m['depName'])) {
                    $s->departure()
                        ->name(trim($m['depName']));
                }

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                    ->strict();

                if (isset($m['arrName']) && !empty($m['arrName'])) {
                    $s->arrival()
                        ->name(trim($m['arrName']));
                }

                $s->extra()
                    ->cabin($m['cabin']);

                if (isset($m['conf']) && !empty($m['conf'])) {
                    $s->setConfirmation($m['conf']);
                }
            } elseif (count($table) == 6) {
                if (preg_match("/^.+\n\s*(?<aName>[A-Z\d]{2}) ?(?<aNumber>\d{2,4})\s*$/", $table[0], $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['aNumber']);
                }

                if (preg_match("/{$this->opt($this->t('Operated by'))} *(?<operator>.+)/", $sText, $m)) {
                    $s->airline()
                        ->operator($m['operator']);
                }

                if (preg_match("/^.+\n\s*(?<status>{$this->opt($this->t('CANCELLED'))}\b)?\s*(?<depName>\D+?)\s*"
                    . "\((?<depCode>[A-Z]{3})\)\n\s*\w+ (?<depDate>[\w\-]+)\n\s*{$this->opt($this->t('Departure'))}\s*(?<depTime>[\d\:]+)\s*/u", $table[1], $m)) {
                    $s->departure()
                        ->code($m['depCode'])
                        ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                        ->strict();

                    if (!empty(trim($m['depName']))) {
                        $s->departure()
                            ->name(trim($m['depName']));
                    }

                    if (!empty($m['status'])) {
                        $s->extra()
                            ->status(trim($m['status']));
                    }
                }

                if (preg_match("/^.+\n\s*(?<arrName>\D+?)\s*\((?<arrCode>[A-Z]{3})\)\n\s*\w+ (?<arrDate>[\w\-]+)\n\s*{$this->opt($this->t('Arrival'))}\s*(?<arrTime>[\d\:]+)\s*/u", $table[2], $m)) {
                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                        ->strict();

                    if (!empty(trim($m['arrName']))) {
                        $s->arrival()
                            ->name(trim($m['arrName']));
                    }
                }

                if (preg_match("/^.+\n\s*(?<cabin>[\s\S]+?)\s*$/", $table[4], $m)) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }
        }
    }

    public function ParseHotelPDF(Email $email, $text): void
    {
        $hotelText = $this->re("/\n {0,5}{$this->opt($this->t('Hotel Details'))}\n(.+?)\n {0,5}(?:{$this->opt($this->t('Product Details'))}|{$this->opt($this->t('Invoice Details'))})\n/s", $text);
        $segments = $this->split("/\n {0,5}((?:{$this->opt($this->t('Hotel Description'))} +.* +{$this->opt($this->t('Passengers'))}\n|{$this->opt($this->t('Transfers Description'))}))/", "\n\n" . $hotelText);

        foreach ($segments as $sText) {
            $sText = preg_replace("/\n(\n+\s+\d+\s*Page\s*\d+)/", "", $sText);

            if (preg_match("/^\s*{$this->opt($this->t('Transfers Description'))}/", $sText)) {
                if (!isset($transfers)) {
                    $transfers = $email->add()->transfer();

                    $transfers->general()
                        ->travellers($this->travellers, true);

                    $transfers->general()
                        ->noConfirmation();
                }

                if (preg_match("/^\s*.+\n.+\n*\s*.+ {2,}(?<fromDate>[\w\-]+\d{4}) {2,}(?<toDate>[\w\-]+\d{4})\n*/", $sText, $m)) {
                    $from = strtotime(str_replace('-', ' ', $m['fromDate']));
                    $to = strtotime(str_replace('-', ' ', $m['toDate']));

                    $city = null;
                    $name = null;

                    foreach ($email->getItineraries() as $it) {
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        if ($it->getType() === 'hotel') {
                            if ($from === $it->getCheckInDate()
                                && $to === $it->getCheckOutDate()
                            ) {
                                $city = $it->getAddress();
                                $name = $it->getHotelName() . ', ' . $it->getAddress();

                                break;
                            }
                        }
                    }

                    if (empty($city)) {
                        continue;
                    }

                    foreach ($email->getItineraries() as $it) {
                        /** @var \AwardWallet\Schema\Parser\Common\Flight $it */
                        if ($it->getType() == 'flight') {
                            foreach ($it->getSegments() as $fs) {
                                if ($from === strtotime('00:00', $fs->getArrDate())
                                    && stripos($fs->getArrName(), $city) === 0
                                ) {
                                    $s = $transfers->addSegment();

                                    $s->departure()
                                        ->name($fs->getArrName())
                                        ->code($fs->getArrCode())
                                        ->date($fs->getArrDate());

                                    if ($addressDep = $this->getAddressByName($s->getDepName())) {
                                        $s->departure()->address($addressDep);
                                    }

                                    $s->arrival()
                                        ->name($name)
                                        ->noDate();

                                    if ($addressArr = $this->getAddressByName($s->getArrName())) {
                                        $s->arrival()->address($addressArr);
                                    }

                                    foreach ($transfers->getSegments() as $ts) {
                                        if ($ts->getId() !== $s->getId()) {
                                            if ($ts->getDepName() === $s->getDepName()
                                                && $ts->getArrName() === $s->getArrName()
                                                && (!empty($s->getDepDate()) && $ts->getDepDate() === $s->getDepDate()
                                                    || !empty($s->getArrDate()) && $ts->getArrDate() === $s->getArrDate()
                                                )
                                            ) {
                                                $transfers->removeSegment($s);
                                            }
                                        }
                                    }
                                }

                                if ($to === strtotime('00:00', $fs->getDepDate())
                                    && stripos($fs->getDepName(), $city) === 0
                                ) {
                                    $s = $transfers->addSegment();

                                    $s->departure()
                                        ->name($name)
                                        ->noDate();

                                    if ($addressDep = $this->getAddressByName($s->getDepName())) {
                                        $s->departure()->address($addressDep);
                                    }

                                    $s->arrival()
                                        ->name($fs->getDepName())
                                        ->code($fs->getDepCode())
                                        ->date(strtotime('-4 hours', $fs->getDepDate()));

                                    if ($addressArr = $this->getAddressByName($s->getArrName())) {
                                        $s->arrival()->address($addressArr);
                                    }

                                    foreach ($transfers->getSegments() as $ts) {
                                        if ($ts->getId() !== $s->getId()) {
                                            if ($ts->getDepName() === $s->getDepName()
                                                && $ts->getArrName() === $s->getArrName()
                                                && (!empty($s->getDepDate()) && $ts->getDepDate() === $s->getDepDate()
                                                    || !empty($s->getArrDate()) && $ts->getArrDate() === $s->getArrDate()
                                                )
                                            ) {
                                                $transfers->removeSegment($s);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif (preg_match("/^\s*{$this->opt($this->t('Hotel Description'))}/", $sText)) {
                $h = $email->add()->hotel();
                $travellers = [];

                if (preg_match("/\s+(\d)(?:\-|\,)(\d)\n/", $sText, $match)) {
                    $passengerKeys = range($match[1], $match[2]);

                    foreach ($passengerKeys as $key) {
                        $travellers[] = $this->travellers[$key];
                    }

                    $h->general()
                        ->travellers($travellers);
                } else {
                    $h->general()
                        ->travellers($this->travellers, true);
                }

                $h->general()
                    ->noConfirmation()
                ;

                if (preg_match("/^\s*.+\n(?<hotelName>.+)\n\s*(?<address>.+) {2,}(?<arrDate>[\w\-]+\d{4}) {2,}(?<depDate>[\w\-]+\d{4})\s*\d+ {2,}(.+) {2,}(?<passengers>\d+[\d,\-]*)\n(?<type>(?:.+[ ]{3,}|.+))/", $sText, $m)
                /*Hotel Description                                             Destination       Check In      Check Out Nights Occupancy Passengers
                Barcelo Maya Caribe                                                                                                       1-3
                      Cancun, Mexico    27-APR-2024   04-MAY-2024 7           Dbl + Chd
                Superior Room Run of House-All Inclusive*/
                    || preg_match("/^\s*.+\n(?<hotelName>.+\s)(?<passengers>\d+[\d,\-]*)\n\s*(?<address>.+) {2,}(?<arrDate>[\w\-]+\d{4}) {2,}(?<depDate>[\w\-]+\d{4})\s*\d+ {2,}(.+)(?: {2,}|\n)(?<type>(?:.+[ ]{3,}|.+))/", $sText, $m)
                ) {
                    $nameParts = preg_split('/[ ]{2,}/', $m['hotelName']);

                    if (count($nameParts) === 2) {
                        $m['hotelName'] = $nameParts[0];
                    }

                    $h->hotel()
                        ->name(trim($m['hotelName'], '-*@ '))
                        ->address($m['address']);

                    $h->booked()
                        ->checkIn(strtotime($m['arrDate']))
                        ->checkOut(strtotime($m['depDate']));

                    $h->addRoom()
                        ->setType(trim($m['type']));

                    if (isset($this->hotelsPassengers[$h->getHotelName()])
                        && $this->hotelsPassengers[$h->getHotelName()]['checkIn'] === $h->getCheckInDate()
                        && $this->hotelsPassengers[$h->getHotelName()]['checkOut'] === $h->getCheckOutDate()
                    ) {
                        if ($this->hotelsPassengers[$h->getHotelName()]['passengers'] !== $m['passengers']) {
                            foreach ($email->getItineraries() as $it) {
                                /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                                if ($it->getType() == 'hotel' && $h->getId() !== $it->getId()) {
                                    if ($h->getHotelName() === $it->getHotelName()
                                        && $h->getCheckInDate() === $it->getCheckInDate()
                                        && $h->getCheckOutDate() === $it->getCheckOutDate()
                                    ) {
                                        $it->addRoom()
                                            ->setType(trim($m['type']));

                                        if (count($travellers) > 0) {
                                            foreach ($travellers as $traveller) {
                                                if (in_array($traveller, array_column($it->getTravellers(), 0))) {
                                                    continue;
                                                } else {
                                                    $it->general()
                                                        ->traveller($traveller);
                                                }
                                            }
                                        }
                                        $email->removeItinerary($h);

                                        break;
                                    }
                                }
                            }
                        } else {
                            $email->removeItinerary($h);
                        }
                    }
                    $this->hotelsPassengers[$h->getHotelName()] = [
                        'checkIn'    => $h->getCheckInDate(),
                        'checkOut'   => $h->getCheckOutDate(),
                        'passengers' => $m['passengers'],
                    ];
                }
            }
        }
    }

    public function ParseCruisePDF(Email $email, $text): void
    {
        $cruise = $email->add()->cruise();
        $cruise->general()->travellers($this->travellers, true);

        if (preg_match("/\n(?<headers>[ ]{0,5}{$this->opt($this->t('Cruise Description'))}[ ]{2}.+)\n(?<body>[\s\S]{3,}?)\n+[ ]{0,5}{$this->opt($this->t('Invoice Details'))}\n/", $text, $m)) {
            $cruiseHeaders = $m['headers'];
            $cruiseBody = preg_replace("/^[ ]+\d{5,}[ ]*Page[ ]*\d+$/mi", '', $m['body']);
        } else {
            $cruiseHeaders = $cruiseBody = null;
        }

        $dateEmbarkation = $dateDisembarkation = $timeEmbarkation = $timeDisembarkation = null;

        if (preg_match("/ (?<date1>\d{1,2}-[[:alpha:]]{3,}-\d{2,4})[ ]+(?<date2>\g<date1>)(?: |\n|$)/u", $cruiseBody, $m)) {
            $dateEmbarkation = strtotime($m['date1']);
            $dateDisembarkation = strtotime($m['date2']);
        }

        $remarksText = $this->re("/\n[ ]*{$this->opt($this->t('Remarks and Special Requests'))}(?: .+)?\n+(.+?)(?:\n\n|\n[ ]{20})/s", $text);

        if (preg_match("/^[ ]*((?:\S.*\S )?{$this->opt($this->t('Confirmation Number'))})[:# ]+([-A-Z\d]{5,})$/im", $remarksText, $m)) {
            $cruise->general()->confirmation($m[2], $m[1]);
        } elseif (!preg_match("/(?:{$this->opt($this->t('Confirmation Number'))}|\bConfirmation\b)/i", $remarksText, $m)) {
            $cruise->general()->noConfirmation();
        }

        $cruiseName = preg_match("/^[ ]*{$this->opt($this->t('Itinerary'))}[ ]*[:]+[ ]*((?:.+\n){1,2})[ ]*{$this->opt($this->t('Embarkation'))}[ ]*:/m", $remarksText, $m) ? preg_replace(["/\n+/", '/\s+/'], ['', ' '], $m[1]) : null
            ?? $this->re("/^[ ]*{$this->opt($this->t('Itinerary'))}[ ]*[:]+[ ]*(\S.+)$/m", $remarksText);
        $cruise->details()->description($cruiseName);

        $s = $cruise->addSegment();

        if (preg_match("/^[ ]*{$this->opt($this->t('Embarkation'))}[ ]*[:]+[ ]*(?<name>\S.+?\S)[ ]*[-]+[ ]*(?<time>{$this->patterns['time']})/m", $remarksText, $m)) {
            $s->setName($m['name']);
            $timeEmbarkation = $m['time'];
        }

        if ($dateEmbarkation && $timeEmbarkation) {
            $s->setAboard(strtotime($timeEmbarkation, $dateEmbarkation));
        }

        $s = $cruise->addSegment();

        if (preg_match("/^[ ]*{$this->opt($this->t('Disembarkation'))}[ ]*[:]+[ ]*(?<name>\S.+?\S)[ ]*[-]+[ ]*(?<time>{$this->patterns['time']})/m", $remarksText, $m)) {
            $s->setName($m['name']);
            $timeDisembarkation = $m['time'];
        }

        if ($dateDisembarkation && $timeDisembarkation) {
            $s->setAshore(strtotime($timeDisembarkation, $dateDisembarkation));
        }

        $cabin = $this->re("/^[ ]*{$this->opt($this->t('Cabin'))}[ ]*[:]+[ ]*(\S.+)$/m", $remarksText);
        $cruise->details()->room($cabin);

        $deck = $this->re("/^[ ]*{$this->opt($this->t('Deck'))}[ ]*[:]+[ ]*(\S.+)$/m", $remarksText);

        if (!empty($deck)) {
            $cruise->details()->deck($deck);
        }
    }

    public static function getEmailProviders()
    {
        return ['westjet', 'aeroplan', 'porter'];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) !== true) {
                continue;
            }

            $paxText = $this->re("/{$this->opt($this->t('Passenger Information'))}\n[ ]*{$this->opt($this->t('First name(s) and name(s)'))}\n+(.+?)\n+[ ]*(?:{$this->opt($this->t('Flight Information'))}|{$this->opt($this->t('FlightPartEnd'))})/isu", $text);

            $this->travellers = preg_replace("/([ ]{5,}.*)/", "", array_filter(array_map('trim', preg_split("/\n {0,5}\d {2,}((?:MSTR|MRS|MR|MS) +)?/", "\n\n" . $paxText))));

            if ($this->containsText($text, $this->t('Flight Information')) === true) {
                $this->ParseFlightPDF($email, $text);
            }

            if ($this->containsText($text, $this->t('Hotel Details')) === true) {
                $this->ParseHotelPDF($email, $text);
            }

            if (preg_match_all("/^[ ]*{$this->opt($this->t('Cruise Description'))}/m", $text, $cruiseMatches) && count($cruiseMatches[0]) === 1) {
                $this->ParseCruisePDF($email, $text);
            }
        }

        $otaConf = $this->re("/\n *{$this->opt($this->t('Booking'))} *(\d+) *(?:PNR)?/", $text);

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf,
                    $this->re("/\n *({$this->opt($this->t('Booking'))}) *\d+ *PNR/", $text)
                );
        }

        if (preg_match("/\s{2,}{$this->opt($this->t('Total Invoice Amount'))} *(\d[\d\.]*) *([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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

        if (empty($textRows)) {
            return '';
        }
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

    private function getAddressByName(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        if (strcasecmp($s, 'ROYALTON GRENADA, Grenada') === 0) {
            return 'Royalton Grenada, Maurice Bishop Memorial Highway, Calliste, Grenada'; // for Geoapify API
            // return "Magazine Beach Point Selines, St George's, Grenada"; // for other API
        }

        return null;
    }
}
