<?php

namespace AwardWallet\Engine\avantid\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryForPdf extends \TAccountChecker
{
    public $mailFiles = "avantid/it-47766801.eml, avantid/it-48000408.eml, avantid/it-48000841.eml, avantid/it-48096650.eml, avantid/it-50548539.eml, avantid/it-50594028.eml, avantid/it-657086327.eml";

    public $reFrom = ["@avantidestinations.com"];
    public $reBody = [
        'en' => ['DAY BY DAY'],
    ];
    public $reSubject = [
        'Itinerary for:',
    ];
    public $pdfNamePattern = ".*pdf";
    public $lang = '';
    public static $dict = [
        'en' => [
            'Depart from home town' => 'Depart from home town',
            'AGENCY:'               => 'AGENCY:',
            'BOOKING NUMBER:'       => ['BOOKING NUMBER:', 'PROPOSAL NUMBER:'],
            'Adults'                => ['Adults', 'Adult'],
            'Nights'                => ['Nights', 'Night'],
            'typesReservations'     => ['FLIGHTS', 'HOTELS', 'EXPERIENCES', 'GROUND TRANSPORTATION'],
            'endSegments'           => ['Airfare subject', 'Baggage:'],
            'Train#'                => ['Train#', 'TRAIN#'],
            'from'                  => ['from', 'From:'],
            'to'                    => ['to', 'To:'],
            'departing'             => ['departing', 'Depart:'],
            'arriving'              => ['arriving', 'Arrive:'],
        ],
    ];
    private $keywordProv = ['AvantiDestinations.com', 'Avanti Destinations'];
    private $rentalProviders = [
        'jumbo'        => ['Jumbo Car'],
        'rentacar'     => ['Enterprise'],
        'dollar'       => ['Dollar'],
        'hertz'        => ['Hertz'],
        'sixt'         => ['Sixt'],
        'alamo'        => ['Alamo'],
        'perfectdrive' => ['Budget'],
        'payless'      => ['Payless'],
    ];
    private $otaConfNo;
    private $status;
    private $onePdf = true;
    private $cost;
    private $taxes;
    private $total;
    private $agency;
    private $phone;
    private $daysTrip;
    private $travellers;
    private $trainCollected;
    private $rentalCollected;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($email, $text);
                    }
                }
            }
        }

        if ($this->onePdf === true && isset($this->cost, $this->taxes, $this->total)) {
            $email->price()
                ->cost($this->cost)
                ->tax($this->taxes)
                ->total($this->total);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || $this->stripos($headers["subject"], $this->keywordProv))
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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
        $types = 4; // flights, trains, hotels, rentals
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function delGarbage(&$text)
    {
        $text = preg_replace("/\n\s*{$this->t('Prepared by Avanti Destinations')}.+?[ ]{3,}Page:[ ]*\d+(?:\n|$)/", "\n\n",
            $text);
    }

    private function parseEmailPdf(Email $email, string $textPdf)
    {
        $topInfo = strstr($textPdf, $this->t('DAY BY DAY'), true);

        if (empty($topInfo)) {
            $this->logger->debug('check format pdf');

            return false;
        }
        $this->delGarbage($topInfo);
        $mainInfo = strstr($textPdf, $this->t('DAY BY DAY'));
        $this->delGarbage($mainInfo);

        $this->otaConfNo = null;

        if (preg_match("/({$this->opt($this->t('BOOKING NUMBER:'))})[ ]*(\w+)\n/", $topInfo, $m)) {
            $this->otaConfNo = [trim($m[1], ":"), $m[2]];

            if ($m[1] == $this->t('PROPOSAL NUMBER:')
                && $this->stripos($topInfo, $this->t('for planning purposes only'))
            ) {
                $this->status = 'planning';
            }
        } else {
            $this->logger->debug('other format ota info');

            return false;
        }
        $this->travellers = array_filter(array_map([$this, "nice"], explode("\n",
            $this->re("/{$this->opt($this->t('BOOKING NUMBER:'))}[^\n]*\s+(.+?)(?:\n\n|{$this->t('AGENCY:')})/s",
                $topInfo))));

        if (count($email->getItineraries()) > 0) {
            $this->onePdf = false;
        }

        $this->agency = $this->phone = $this->daysTrip = null;

        $this->cost = PriceHelper::cost($this->re("/\n[ ]*{$this->t('PRICE:')}[ ]+(.+)/", $topInfo));
        $this->taxes = PriceHelper::cost($this->re("/\n[ ]*{$this->t('TAXES:')}[ ]+(.+)/", $topInfo));
        $this->total = PriceHelper::cost($this->re("/\n[ ]*{$this->t('TOTAL PRICE:')}[ ]+(.+)/", $topInfo));
        $this->agency = $this->re("/\n[ ]*{$this->t('AGENCY:')}[ ]+(.+?)[ ]*\|/", $topInfo);
        $this->phone = trim($this->re("/\n[ ]*{$this->t('PHONE:')}[ ]+(.+?)[ ]*\|/", $topInfo));

        $types = $this->splitter("/\n[ ]{3,}({$this->opt($this->t('typesReservations'))}[ ]*\n)/", $topInfo);

        // get details day by day: for parse trains or collect added information for others reservations
        $dayByDay = $this->splitter("/\n([ ]*\w{3} \d+ \w{3}, \d{4}\n)/", $mainInfo);

        foreach ($dayByDay as $text) {
            if (preg_match("/^[ ]*(\w{3} \d+ \w{3}, \d{4})\n(.+)/s", $text, $m)) {
                $date = $this->normalizeDate($m[1]);

                if (!isset($this->daysTrip[$date])) {
                    $this->daysTrip[$date] = $m[2];
                    $this->trainCollected[$date] = false;
                    $this->rentalCollected[$date] = false;
                } else {
                    $this->logger->alert('check format. a few days with one date on block "DAY BY DAY" - ' . $m[1]);

                    return false;
                }
            }
        }

        // transfers and tours - skip. not enough info or not stable format
        foreach ($types as $text) {
            $type = trim($this->re("/^(.+)\n/", $text));

            if (in_array($type, (array) $this->t('FLIGHTS'))) {
                if ($this->parseFlights($email, $text) === false) {
                    return false;
                }

                continue;
            }

            if (in_array($type, (array) $this->t('EXPERIENCES'))) {
                continue;
            }

            if (in_array($type, (array) $this->t('HOTELS'))) {
                if ($this->parseHotels($email, $text) === false) {
                    return false;
                }

                continue;
            }

            if (in_array($type, (array) $this->t('GROUND TRANSPORTATION'))) {
                if ($this->parseTransportation($email, $text) === false) {
                    return false;
                }

                continue;
            }
        }

        return true;
    }

    private function parseTransportation(Email $email, string $textPdf)
    {
        $table = $this->re("/^[^\n]+\n\s*?(.+)/s", $textPdf);
        $pos = $this->colsPos($this->re("/(.+)/", $table));

        if (count($pos) > 2 || count($pos) === 0) {
            $this->logger->alert("check format ground transportation");

            return false;
        }
        $table = $this->splitCols($table, $pos);
        $table = implode("\n", $table);

        $reservations = $this->splitter("/\n\n(\w{3} \d+ \w{3}, \d{4}\n)/", "ctrlStr\n\n" . $table);

        foreach ($reservations as $reservation) {
            if (preg_match("/^(\w{3} \d+ \w{3}, \d{4})\n(.+)/", $reservation, $m)) {
                $date = $this->normalizeDate($m[1]);

                if (!$date) {
                    $this->logger->alert("check format date ground transportation");

                    return false;
                }

                if (preg_match("/^\s*{$this->opt($this->t('Train Transportation'))}/", $m[2])) {
                    if (!isset($this->trainCollected[$date])) {
                        $this->logger->alert("can't find day-flag on train collected");

                        return false;
                    }

                    if ($this->trainCollected[$date]) {
                        continue;
                    }

                    if (!$this->parseTrains($email, $reservation, $date)) {
                        return false;
                    }
                    $this->trainCollected[$date] = true;

                    continue;
                } elseif (preg_match("/^\s*{$this->opt($this->t('Car Rental'))}/", $m[2])) {
                    if (!isset($this->rentalCollected[$date])) {
                        $this->logger->alert("can't find day-flag on rental collected");

                        return false;
                    }

                    if ($this->rentalCollected[$date]) {
                        continue;
                    }

                    if (!$this->parseRentals($email, $reservation, $date)) {
                        return false;
                    }
                    $this->rentalCollected[$date] = true;

                    continue;
                } elseif (preg_match("/^\s*{$this->opt($this->t('Private transfer'))}/", $m[2])) {
                    // skip transfer
                    continue;
                }
            }
        }

        return true;
    }

    private function parseFlights(Email $email, string $textPdf)
    {
        $r = $email->add()->flight();
        $r->ota()->confirmation($this->otaConfNo[1], $this->otaConfNo[0]);

        if (!empty($this->phone)) {
            $r->ota()->phone($this->phone, $this->agency);
        }

        $r->general()->noConfirmation();

        $travellers = array_filter(array_map([$this, "nice"], explode(',',
            $this->re("/\n\s*{$this->t('Air Transportation for:')}(.+?)(?:\n\n|{$this->t('Please contact the Carrier for')})/s",
                $textPdf))));

        if (!empty($travellers)) {
            $r->general()->travellers($travellers);
        } else {
            $r->general()->travellers($this->travellers);
        }

        if (isset($this->status)) {
            $r->general()->status($this->status);
        }

        $daysFlight = $this->splitter("/\n[ ]{3,}(\w{3} \d+ \w{3}, \d{4}[ ]*\n)/", $textPdf);

        foreach ($daysFlight as $text) {
            if (preg_match("/^(\w{3} \d+ \w{3}, \d{4})[ ]*\n(.+)/s", $text, $m)) {
                $date = $this->normalizeDate($m[1]);
                $segments = preg_split("/\n\n/", $m[2]);

                foreach ($segments as $segment) {
                    if (strpos($segment, $this->t('Depart:')) === false) {
                        continue;
                    }
                    $s = $r->addSegment();

                    if (preg_match("/(.+?)\n\s*{$this->opt($this->t('endSegments'))}/s", $segment, $m)) {
                        $segment = $m[1];
                    }
                    $pos = $this->colsPos($segment);

                    if (count($pos) < 3) {
                        $this->logger->alert('other format flight-segment');

                        return false;
                    }
                    $pos = array_slice($pos, 0, 3);
                    $table = $this->splitCols($segment, $pos);
                    $s->airline()
                        ->name(trim($table[0]))
                        ->number($this->re("/{$this->t('Flight:')}[ ](\d+)$/m", $table[2]))
                        ->operator($this->re("/{$this->t('OPERATED BY')}[ ](.+)/", $table[2]), false, true);
                    $s->departure()->date(strtotime($this->normalizeTime($this->re("/{$this->t('Depart:')}[ ]+(\w+)/",
                        $table[2])), $date));
                    $s->arrival()->date(strtotime($this->normalizeTime($this->re("/{$this->t('Arrive:')}[ ]+(\w+)/",
                        $table[2])), $date));

                    if (preg_match("/^(.+)\s*\(([A-Z]{3})\)\s+(.+)\s*\(([A-Z]{3})\)\s*$/s", $table[1], $m)) {
                        // fe: it-47766801.eml depname=arrname -> so don't collect names
                        $s->departure()
//                            ->name($m[1])
                            ->code($m[2]);
                        $s->arrival()
//                            ->name($m[3])
                            ->code($m[4]);
                    }
                }
            }
        }

        return true;
    }

    private function parseHotels(Email $email, string $textPdf)
    {
        $hotels = $this->splitter("/\n([ ]*\w{3} \d+ \w{3}, \d{4})/", $textPdf);

        if (count($hotels) === 0) {
            $this->logger->debug("check format date in hotel-reservations!!!");

            return false;
        }

        foreach ($hotels as $hotel) {
            $r = $email->add()->hotel();
            $r->ota()->confirmation($this->otaConfNo[1], $this->otaConfNo[0]);

            if (!empty($this->phone)) {
                $r->ota()->phone($this->phone, $this->agency);
            }

            $travellers = array_filter(array_map("trim",
                explode(',', $this->re("/\n\s*{$this->t('for:')}(.+?)\n\n/s", $hotel))));

            if (!empty($travellers)) {
                $r->general()->travellers($travellers);
            } else {
                $r->general()->travellers($this->travellers);
            }

            $r->general()
                ->noConfirmation();

            if (isset($this->status)) {
                $r->general()->status($this->status);
            }

            if (preg_match("/^[ ]*(\w{3} \d+ \w{3}, \d{4})[ ]+(\d+)[ ]{$this->opt($this->t('Nights'))}\s+(.+)\s+{$this->t('for:')}/s",
                $hotel, $m)) {
                $date = $this->normalizeDate($m[1]);
                $r->booked()
                    ->checkIn($date)
                    ->checkOut(strtotime("+ {$m[2]} days", $date));
                $hotelName = $this->nice($m[3]);
                $parts = explode(" - ", $hotelName);

                if (count($parts) === 2) {
                    $r->hotel()->name(trim($parts[1]));
                } else {
                    $r->hotel()->name($hotelName);
                }

                if (preg_match("/\s+{$this->t('for:')}.+?\n\n\s*(\d+)\-([^\n]+)\n\s*{$this->t('Address:')}\s+(.+)/s",
                    $hotel, $m)) {
                    $r->booked()
                        ->rooms($m[1]);
                    $room = $r->addRoom();
                    $room->setType($m[2]);
                    $address = $this->nice($m[3]);
                    $r->hotel()->address(trim($address));
                }
            } elseif (preg_match("/^[ ]*(\w{3} \d+ \w{3}, \d{4})[ ]+(\d+)[ ]{$this->opt($this->t('Nights'))}\s+(.+)\s+{$this->t('Address:')}\s+(.+)/s",
                $hotel, $m)) {
                $date = $this->normalizeDate($m[1]);
                $r->booked()
                    ->checkIn($date)
                    ->checkOut(strtotime("+ {$m[2]} days", $date));
                $hotelName = $this->nice($m[3]);
                $parts = explode(" - ", $hotelName);

                if (count($parts) === 2) {
                    $r->hotel()->name(trim($parts[1]));
                } else {
                    $r->hotel()->name($hotelName);
                }
                $address = $this->nice($m[4]);
                $r->hotel()->address(trim($address));
            }
        }

        return true;
    }

    private function parseRentals(Email $email, string $textPdf, int $date)
    {
        if (!isset($this->daysTrip[$date])) {
            $this->logger->alert("rental trip. cant't find dayTrip[{$date}]");

            return false;
        }

        if (preg_match_all("/(?:^|\n)\n((?:[^\n]+\n)+?{$this->opt($this->t('Pick-up:'))}.+?)\n\n/s",
            $this->daysTrip[$date], $v)) {
            $texts = $v[1];

            foreach ($texts as $text) {
                $r = $email->add()->rental();
                $r->ota()->confirmation($this->otaConfNo[1], $this->otaConfNo[0]);

                if (!empty($this->phone)) {
                    $r->ota()->phone($this->phone, $this->agency);
                }

                $traveller = $this->re("/{$this->t('Driver:')}[ ]*(.+)?:\d+/", $text);
                $r->general()
                    ->noConfirmation()
                    ->traveller($traveller);

                if (isset($this->status)) {
                    $r->general()->status($this->status);
                }

                $node = $this->re("/^\s*(.+)/", $text);

                if (preg_match("/(.+?)\s+{$this->t('Rent A Car')}\s+[A-Z]+\s+\-\s+[A-Z]\s+(.+)/", $node, $m)) {
                    $rentalCompany = $m[1];
                    $r->car()
                        ->model($m[2]);
                } elseif (preg_match("/(.+?)\s+{$this->t('Rent A Car')}\s+(.+)/", $node, $m)) {
                    $rentalCompany = $m[1];
                    $r->car()
                        ->model($m[2]);
                } elseif (preg_match("/(.+?)\s+\-\s+(.+? {$this->t('OR SIMILAR')})$/i", $node, $m)) {
                    $rentalCompany = $m[1];
                    $r->car()
                        ->model($m[2]);
                } else {
                    $this->logger->debug('other format rental');

                    return false;
                }
                // provider
                if (!empty($rentalCompany)) {
                    $r->extra()->company($rentalCompany);

                    foreach ($this->rentalProviders as $code => $detects) {
                        foreach ($detects as $detect) {
                            if (false !== stripos($rentalCompany, $detect)) {
                                $r->program()->code($code);
                                $flagCode = true;

                                break 2;
                            }
                        }
                    }

                    if (!isset($flagCode)) {
                        $r->program()->keyword($rentalCompany);
                    }
                }

                // pick-up
                $node = $this->re("/{$this->t('Pick-up:')}[ ]*(.+)/", $text);

                if (preg_match("#(.+) \/\s*(.+?)(?:{$this->opt($this->t('Phone:'))}\s+(.+)|$)#", $node, $m)) {
                    $r->pickup()
                        ->date($this->normalizeDate($m[1]))
                        ->location($m[2])
                        ->phone($m[3], false, true);
                }

                //drop-off
                $node = $this->re("/{$this->t('Drop-off:')}[ ]*(.+)/", $text);

                if (preg_match("#(.+) \/\s*(.+?)(?:{$this->opt($this->t('Phone:'))}\s+(.+)|$)#", $node, $m)) {
                    $r->dropoff()
                        ->date($this->normalizeDate($m[1]))
                        ->location($m[2])
                        ->phone($m[3], false, true);
                }
            }
        }

        return true;
    }

    private function parseTrains(Email $email, string $textPdf, int $date)
    {
        if (!isset($this->daysTrip[$date])) {
            $this->logger->alert("train trip. cant't find dayTrip[{$date}]");

            return false;
        }

        $texts = $this->splitter("/^[ ]*({$this->t('Train Transportation')})/m", $this->daysTrip[$date]);

        foreach ($texts as $text) {
            $r = $email->add()->train();
            $r->ota()
                ->confirmation($this->otaConfNo[1], $this->otaConfNo[0])
                ->phone($this->phone, $this->agency);

            $r->general()->noConfirmation();

            $travellers = array_filter(array_map([$this, "nice"],
                explode(',',
                    $this->re("/\n\s*{$this->t('for:')}(.+?)\n(?:\n| {0,5}(?:SBB )?{$this->opt($this->t('Train#'))})/s", $text))));

            if (!empty($travellers)) {
                $r->general()->travellers($travellers);
            } else {
                $r->general()->travellers($this->travellers);
            }

            if (isset($this->status)) {
                $r->general()->status($this->status);
            }
            $s = $r->addSegment();

            if (preg_match("/{$this->opt($this->t('Train#'))}\s*(.+)\s+{$this->opt($this->t('from'))}\s+(.+)\s+{$this->opt($this->t('to'))}\s+(.+?)\s+{$this->opt($this->t('departing'))}\s+(\d+:\d+(?:\s*[ap]m)?)\s+{$this->opt($this->t('arriving'))}\s+(\d+:\d+(?:\s*[ap]m)?)\s+(.+)/si",
                $text, $m)) {
                $s->extra()->number($m[1]);
                $s->departure()
                    ->name($this->nice($m[2]))
                    ->date(strtotime($m[4], $date));
                $s->arrival()
                    ->name($this->nice($m[3]))
                    ->date(strtotime($m[5], $date));
                $node = $m[6];

                if (preg_match("#(\d+)\s*\-\s*{$this->opt($this->t('Adults'))}\s+(?:(\d+)\s*\-\s*{$this->opt($this->t('Children'))}\s+)?(?<cabin>.*\b{$this->t('Class')}\b.*)#",
                    $node, $m)
                    || preg_match("#{$this->opt($this->t('Cabin:'))}\s+(?<cabin>.+)#", $node, $m)
                ) {
                    $s->extra()->cabin($m['cabin']);
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //FRI 28 FEB, 2020
            '#^\w+ (\d+) (\w+), (\d{4})$#u',
            //29-Nov-2019 12:00
            '#^(\d+)\-(\w+)\-(\d{4}) (\d+:\d+(?:[ap]m)?)$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function normalizeTime($str)
    {
        $in = [
            //350P
            '#^(\d{1,2})(\d{2})([AP])$#',
        ];
        $out = [
            '$1:$2 $3M',
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if ($this->stripos($body, $this->keywordProv) && isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Depart from home town"], $words["BOOKING NUMBER:"])) {
                if ($this->stripos($body, $words["Depart from home town"]) && $this->stripos($body,
                        $words["BOOKING NUMBER:"])
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
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

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }
}
