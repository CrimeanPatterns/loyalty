<?php

namespace AwardWallet\Engine\directravel\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "directravel/it-44862673.eml, directravel/it-50472051.eml";

    public static $detectHeaders = [
        'directravel' => [
            'from'    => 'direct2uitinerary@dt.com',
            'subject' => [
                "Ticketed Direct2U Itinerary for",
                "Invoiced Direct2U Itinerary for",
            ],
            'keyword' => 'Direct2U',
        ],
        'otg' => [
            'from'    => ['ovationtravel.com', '.lawyerstravel.com'],
            'subject' => [
                "en" => "Ticketed Travel Itinerary for",
                "Ticketed Exchange Travel Itinerary",
            ],
            'keyword' => 'Lawyers Travel',
        ],
        'camelback' => [
            'from'    => ['@camelbacktravel.com'],
            'subject' => [
                "en" => "Invoiced Direct2U Itinerary for",
            ],
            'keyword' => 'Camelback',
        ],
    ];

    public $detectCompany = [
        'directravel' => [
            'directtravel.streamthru.com',
            'DIRECT TRAVEL',
        ],
        'otg' => [
            'ovation.streamthru.com',
            '.lawyerstravel.com',
        ],
        'camelback' => [
            'Camelback Odyssey Travel',
            '@camelbacktravel.com',
        ],
    ];
    public $detectBody = [
        "en" => [
            "Complete details for your trip are below",
            "View your itinerary on our app",
            "Travel arrangements for",
            "Thank you for choosing Camelback Odyssey Travel",
        ],
    ];

    public static $dictionary = [
        "en" => [
            "Total:"           => ["Total:", "Total Charge:", "Total Invoiced Amount:"],
            "Agency reference" => ["Agency reference", "Agency Locator"],
        ],
    ];

    public $lang = "en";

    private $providerCode;
    private $pdfNamePattern = ".*pdf";
    private $pax;
    private $info;
    private $keywords = [
        'rentacar' => [
            'Enterprise',
        ],
        'hertz' => [
            'Hertz',
        ],
        'national' => [
            'National',
        ],
        'avis' => [
            'Avis',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $provider => $dHeaders) {
            if (isset($dHeaders['from'])) {
                $headers = (array) $dHeaders['from'];

                foreach ($headers as $header) {
                    if (!empty($header) && stripos($from, $header) !== false) {
                        $this->providerCode = $provider;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        foreach (self::$detectHeaders as $provider => $dHeaders) {
            if (empty($dHeaders['subject'])) {
                continue;
            }

            if (isset($this->providerCode) && $this->providerCode !== $provider) {
                continue;
            }

            foreach ($dHeaders['subject'] as $dSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($dHeaders['keyword'])}\b#i", $headers["subject"]) > 0)
                    && strpos($headers["subject"], $dSubject) !== false) {
                    $this->providerCode = $provider;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if ($this->detectBody($body)) {
            $this->logger->debug('go to parse by body - parser It4526683.php');

            return null;
        }

        $this->date = strtotime($parser->getHeader('date'));
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        $this->parseHtml($text, $email);
                    }
                } else {
                    return null;
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    private function parseFlight(array $flights, Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->travellers($this->pax);

        $tickets = [];

        if (preg_match_all("#^ *Ticket ?:\s+(\d{7,})\b#m", $this->info, $m)) {
            $tickets = array_values(array_unique($m[1]));
        }

        if (preg_match("#{$this->opt($this->t('Total:'))}\s+(.+)#i", $this->info, $m)) {
            $tot = $this->getTotalCurrency($m[1]);

            if ($tot['Total'] !== '') {
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $accounts = [];

        foreach ($flights as $root) {
//            $this->logger->debug('flight $root = '."\n".print_r( $root,true));

            // del garbage above first string with airline
            if (preg_match("/^(\s*.+?)\n([ ]{3,}(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]{2,}[\s\S]+)/u", $root, $m)) {
                $root = $m[2];
            }
            $s = $f->addSegment();

            $header = strstr($root, 'Departure', true);
            $header = str_replace('Hazmat Info', '', $header); // fix for emails: like pdf in 6002058.eml
            $header = $this->splitCols($header, $this->colsPos($header, 3));

            if (count($header) === 4) {
                $header[2] = $this->mergeCols($header[2], $header[3]);
                unset($header[3]);
            } elseif (count($header) === 5) {
                // it-44862673.eml
                $header[3] = $this->mergeCols($header[3], $header[4]);
                $header[2] = $this->mergeCols($header[2], $header[3]);
                unset($header[3], $header[4]);
            }

            if (count($header) !== 3) {
                $this->logger->debug('other format header flight');

                return false;
            }

            $body = $this->re("#^( *Departure.+)#sm", $root);

            if (!empty($str = strstr($body, 'Check-in'))) {
                $body = $str;
            }
            $pos = $this->colsPos($this->re("#^(.+)#", $body));

            if (count($pos) !== 4) {
                $this->logger->debug('other format body flight');

                return false;
            }
            $body = $this->splitCols($body, [$pos[0], $pos[2]]);

            $confNo = trim($this->re("#Airline check-in ID\s+(.+)#", $body[1]));

            if (!empty($confNo1 = $this->re("#^([A-Z\d]{5,7})\b#", $confNo))) {
                $s->airline()
                    ->confirmation($confNo1);
            } elseif (strlen($confNo) == 12 && substr($confNo, 0, 6) === substr($confNo,
                    6)
            ) { // Airline check-in ID  QQVTNHQQVTNH
                $s->airline()
                    ->confirmation(substr($confNo, 0, 6));
            }

            $s->airline()
                ->number($this->re("#\b(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$#", trim($header[0])))
                ->name($this->re("#\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+$#", trim($header[0])));

            $operator = $this->re("#OPERATED BY +(.+)#", $root);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $s->departure()
                ->code($this->re("#\(([A-Z]{3})#", $header[1]))
                ->date(strtotime($this->normalizeDate($this->re("#Departure\s+(.+)#", $body[0]))));
            $terminal = $this->re("#Terminal\s+([-\w ]+?)\n+\s*Class#", $body[0]);

            if (empty($terminal)) {
                $terminal = $this->re("#Departure\s+([-\w ]+?)\n *terminal#", $body[0]);
            }
            $s->departure()
                ->terminal($terminal, true, true);

            $s->arrival()
                ->code($this->re("#\(([A-Z]{3})#", $header[2]))
                ->date(strtotime($this->normalizeDate($this->re("#Arrival\s+(.+)#", $body[1]))));
            $terminal = $this->re("#Terminal\s+([-\w ]+?)\n+\s*Seat#", $body[1]);

            if (empty($terminal)) {
                $terminal = $this->re("#Arrival\s+([-\w ]+?)\n *terminal#", $body[1]);
            }
            $s->arrival()
                ->terminal($terminal, true, true);

            $seats = array_values(array_filter(
                array_map("trim", explode(",", $this->re("#Seat\s+(.+)#", $body[1]))),
                function ($v) {
                    if (preg_match("#^\d{1,3}[A-Z]$#", $v)) {
                        return $v;
                    } else {
                        return false;
                    }
                }
            ));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }

            $s->extra()
                ->aircraft($this->re("#Equipment\s+(.+)#", $body[0]))
                ->duration($this->re("#Duration\s+(.+)#", $body[1]), true, true);

            if (empty($s->getDuration())) {
                if (preg_match("#Duration/Meal\s+(?:service\s+)?([^/]+)\s*/\s*([\s\S]+?)(?:\n\s*service\s+|\n\n|More flight information)#", $body[1], $m)) {
                    $s->extra()
                        ->meal(preg_replace("#\s+#", ' ', trim($m[2])), true)
                        ->duration($m[1], true, true);
                }
            }

            $class = $this->re("#Class\s+(.+)#", $body[0]);

            if (preg_match("#^\s*[A-Z]{1,2}\s*$#", $class)) {
                $s->extra()
                    ->bookingCode($class);
            } elseif (preg_match("#^\s*(.+)\s*\(\s*([A-Z]{1,2})\s*\)\s*$#", $class, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (!empty($class)) {
                $s->extra()
                    ->cabin($class);
            }

            $s->extra()->status($this->re("#^[ ]*Status[ ]+(.+?)(?:[ ]{2}|$)#m", $body[0]), false, true);

            $account = $this->re("#Frequent\s+Traveler\s+((?-i)[A-Z\d,]{5,})#i", $body[1]);
            $accs = array_filter(array_map("trim", explode(",", $account)));

            if (empty($accs)) {
                $account = $this->re("#Frequent\s+((?-i)[A-Z\d,]{5,})\s+Traveler\s+#i", $body[1]);
                $accs = array_filter(array_map("trim", explode(",", $account)));
            }

            $ticketsText = $this->re("#\n *eTicket +([\d, \-]{10,})\s*(?:\n|$)#", $root);
            $tickets += array_filter(array_map("trim", explode(",", $ticketsText)));

            if (!empty($accs)) {
                foreach ($accs as $account) {
                    if (!in_array($account, $accounts)) {
                        $accounts[] = $account;
                        $f->program()
                            ->account($account, false);
                    }
                }
            }
        }

        $tickets = array_unique(array_map('trim', array_filter($tickets)));

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        return true;
    }

    private function parseBus(array $buses, Email $email)
    {
        $b = $email->add()->bus();
        $b->general()
            ->noConfirmation()
            ->travellers($this->pax);

        foreach ($buses as $root) {
            $s = $b->addSegment();

            $header = strstr($root, 'Route or', true);

            if (!empty($str = strstr($header, 'Operator', true))) {
                $header = $str;
            }
            $header = $this->splitCols($header, $this->colsPos($header, 3));

            if (count($header) === 3) {
                $header[1] = $this->mergeCols($header[1], $header[2]);
                unset($header[2]);
            }

            if (count($header) !== 2) {
                $this->logger->debug('other format header bus');

                return false;
            }

            $body = $this->re("#^( *Route or.+)#sm", $root);
            $pos = $this->colsPos($body, 3);

            if (count($pos) !== 4) {
                $this->logger->debug('other format body bus');

                return false;
            }
            $body = $this->splitCols($body, $pos);
            $body[0] = $this->mergeCols($body[0], $body[1]);
            $body[1] = $this->mergeCols($body[2], $body[3]);
            unset($body[2]);
            unset($body[3]);

            $number = $this->re("#Route or\s+(.+)\s+Number#", $body[1]);

            if (empty($number)) {
                $number = $this->re("#Route or\s*Number\s*(.+)#", $body[1]);
            }
            $s->extra()
                ->number($number);

            if (!empty($code = $this->re("#\(([A-Z]{3})#", $header[0]))) {
                $s->departure()
                    ->code($code);
            }

            if (!empty($code = $this->re("#\(([A-Z]{3})#", $header[1]))
            ) {
                $s->arrival()
                    ->code($code);
            }

            $s->departure()
                ->name(preg_replace("#\s+#", ' ', $this->re("#(.+?)\s*(?:\([A-Z]{3}\))?$#s", $header[0])))
                ->date(strtotime($this->normalizeDate($this->re("#Departure\s*(.+)#", $body[0]))));

            $s->arrival()
                ->name(preg_replace("#\s+#", ' ', $this->re("#(.+?)\s*(?:\([A-Z]{3}\))?$#s", $header[1])))
                ->date(strtotime($this->normalizeDate($this->re("#Arrival\s*(.+)#", $body[1]))));
        }

        return true;
    }

    private function parseHotel_1(array $hotels, Email $email)
    {
        foreach ($hotels as $root) {
//            $this->logger->debug('hotel root = '."\n".print_r( $root,true));

            $h = $email->add()->hotel();

            $header = strstr($root, 'Check', true);
            $body = $this->re("#^( *Check[- ].+?)Invoice/Ticket information for#sm", $root);

            if (!$body) {
                $body = $this->re("#^( *Check[- ].+)#sm", $root);
            }

            $body = $this->re("#^(.*?\n) {0,10}Special\b#sm", $body);

            $pos = $this->colsPos($body, 3);

            if (count($pos) !== 4) {
                $this->logger->debug('other format body hotel-1');

                return false;
            }
            $body = $this->splitCols($body, $pos);
            $body[0] = $this->mergeCols($body[0], $body[1]);
            $body[1] = $this->mergeCols($body[2], $body[3]);

            unset($body[2]);
            unset($body[3]);

            $conf = $this->re("#Confirmation\s*(.+)#", $body[0]);

            if (preg_match("#\bBARD\b#", $conf)) {
                $h->general()
                    ->noConfirmation();
            } else {
                $h->general()
                    ->confirmation($conf);
            }
            $h->general()
                ->travellers($this->pax)
                ->status($this->re("#Status\s*(.+)#", $body[0]));

            $h->hotel()
                ->name($this->re("#^\s*(.+)#", $header))
                ->address($this->re("#^\s*.+\n(.+)#", $header))
                ->phone($this->re('#Phone\s*(.+)#',
                    $body[0]))
                ->fax($this->re('#Fax\s*(.+)#',
                    $body[0]), true);

            if (empty($rooms = $this->re("#No of\s*(\d+)\s*rooms#", $body[1]))) {
                $rooms = $this->re("#No of rooms\s*(\d+)#", $body[1]);
            }

            if (empty($cancel = $this->re("#Cancellation\s*(.+)\n\s*[Pp]olicy#", $root))) {
                if (empty($cancel = $this->re("#Cancellation Policy\s*(.+)#", $root))) {
                    $cancel = $this->re("#Cancellation\s*(.+?)Invoice/Ticket information#s", $root);
                }
            }

            $checkin = $this->re("#Check[- ]in\s*(.+)#", $body[0]);

            if (empty($checkin)) {
                $checkin = $this->re("#Check[- ]\s*(.+)\s*in\s*#", $body[0]);
            }
            $checkout = $this->re("#Check[- ]out\s*(.+)#", $body[1]);

            if (empty($checkout)) {
                $checkout = $this->re("#Check[- ]\s*(.+)\s*out#", $body[1]);
            }
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($checkin)))
                ->checkOut(strtotime($this->normalizeDate($checkout)))
                ->guests($this->re("#Guests\s*(\d+)#", $body[1]))
                ->rooms($rooms, true, true);
            $h->general()->cancellation(preg_replace("#\s+#", ' ', $cancel), true);

            $this->detectDeadLine($h);

            $r = $h->addRoom();
            $r->setRate($this->re('#Rate\s*(.+)#',
                $body[1]), true, true);
            $r->setType(preg_replace("#\s+#", ' ', str_replace('Rooms', '', $this->re('#Guests\s*(.+)\s*Rate#s',
                $body[1]))), true);

            $account = $this->re("#^([\w\-]{5,})$#", $this->re("#Frequent\s*([A-Z\d\-]{5,})\s* Guest ID#", $body[1]));

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }
        }

        return true;
    }

    private function parseHotel_2(array $hotels, Email $email)
    {
        foreach ($hotels as $root) {
            $h = $email->add()->hotel();

            $header = strstr($root, 'Check', true);

            $roomDesc = '';

            $body = $this->re("#^( *Check\b.+)#sm", $root);
            $body = $this->re("#^(.*?\n) {0,10}Special\b#sm", $body);

            if (preg_match("/^(?<start>[\S\s]+\n {0,10}Status.*\n(?: {40,}.*\n+)*)(?<room>(?:.*\n+)+?)(?<end>(?: {40,}|\n| *Rate)[\s\S]+)/", $body, $m)) {
                $body = $m['start'] . $m['end'];
                $roomDesc = $m['room'];
            }

            $pos = $this->colsPos($body, 3);

            if (count($pos) !== 4) {
                $pos = $this->colsPos($this->re("/^((?:.*\n+){6})/", $body), 3);
            }

            if (count($pos) !== 4) {
                $this->logger->debug('other format body hotel 2');

                return false;
            }
            $body = $this->splitCols($body, $pos);
            $body[0] = $this->mergeCols($body[0], $body[1]);
            $body[1] = $this->mergeCols($body[2], $body[3]);
            unset($body[2]);
            unset($body[3]);

            $conf = $this->re("#Reference\s*(.+)#", $body[0]);
            $h->general()
                ->confirmation($conf)
                ->travellers($this->pax)
                ->status($this->re("#Status\s*(.+)#", $body[0]));

            $h->hotel()
                ->name($this->re("#^\s*(.+)#", $header))
                ->address($this->re("#^\s*.+\n(.+)#", $header))
                ->phone($this->re('#Telephone\s*(.+)#',
                    $body[0]))
                ->fax($this->re('#Fax\s*(.+)#',
                    $body[1]), true);

            if (empty($rooms = $this->re("#No of\s*(\d+)\s*rooms#", $body[0]))) {
                $rooms = $this->re("#No of rooms\s*(\d+)#", $body[0]);
            }

            if (empty($guests = $this->re("#No. of\s*(\d+)\s*Guests#", $body[1]))) {
                $guests = $this->re("#No. of\s*Guests\s*(\d+)#", $body[1]);
            }

            if (empty($cancel = $this->re("#Cancellation\s*(.+)\s*Policy#", $root))) {
                if (empty($cancel = $this->re("#Cancellation Policy\s*(.+)#", $root))) {
                    if (empty($cancel = $this->re("#Cancellation\s*(.+?)Invoice/Ticket information#s", $root))) {
                        $cancel = $this->re("#Remarks\s*(Cancel.+?)(\n\n|$)#si", $root);
                    }
                }
            }

            $checkin = $this->re("#Check in\s*(.+)#", $body[0]);

            if (empty($checkin)) {
                $checkin = $this->re("#Check\s*(.+)\s*in\s*#", $body[0]);
            }
            $checkout = $this->re("#Check out\s*(.+)#", $body[1]);

            if (empty($checkout)) {
                $checkout = $this->re("#Check\s*(.+)\s*out#", $body[1]);
            }
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($checkin)))
                ->checkOut(strtotime($this->normalizeDate($checkout)))
                ->guests($guests)
                ->rooms($rooms, true, true);
            $h->general()->cancellation(preg_replace("#\s+#", ' ', $cancel), true);

            $this->detectDeadLine($h);

            $r = $h->addRoom();
            $r->setRate($this->re('#Rate\s*(.+)#',
                $body[0]), true, true);
            $r->setType(preg_replace("#\s+#", ' ', str_replace('Room', '', $roomDesc)), true);

            $account = $this->re("#^([\w\-]{5,})$#", $this->re("#(?:Frequent|Freq\.)\s*([A-Z\d\-]{5,})\b\s*(?:Guest ID|guest ID)#", $body[1]));

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#UP TO (\d+ DAYS?) BEFORE ARRIVAL#ui", $cancellationText, $m)
            || preg_match("#CANCEL BEFORE (\d+\s*DAYS) PRIOR TO DAY OF ARRIVAL TO AVOID PENALTY#ui", $cancellationText, $m)
            || preg_match("#CANCEL (\d+ HOURS?) PRIOR TO ARRIVAL TO AVOID PENALTY#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1]);

            return;
        }
    }

    private function parseCar(array $cars, Email $email)
    {
        foreach ($cars as $root) {
//            $this->logger->debug('Car root = '.print_r( $root,true));

            $r = $email->add()->rental();

            $header = strstr($root, 'Pick up', true);

            if (preg_match("#(.+?)^ *\w{3},?\s+\w{3}\s+#ms", $header, $m)) {
                $header = $m[1];
                $body = $this->re("#^( *\w{3},?\s+\w{3}\s+.+)#ms", $root);
            } else {
                $body = $this->re("#^( *Pick up.+)#sm", $root);
            }
            $rows = explode("\n", $body);
            $pos = [];

            foreach ($rows as $row) {
                if (($p1 = strpos($row, 'Pick up')) !== false) {
                    $pos[0] = $p1;
                }

                if (($p2 = strpos($row, 'Drop off')) !== false) {
                    $pos[1] = $p2;
                }

                if (count($pos) === 2) {
                    break;
                }
            }

            if (count($pos) !== 2) {
                $this->logger->debug('other format body rental');

                return false;
            }
            $body = $this->splitCols($body, $pos);

            $bodyRows = explode("\n\n", $body[0]);

            foreach ($bodyRows as $i => $bodyRow) {
                if (strpos($bodyRow, 'Pick up') !== false) {
                    $r->pickup()
                        ->date(strtotime($this->normalizeDate(preg_replace("#\s+#", ' ',
                            str_replace('Pick up', ' ', $bodyRow)))));

                    continue;
                }

                if (strpos($bodyRow, 'Rental') !== false && strpos($bodyRow, 'location') !== false) {
                    $r->pickup()
                        ->location(preg_replace("#\s+#", ' ',
                            str_replace('Rental', ' ', str_replace('location', ' ', $bodyRow))));

                    continue;
                }

                if (strpos($bodyRow, 'Type') === 0) {
                    $r->car()
                        ->type($this->re("#Type\s*(.+)#", $bodyRow));

                    continue;
                }

                if (strpos($bodyRow, 'Confirmation') === 0) {
                    $r->general()
                        ->confirmation($this->re("#Confirmation\s*([A-Z\d]{5,})\b#", $bodyRow))
                        ->travellers($this->pax);

                    continue;
                }

                if (strpos($bodyRow, 'Approx.') !== false && strpos($bodyRow, 'Total') !== false) {
                    $sum = preg_replace("#\s+#", ' ', str_replace('Approx.', ' ', str_replace('Total', ' ', $bodyRow)));
                    $tot = $this->getTotalCurrency($sum);

                    if ($tot['Total'] !== '') {
                        $r->price()
                            ->total($tot['Total'])
                            ->currency($tot['Currency']);
                    }

                    continue;
                }
            }

            $bodyRows = explode("\n\n", $body[1]);

            foreach ($bodyRows as $i => $bodyRow) {
                if (strpos($bodyRow, 'Drop off') !== false && $i == 0) {
                    $r->dropoff()
                        ->date(strtotime($this->normalizeDate(preg_replace("#\s+#", ' ',
                            str_replace('Drop off', ' ', $bodyRow)))));

                    continue;
                }

                if (strpos($bodyRow, 'Phone') === 0) {
                    $phone = $this->re("#Phone\s*(.+)#", $bodyRow);

                    if (!empty($phone)) {
                        $r->pickup()
                            ->phone($phone);
                        $r->dropoff()
                            ->phone($phone);
                    }

                    continue;
                }

                if (strpos($bodyRow, 'Return') !== false && strpos($bodyRow, 'location') !== false) {
                    if (!empty($location = trim(preg_replace("#\s+#", ' ',
                        str_replace('Return', ' ', str_replace('location', ' ', $bodyRow)))))
                    ) {
                        $r->dropoff()
                            ->location($location);
                    } else {
                        $r->dropoff()
                            ->noLocation();
                    }

                    continue;
                }

                if (strpos($bodyRow, 'Frequent') !== false && strpos($bodyRow, 'Renter ID') !== false) {
                    if (!empty($account = trim(preg_replace("#\s+#", ' ',
                        str_replace('@', '', str_replace('Frequent', ' ', str_replace('Renter ID', ' ', $bodyRow))))))
                    ) {
                        if (preg_match("#^X{4,}.*$#", $account, $m)) {
                            $r->program()
                                ->account($account, true);
                        } else {
                            $r->program()
                                ->account($account, false);
                        }
                    }

                    continue;
                }
            }

            if (empty($r->getDropOffLocation())) {
                $r->dropoff()->noLocation();
            }

            $keyword = $this->re("#(.+)\n#",
                trim($header));
            $rentalProvider = $this->getRentalProviderByKeyword($keyword ?? '');

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->program()->keyword($keyword);
            }
        }

        return true;
    }

    private function parseTrain(array $trains, Email $email)
    {
        $accounts = [];

        foreach ($trains as $root) {
            $t = $email->add()->train();
            $t->general()
                ->travellers($this->pax);
            $s = $t->addSegment();

            $header = strstr($root, 'Train', true);

            if (!empty($str = strstr($header, 'Carrier', true))) {
                $header = $str;
            }
            $header = $this->splitCols($header, $this->colsPos($header, 3));

            if (count($header) === 3) {
                $header[1] = $this->mergeCols($header[1], $header[2]);
                unset($header[2]);
            }

            if (count($header) !== 2) {
                $this->logger->debug('other format header train');

                return false;
            }

            $body = $this->re("#^( *Train.+)#sm", $root);
            $pos = $this->colsPos($body, 3);

            if (count($pos) !== 4) {
                $this->logger->debug('other format body train');

                return false;
            }
            $body = $this->splitCols($body, $pos);
            $body[0] = $this->mergeCols($body[0], $body[1]);
            $body[1] = $this->mergeCols($body[2], $body[3]);
            unset($body[2]);
            unset($body[3]);

            $confNo = $this->re("#Confirmation *([A-Z\d\-]{5,})#", $body[1]);

            if (!empty($confNo)) {
                $t->general()
                    ->confirmation($confNo);
            } else {
                $t->general()
                    ->noConfirmation();
            }
            $status = $this->re("#Status *(.+)#", $body[1]);

            if (!empty($status)) {
                $t->general()
                    ->status($status);
            }
            $account = $this->re("#Frequent Traveler\s*([A-Z\d\-]{5,})#", $body[0]);

            if (empty($account)) {
                $account = $this->re("#Frequent\s*([A-Z\d\-]{5,})\s*Traveler#", $body[0]);
            }

            if (!empty($account) && !in_array($account, $accounts)) {
                $t->program()
                    ->account($account, false);
            }

            $s->extra()
                ->status($this->re("#Status\s*(.+)#", $root));

            $number = $this->re("#Train number\s*(.+)#", $body[1]);

            if (empty($number)) {
                $number = $this->re("#Train\s*(.+)\s*number\s*#", $body[1]);
            }
            $s->extra()
                ->cabin($this->re("#Class\s*(.+)#", $body[0]))
                ->duration($this->re("#Duration\s*(.+)#", $body[0]))
                ->service($this->re("#Carrier\s*(.+)#", $body[0]))
                ->seat($this->re("#Seat\s*(.+)#", $body[1]), true, true)
                ->number($number);

            $s->departure()
                ->name(preg_replace("#\s+#", ' ', $header[0]))
                ->date(strtotime($this->normalizeDate($this->re("#Departure\s*(.+)#", $body[0]))));

            $s->arrival()
                ->name(preg_replace("#\s+#", ' ', $header[1]))
                ->date(strtotime($this->normalizeDate($this->re("#Arrival\s*(.+)#", $body[1]))));
        }

        return true;
    }

    private function parseLimo(array $limos, Email $email)
    {
        foreach ($limos as $root) {
            $r = $email->add()->rental();
            $header = strstr($root, 'Date', true);
            $header = $this->splitCols($header, $this->colsPos($header, 3));

            if (count($header) === 3) {
                $header[1] = $this->mergeCols($header[1], $header[2]);
                unset($header[2]);
            }

            if (count($header) !== 2) {
                $this->logger->debug('other format header limo');

                return false;
            }

            $body = $this->re("#^( *Date.+)#sm", $root);

            $r->general()
                ->confirmation($this->re("#Confirmation\s+([A-Z\d\-]{5,})#", $body))
                ->travellers($this->pax);

            $r->pickup()
                ->date(strtotime($this->normalizeDate($this->re("#Date\s+(.+?)\s{3,}#", $body))))
                ->location(preg_replace("#\s+#", ' ', $header[0]));

            $r->dropoff()
                ->noDate()
                ->location(preg_replace("#\s+#", ' ', $header[1]));

            $keyword = $this->re("#Company\s+(.+)#", $body);
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->program()->keyword($keyword);
            }
            $r->program()->phone($this->re("#Phone\s+(.+)#", $body));

            $tot = $this->getTotalCurrency($this->re("#Rate\s+(.+?)\s{3,}#", $body));

            if ($tot['Total'] !== '') {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $r->car()
                ->type($this->re("#VEHICLE-(.+)#", $body));
        }

        return true;
    }

    private function parseHtml(string $text, Email $email)
    {
        $text = str_replace([' ', '&#160;', '&nbsp;'], ' ', $text);

        if (!empty($str = strstr($text, "General Remarks", true))) {
            $text = $str;
        }
        $this->info = $this->findСutSection($text, 'icket information', null);

        if (!empty($str = strstr($text, "Invoice/ticket information", true))) {
            $text = $str;
        }

        if (!empty($str = strstr($text, "Invoice/Ticket information", true))) {
            $text = $str;
        }

        $node = $this->findСutSection($text, 'Traveler Name', 'Complete details for your trip are below');

        if (empty($node)) {
            $node = $this->findСutSection($text, 'Traveler name', 'Complete details for your trip are below');
        }

        if (preg_match_all("#^ *([A-Z ]+?)(?:\s{3,}|\n)#m", $node, $m)) {
            $this->pax = array_values(array_filter(array_map("trim", $m[1])));
        }

        if (empty($this->pax)) {
            $this->pax[] = $this->re("#Travel arrangements for\s+(.+?)\s*(?:{$this->opt($this->t('Agency reference'))}|\n)#", $text);
        }

        $email->ota()->confirmation($this->re("#{$this->opt($this->t('Agency reference'))}:\s*([A-Z\d]{5,})#i", $text));

        $segmentsText = "\n\n" . preg_replace("/[\s\S]+\n *From \/ To.+\n(?:.*\n+){1,4}?( {0,3}(?:Flight|Hotel|Tour|Limo|Car|Other|Bus) .*\n{1,2}(?:  {5,}.*\n{1,2}){0,5})+/", "\n", $text);

        //not simple selector
        $delimiter = "(?:"
            . "\n {0,10}(?:Departure|Date|Check-|Check[- ]in|Pick up|Operator|Route or|Train)\s+[A-Z].*\d(?:.*\n+){2}"
            . "|\n[ ]*Tour\s+Date\s+[A-Z].*\n"
            . "|\n[ ]*Other\s+Date\s+[A-Z].*\n"
            . ")";
//        $this->logger->debug('$delimiter = '.print_r( $delimiter,true));

        $arr = $this->splitter("#({$delimiter})#u", $segmentsText, false);
//        $this->logger->debug('$arr = '.print_r( $arr,true));

        $flights = [];
        $buses = [];
        $trains = [];
        $hotels = [];
        $hotels_2 = [];
        $cars = [];
        $limos = [];

        $next = '';

        foreach ($arr as $i => $value) {
            if (!preg_match("#^\s*(?:Tour|Other)\n#", $value)) {
                $value = $next . $value;
                $value = "\n" . $value;
            }
            $next = '';

            $value = preg_replace("/(\n.+)$/", '$1' . "\n", $value);

            if ((preg_match("/([\s\S]+?\n)((?:.+\n+){5})$/", $value, $m1) || $m1 = [1 => '', 2 => $value])
            && preg_match("/([\s\S]+?)\n((?:[ ]{5,}.*\n{1,2}){1,5})$/", $m1[2], $m2)) {
                $next = $m2[2];
                $value = $m1[1] . $m2[1];
            }

            if ($i === 0) {
                continue;
            }

            if (preg_match("#^\s*(?:Tour|Other)#", $value)) {
                continue;
            }

            if (strpos($value, 'Departure') !== false && strpos($value, 'Route or') === false && strpos($value,
                    'Train') === false
            ) {
                $flights[] = $value;
            }

            if (strpos($value, 'Departure') !== false && strpos($value, 'Route or') !== false) {
                $buses[] = $value;
            }

            if (strpos($value, 'Departure') !== false && strpos($value, 'Train') !== false) {
                $trains[] = $value;
            }

            if (strpos($value, 'Check in') !== false || strpos($value, 'Check-') !== false) {
                if ($this->re("/\n {0,10}(Rate)\s+/", $value)) {
                    $hotels_2[] = $value;
                } elseif ($this->re("/\n.+ {3,}(Rate)\s+/", $value)) {
                    $hotels[] = $value;
                }
            }

            if (strpos($value, 'Pick up') !== false) {
                $cars[] = $value;
            }

            if (strpos($value, 'Date') !== false) {
                $limos[] = $value;
            }
        }

        // FLIGHT
        if (!empty($flights)) {
            $this->parseFlight($flights, $email);
        }
        // BUSES
        if (!empty($buses)) {
            $this->parseBus($buses, $email);
        }
        // TRAIN
        if (!empty($trains)) {
            $this->parseTrain($trains, $email);
        }
        // HOTEL
        if (!empty($hotels)) {
            $this->parseHotel_1($hotels, $email);
        }
        // HOTEL-2
        if (!empty($hotels_2)) {
            $this->parseHotel_2($hotels_2, $email);
        }
        // CAR
        if (!empty($cars)) {
            $this->parseCar($cars, $email);
        }
        // LIMO
        if (!empty($limos)) {
            $this->parseLimo($limos, $email);
        }
    }

    private function detectBody($text)
    {
        $foundCompany = false;

        foreach ($this->detectCompany as $provider => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($text, $dCompany) !== false) {
                    $foundCompany = true;

                    if (empty($this->providerCode)) {
                        $this->providerCode = $provider;
                    }

                    break 2;
                }
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        return $this->assignLang($text);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('normalizeDate = '.print_r( $str,true));
        $in = [
            "#^\w+\s+(\w+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+\s+[AP]M)$#",
            "#^\s*\w+\s+(\w+)\s+(\d+),\s+(\d{4})\s+Time\s+(\d+:\d+\s+[AP]M)\s*$#",
            "#^\w+\s+(\w+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

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

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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
        $pos = $this->rowColsPos($this->inOneRow($table));

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

    private function assignLang($text)
    {
        foreach ($this->detectBody as $lang=>$detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
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

    private function splitter($regular, $text, $deleteFirst = true)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($deleteFirst === false && !empty($array[0])) {
            $result[] = $array[0];
        }
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function mergeCols($col1, $col2)
    {
        $rows1 = explode("\n", $col1);
        $rows2 = explode("\n", $col2);
        $newRows = [];

        foreach ($rows1 as $i => $row) {
            if (isset($rows2[$i])) {
                $newRows[] = $row . $rows2[$i];
            } else {
                $newRows[] = $row;
            }
        }

        if (($i = count($rows1)) > count($rows2)) {
            for ($j = $i; $j < count($rows2); $j++) {
                $newRows[] = $rows2[$j];
            }
        }

        return implode("\n", $newRows);
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
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
}
