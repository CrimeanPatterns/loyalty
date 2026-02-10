<?php

namespace AwardWallet\Engine\avantid\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InvoiceFor extends \TAccountChecker
{
    public $mailFiles = "avantid/it-319714981.eml, avantid/it-324995018.eml, avantid/it-35396611.eml, avantid/it-36176699.eml, avantid/it-36220176.eml, avantid/it-37920152.eml, avantid/it-46202886.eml";

    public $reFrom = ["@avantidestinations.com"];
    public $reBody = [
        'en' => ['INVOICE - Booking', 'INVOICE - Proposed Travel'],
    ];
    public $reSubject = [
        'Invoice for:',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Depart from home town' => 'Depart from home town',
            '#Pax'                  => ['#Pax', 'Travelers'],
            'INVOICE - Booking'     => ['INVOICE - Booking', 'INVOICE - Proposed Travel'],
            'nts'                   => ['nts', 'nt'],

            // Transfer
            'pickup'  => '-Pickup:',
            'dropoff' => '-Dropoff:',
        ],
    ];
    private $keywordProv = ['AvantiDestinations.com', 'Avanti Destinations'];
    private $reservationDate;
    private $otaConfNo;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $text = $parser->getHTMLBody();
        $this->parseEmail($email, $this->htmlToText($text));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.avantidestinations.com')] | //a[contains(@href,'book.avantidestinations.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || $this->stripos($headers["subject"], $this->keywordProv)
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
        $types = 3; // flights | trains | hotels
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email, string $text)
    {
        $this->reservationDate = strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(),'INVOICE - Booking')]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]/descendant::text()[starts-with(normalize-space(),'Booked:')]/following::text()[normalize-space()!=''][1]"));

        if (empty($this->reservationDate)) {
            $this->reservationDate = strtotime($this->http->FindSingleNode("//text()[contains(normalize-space(),'INVOICE - Booking')]/ancestor::*[1]/following::*[normalize-space()!=''][1]/descendant::text()[contains(normalize-space(),'ooked:')]/following::text()[normalize-space()!=''][1]"));
        }

        $this->status = $this->http->FindSingleNode("//text()[contains(normalize-space(),'INVOICE - Booking')]/ancestor::*[1]/following-sibling::*[normalize-space()!=''][1]/descendant::text()[starts-with(normalize-space(),'Status:')]/following::text()[normalize-space()!=''][1]");

        if (empty($this->status)) {
            $this->status = $this->http->FindSingleNode("//text()[contains(normalize-space(),'INVOICE - Booking')]/ancestor::*[1]/following::*[normalize-space()!=''][1]/descendant::text()[contains(normalize-space(),'tatus:')]/following::text()[normalize-space()!=''][1]");
        }

        $this->otaConfNo = null;
        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('INVOICE - Booking'))}]");

        if (preg_match("#({$this->opt($this->t('INVOICE - Booking'))})[\#:\s]+([\w\-]+)#", $node, $m)) {
            $this->otaConfNo[trim($m[1], ":")] = $m[2];
        } else {
            $this->logger->debug('other format ota info');

            return false;
        }

        // transfers, rentals and tours - skip. not enough info
        if ($this->parseFlights($email) === false) {
            $this->logger->alert('not parseFlights');

            return false;
        }

        if ($this->parseHotels($email) === false) {
            $this->logger->alert('not parseHotels');

            return false;
        }

        if ($this->parseTrains($email) === false) {
            $this->logger->alert('not parseTrains');

            return false;
        }

        if ($this->parseTrains2($email) === false) {
            $this->logger->alert('not parseTrains2');

            return false;
        }

        if ($this->parseTransfer($email, $text) === false) {
            $this->logger->alert('not parseTransfer');

            return false;
        }

        if ($this->parseRental($email, $text) === false) {
            $this->logger->alert('not parseRental');

            return false;
        }

        return true;
    }

    private function parseTransfer(Email $email, string $text)
    {
        if ($this->http->XPath->query("//text()[contains(.,'-Pickup:')]")->length == 0) {
            return true;
        }

        $r = $email->add()->transfer();

        foreach ($this->otaConfNo as $key => $value) {
            $r->ota()->confirmation($value, $key);
        }
        $r->general()->travellers(preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1',
            $this->http->FindNodes("//text()[contains(., 'Passengers')]/following::table[1]//tr[not(./tr)]")));
        $r->general()->noConfirmation();

        if (!empty($this->reservationDate)) {
            $r->general()->date($this->reservationDate);
        }
        // 31-Oct-2019 Thu
        foreach ($this->splitter("/(\d{1,2}-[A-z]{3}-\d{4} [A-z]{3})/s", $text) as $item) {
//            $this->logger->debug($item);

            /*
             Airline:NH Flight#:2179 Departure Airport:NRT Arrival Airport:ITM Arrival Time:740 AM
             Hotel-Pickup: Hotel Name:THE RITZ-CARLTON, OSAKA Address:2 CHOME-5-25 UMEDA, KITA WARD, OSAKA, 530-0001, JAPAN Phone#:+81 6-6343-7000 Arrival Time:740 AM
             Hotel-Dropoff: Hotel Name:TOKYO MARRIOTT HOTEL Address:SHINAGAWA-KU 4-7-36, 7 KITASHINAGAWA, SHINAGAWA-KU, TOKYO 140-0001, JAPAN Phone#:+81 3-5488-3911
             */
            if (preg_match('/(?<pickup>\w+-Pickup:\s*\n*\s*.+?)\n.*?(?<dropoff>\w+-Dropoff:\s*\n.+?)\n/s', $item, $m)
                || preg_match('/(?<dropoff>\w+-Dropoff:\s*\n*\s*.+?)\n.*?(?<pickup>\w+-Pickup:\s*\n.+?)\n/s', $item, $m)) {
                $s = $r->addSegment();

                if (preg_match('/^\s*(\d{1,2}-[A-z]{3}-\d{4}) [A-z]{3}/s', $item, $date)) {
                    $date = $this->normalizeDate($date[1]);
                }

//                $this->logger->debug($m['pickup']);
//                $this->logger->debug($m['dropoff']);
//                $this->logger->debug("---------");

                // Airline:NH Flight#:2179 Departure Airport:NRT Arrival Airport:ITM Arrival Time:740 AM
                if (preg_match('/Departure (Airport:\s*.+?)Arrival.+?(?:\w+ Time:(\d+\s*[AP]M))$/', $m['pickup'], $match)) {
                    $s->departure()->name($match[1])
                        ->address($match[1]);

                    if (!empty($match[2])) {
                        $s->departure()->date(strtotime($this->normalizeTime($match[2]), $date));
                    } else {
                        $s->departure()->noDate();
                    }
                }
                // Hotel Name:THE RITZ-CARLTON, OSAKA Address:2 CHOME-5-25 UMEDA, KITA WARD, OSAKA, 530-0001, JAPAN Phone#:+81 6-6343-7000
                if (preg_match('/Hotel Name:(.+?)Address:(.+?)(?:\s*Phone#:([+\d)(\s-]+))?$/', $m['pickup'], $match)) {
                    $s->departure()->name($match[1])
                        ->address($match[2])
                        ->noDate();
                }

                if (preg_match('/Departure (Airport:\s*.+?)Arrival.+?(?:\w+ Time:(\d+\s*[AP]M))?$/', $m['dropoff'], $match)) {
                    $s->arrival()->name($match[1])
                        ->address($match[1]);

                    if (!empty($match[2])) {
                        $s->arrival()->date(strtotime($this->normalizeTime($match[2]), $date));
                    } else {
                        $s->arrival()->noDate();
                    }
                }

                if (preg_match('/Hotel Name:(.+?)Address:(.+?)(?:\s*Phone#:([+\d)(\s-]+))?$/', $m['dropoff'], $match)) {
                    $s->arrival()->name($match[1])
                        ->address($match[2])
                        ->noDate();
                }
            }
        }

        return true;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseFlights(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('From:'))}]/ancestor::tr[1][{$this->contains($this->t('Depart:'))}]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-flights]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $this->logger->debug("---");
            $r = $email->add()->flight();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }
            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $node = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $node);
                $r->general()
                    ->travellers($node, true);
            }

            if (!empty($this->reservationDate)) {
                $r->general()
                    ->date($this->reservationDate);
            }

            // date trip
            $dateText = $this->searchDateTrip($root);
            $date = $this->normalizeDate($dateText);

            //segment
            $s = $r->addSegment();
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[normalize-space()='From:']/ancestor::td[1]/descendant::text()[normalize-space()!='']",
                    $root));
            // points
            $regExp = "#{$this->t('From:')}\s+(?<depCode>[A-Z]{3})\s*\-\s*(?<depName>.+)\s+" .
                "To:\s+(?<arrCode>[A-Z]{3})\s*\-\s*(?<arrName>.+)\s+On:\s+(?<iata>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\-\s*(?<airline>.+)#";

            if (preg_match($regExp, $node, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
                $s->airline()
                    ->name($m['iata'])
                    ->noNumber();

                if (!empty($conf = $this->re("#{$this->opt($this->t('Conf#:'))}\s+{$m['iata']}\\/([A-Z\d]{5,7})\n#", $node))) {
                    $s->airline()->confirmation($conf);
                }
            }
            //operator
            if (!empty($operator = $this->re("#{$this->opt($this->t('OPERATED BY'))}[ ]+(.+)#", $node))) {
                $s->airline()->operator($operator);
            }
            // dates
            if (preg_match("#{$this->t('Depart:')}\s+(.+?)\s+{$this->t('Arrive:')}\s+(.+?)(\+\d+| \d+)?(?:\s+|$)#", $node, $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeTime($m[1]), $date));
                $s->arrival()
                    ->date(strtotime($this->normalizeTime($m[2]), $date));

                if (isset($m[3]) && !empty($m[3])) {
                    $s->arrival()
                        ->date(strtotime('+' . trim($m[3], ' +') . ' days', $s->getArrDate()));
                }
            }
            // confirmation
            if (preg_match("#\n\s*({$this->opt($this->t('Conf#:'))})\s+([A-Z\d]{5,})#", $node, $m)) {
                $r->general()->confirmation($m[2], trim($m[1], ":"));
            } else {
                $r->general()->noConfirmation();
            }
        }

        return true;
    }

    private function parseTrains(Email $email)
    {
        $xpath = "//text()[{$this->contains($this->t('Class'))}]/ancestor::tr[1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd-dd:dd')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-train]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $this->logger->debug("---");
            $r = $email->add()->train();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $node = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $node);
                $r->general()
                    ->travellers($node, true);
            }

            //general info
            $r->general()
                ->noConfirmation();

            if (!empty($this->reservationDate)) {
                $r->general()
                    ->date($this->reservationDate);
            }
            $status = $this->http->FindSingleNode("./descendant::text()[contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd-dd:dd')]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                    $root);

            if (!empty($status)) {
                $r->general()
                    ->status($status);
            }

            // cost
            $sum = $this->http->FindSingleNode("./td[normalize-space()!=''][last()]", $root, false, "#^[\d\.\,]+$#");

            if (!empty($sum)) {
                $r->price()->cost(PriceHelper::cost($sum));
            }

            // date trip
            $dateText = $this->searchDateTrip($root);
            $date = $this->normalizeDate($dateText);

            // segment
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./descendant::text()[contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd-dd:dd')]",
                $root);

            if (preg_match("#(?<cabin>.+?{$this->t('Class')})\s+(?<train>.+?)[\-\s]+(?<dep>\d+:\d+)\s*\-\s*(?<arr>\d+:\d+)$#",
                $node, $m)) {
                $s->extra()->cabin($m[1]);
                $s->departure()->date(strtotime($m['dep'], $date));
                $s->arrival()->date(strtotime($m['arr'], $date));
                $arr = explode("-", $m['train']);

                if (count($arr) !== 3) {
                    $this->logger->debug('other format train');

                    return false;
                }
                $s->extra()->number($arr[0]);
                $s->departure()->name($arr[1]);
                $s->arrival()->name($arr[2]);
            }
        }

        return true;
    }

    private function parseTrains2(Email $email)
    {
        // RDG TRAIN#EM2138-LONDON ST. PANCRAS-NOTTINGHAM-12:35-14:08 Category: COMFORT
        $xpath = "//text()[{$this->contains($this->t('TRAIN#'))}]/ancestor::tr[1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd-dd:dd')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-train]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $this->logger->debug("---");
            $r = $email->add()->train();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $node = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $node);
                $r->general()
                    ->travellers($node, true);
            }

            //general info
            $r->general()
                ->noConfirmation();

            if (!empty($this->reservationDate)) {
                $r->general()
                    ->date($this->reservationDate);
            }

            // cost
            $sum = $this->http->FindSingleNode("./td[normalize-space()!=''][last()]", $root, false, "#^[\d\.\,]+$#");

            if (!empty($sum)) {
                $r->price()->cost(PriceHelper::cost($sum));
            }

            // date trip
            $dateText = $this->searchDateTrip($root);
            $date = $this->normalizeDate($dateText);

            // segment
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./descendant::text()[contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd-dd:dd')]",
                $root);

            // RDG TRAIN#EM2138-LONDON ST. PANCRAS-NOTTINGHAM-12:35-14:08 Category: COMFORT
            if (preg_match("#{$this->opt($this->t('TRAIN#'))}(?<number>[A-Z \d]+?)\s*\-\s*(?<route>.+?)-(?<dep>\d+:\d+)\s*\-\s*(?<arr>\d+:\d+)\s*{$this->opt($this->t('Category:'))}\s*(?<cabin>.+)$#",
                $node, $m)) {
                $s->departure()->date(strtotime($m['dep'], $date));
                $s->arrival()->date(strtotime($m['arr'], $date));

                $arr = explode("-", $m['route']);

                if (count($arr) !== 2) {
                    $this->logger->debug('other format train');

                    return false;
                }
                $s->departure()->name($arr[0]);
                $s->arrival()->name($arr[1]);

                $s->extra()
                    ->number($m['number'])
                    ->cabin($m['cabin']);
            }
        }

        return true;
    }

    private function parseRental(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Car Rental'))}]/ancestor::tr[1][{$this->contains($this->t('Pick up:'))}]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-train]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $this->logger->debug("---");
            $r = $email->add()->rental();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $node = preg_replace("/\s*:\s*\d+\s*$/", '', $node);
                $node = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $node);
                $r->general()
                    ->travellers($node, true);
            }

            //general info
            $r->general()
                ->noConfirmation();

            if (!empty($this->reservationDate)) {
                $r->general()
                    ->date($this->reservationDate);
            }

            // cost
            $sum = $this->http->FindSingleNode("./td[normalize-space()!=''][last()]", $root, false, "#^[\d\.\,]+$#");

            if (!empty($sum)) {
                $r->price()->cost(PriceHelper::cost($sum));
            }

            // date trip
//            $dateText = $this->searchDateTrip($root);
//            $date = $this->normalizeDate($dateText);

            // Pick up
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick up:'))}]", $root);

            // Pick up: 22-Oct-2023 09:00 / E BURGH WAVERLEY STATION WAVERLEY STATIONNEW STREET CAR PARK Phone: 0131-3414441
            $re = "#.+?:\s*(?<date>.+?)\s*/\s*(?<location>.+?)\s*Phone:\s*(?<phone>[\d \-]{5,})\s*$#";

            if (preg_match($re, $node, $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m['date']))
                    ->location($m['location'])
                    ->phone($m['phone']);
            }

            // Pick up
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop-off:'))}]", $root);

            if (preg_match($re, $node, $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m['date']))
                    ->location($m['location'])
                    ->phone($m['phone']);
            }

            // Car
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Car Rental '))}]", $root);

            if (preg_match("/Car Rental\s+(.+?)\s*or Similar /", $node, $m)) {
                $r->car()
                    ->model($m[1]);
            }
        }

        return true;
    }

    private function parseHotels(Email $email)
    {
        $xpath = "//text()[{$this->ends($this->t('nts'), true)}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-hotel]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $this->logger->debug("---");
            $segmentType = '';
            $city = $description = null;
            // check hotel + hotelInfo - roomType
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][not(position()=last())][{$this->ends($this->t('nts'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (empty($node) && !empty($this->http->FindSingleNode(".", $root, true, "/^\s*\d+nts?\s*$/"))) {
                $node = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root);
                $segmentType = 'sibling';
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root);
            }
            $arr = explode(" - ", $node);

            switch (count($arr)) {
                case 2:
                    if ($this->http->XPath->query("./descendant::text()[string-length(normalize-space())>2][last()]/ancestor::td[1][./preceding-sibling::td[./img[contains(@src,'star')]]]",
                            $root)->length > 0
                    ) {
                        $this->logger->debug("skip: " . $arr[0] . ". not hotel");

                        continue 2;
                    }
                    $hotelName = $arr[0];
                    $type = $arr[1];

                    break;

                case 3:
                    $city = $arr[0];
                    $hotelName = $arr[1];
                    $type = $arr[2];

                    break;

                case 4:
                    $city = $arr[0];
                    $hotelName = $arr[1];
                    $type = $arr[2];
                    $description = $arr[3];

                    break;

                default:
                    $this->logger->debug("other format hotelName");

                    return false;
            }
            $r = $email->add()->hotel();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }

            // date trip
            $dateText = $this->searchDateTrip($root);
            $date = $this->normalizeDate($dateText);

            $room = $r->addRoom();
            $room->setType($type);

            if (isset($description)) {
                $room->setDescription($description);
            }
            $r->hotel()
                ->name($hotelName)
                ->noAddress();

            if (isset($city)) {
                $r->hotel()->detailed()->city($city);
            }

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (empty($node) && $segmentType === 'sibling') {
                $node = array_filter(array_map("trim", explode(",",
                    $this->http->FindSingleNode("following-sibling::tr[1]/descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                        "#{$this->opt($this->t('for:'))}\s+(.+)#"))));
            }

            if (!empty($node)) {
                $node = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $node);
                $r->general()
                    ->travellers($node, true);
            }
            // general info
            $r->general()
                ->noConfirmation();

            if (!empty($this->reservationDate)) {
                $r->general()
                    ->date($this->reservationDate);
            }
            $status = $this->http->FindSingleNode("./td[./a[contains(@href,'product_information')]][last()]/preceding-sibling::td[normalize-space()!=''][1]",
                    $root);

            if (!empty($status)) {
                $r->general()
                    ->status($status);
            }

            // cost
            $sum = $this->http->FindSingleNode("./td[normalize-space()!=''][last()]", $root, false, "#^[\d\.\,]+$#");

            if (!empty($sum)) {
                $r->price()->cost(PriceHelper::cost($sum));
            }

            // cancellation
            $cancellation = $this->http->FindSingleNode("./following-sibling::tr[1][./descendant::text()[{$this->starts($this->t('Cancellation Policy:'))}]]/descendant::text()[{$this->starts($this->t('Cancellation Policy:'))}]",
                $root, false, "#{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)#");

            if (!empty($cancellation)) {
                $r->general()->cancellation($cancellation);
            }

            // booked
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->ends($this->t('nts'))}]", $root, false,
                "#(\d+)\s*{$this->opt($this->t('nts'))}#");
            $r->booked()
                ->checkIn($date)
                ->checkOut(strtotime("+" . $node . " days", $date));

            $this->detectDeadLine($r);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/.+? are NON-REFUNDABLE (?<prior>\d+) days prior to departure./ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['prior'] . ' days', '00:00');
        }

        $h->booked()
            ->parseNonRefundable("#NON-REFUNDABLE once documents are shipped.#");
    }

    private function searchDateTrip(\DOMNode $root)
    {
        $dateText = $this->http->FindSingleNode("./td[normalize-space()!=''][1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'-dddd')]",
            $root);

        if (!$dateText) {
            $dateText = $this->http->FindSingleNode("./preceding::tr[./td[normalize-space()!=''][1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'-dddd')]][1]/td[normalize-space()!=''][1]",
                $root);

            if ($dateText && mb_strlen($dateText) > 40) {
                $dateText = current($this->http->FindNodes("./preceding::tr[./td[normalize-space()!=''][1][contains(translate(normalize-space(),'0123456789','dddddddddd'),'-dddd')]]/td[normalize-space()!=''][1]",
                    $root, '/\d{1,2}-[A-z]{3}-\d{4}/'));
            }
        }

        if (!$dateText) {
            $this->http->FindSingleNode("./td[normalize-space()!=''][last()][contains(translate(normalize-space(),'0123456789','dddddddddd'),'-dddd')]",
                $root);
        }

        if (!$dateText) {
            $dateText = $this->http->FindSingleNode("./preceding::tr[./td[normalize-space()!=''][last()][contains(translate(normalize-space(),'0123456789','dddddddddd'),'-dddd')]][1]/td[normalize-space()!=''][last()]",
                $root);
        }

        return $dateText;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Mon 24-Jun-2019
            '#^(\w+)\s+(\d+)\-(\w+)\-(\d{4})$#u',
            // 31-Oct-2019 Thu
            '#^(\d+)-(\w+)-(\d{4})\s+(\w+)\s+$#',
        ];
        $out = [
            '$2 $3 $4',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function normalizeTime($str)
    {
        $str = trim($str);
        $in = [
            // 740 PM
            '#^(\d{1,2})(\d{2})\s*([AP]M)$#',
            //350P
            '#^(\d{1,2})(\d{2})([AP])$#',
        ];
        $out = [
            '$1:$2 $3',
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

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Depart from home town'], $words['#Pax'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Depart from home town'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['#Pax'])}]")->length > 0
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

    private function htmlToText($string)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        return preg_replace('/\n{2,}/', "\n", $text);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function ends($field, $prevNum = false)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                if ($prevNum) {
                    $len++;
                    $f = 'd' . $f;
                    $rule = "translate(substring(normalize-space(),string-length(normalize-space())+1-{$len},{$len}),'0123456789','dddddddddd')=\"{$f}\"";
                } else {
                    $rule = "substring(normalize-space(),string-length(normalize-space())+1-{$len},{$len})=\"{$f}\"";
                }
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, "#"));
        }, $field)) . ')';
    }
}
