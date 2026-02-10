<?php

namespace AwardWallet\Engine\avantid\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "avantid/it-35946423.eml, avantid/it-38200410.eml, avantid/it-41776132.eml, avantid/it-41829601.eml";

    public $reFrom = ["@avantidestinations.com"];
    public $reBody = [
        'en' => ['This itinerary has been prepared specifically for you'],
    ];
    public $reSubject = [
        'Itinerary for:',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Depart from home town' => 'Depart from home town',
            'BOOKING NUMBER:'       => ['BOOKING NUMBER:', 'PROPOSAL NUMBER:'],
            'Adults'                => ['Adults', 'Adult'],
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        $this->otaConfNo = null;
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOKING NUMBER:'))}]");

        if (preg_match("#({$this->opt($this->t('BOOKING NUMBER:'))})\s+(.+)#", $node, $m)) {
            $this->otaConfNo[trim($m[1], ":")] = $m[2];

            if ($m[1] == $this->t('PROPOSAL NUMBER:')
                && $this->http->XPath->query("//text()[{$this->contains($this->t('for planning purposes only'))}]")->length > 0
            ) {
                $this->status = 'planning';
            }
        } else {
            $this->logger->debug('other format ota info');

            return false;
        }
        // transfers and tours - skip. not enough info
        if ($this->parseFlights($email) === false) {
            return false;
        }

        if ($this->parseHotels($email) === false) {
            return false;
        }

        if ($this->parseRentals($email) === false) {
            return false;
        }

        if ($this->parseTrains($email) === false) {
            return false;
        }

        return true;
    }

    private function parseFlights(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Flight from'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-flights]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $dateText = $this->http->FindSingleNode("./preceding::tr[{$this->ends('-dddd', 'translate(normalize-space(),\'0123456789\',\'dddddddddd\')')}][1]",
                $root);
            $r = $email->add()->flight();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }
            $s = $r->addSegment();

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $r->general()
                    ->travellers($node, true);
            }

            // status
            if (isset($this->status)) {
                $r->general()->status($this->status);
            }

            // points
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#{$this->opt($this->t('Flight from'))}\s+(.+)\s+\(([A-Z]{3})\)\s+{$this->opt($this->t('to'))}\s+(.+)\s+\(([A-Z]{3})\)#",
                $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4]);
            }

            // airline+flight
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[normalize-space()!=''][position()>1]", $root));

            if (preg_match("#{$this->opt($this->t('at'))}\s+(.+)\s+{$this->opt($this->t('on'))}\s+(.+)\s+{$this->t('flight')}\#(\d+)#",
                $node, $m)) {
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
                $timeDep = $m[1];

                $flightSearch = $this->t('Flight: ') . $m[3];

                // try get info on top
                $node = implode("\n",
                    $this->http->FindNodes("//text()[normalize-space()='{$flightSearch}']/preceding::text()[normalize-space()!=''][1][contains(normalize-space(),'{$m[2]}')]/ancestor::*[{$this->contains($this->t('Depart'))}][1]/ancestor::*[1]/descendant::text()[normalize-space()!='']"));

                if (preg_match("#{$this->t('Depart')}:\s+(.+)\s+{$this->t('Arrive')}:\s+(.+?)(\+\d+)?\n#", $node, $m)) {
                    // dates
                    $s->departure()
                        ->date(strtotime($this->normalizeTime($m[1]), $this->normalizeDate($dateText)));
                    $s->arrival()
                        ->date(strtotime($this->normalizeTime($m[2]), $this->normalizeDate($dateText)));

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()
                            ->date(strtotime($m[3] . ' days', $s->getArrDate()));
                    }
                    // confirmation
                    if (preg_match("#{$this->t('Confirmation')}:\s+([A-Z\d]{5,})#", $node, $m)) {
                        $r->general()
                            ->confirmation($m[1], $this->t('Confirmation'));
                    } else {
                        $r->general()
                            ->noConfirmation();
                    }
                    //operator
                    if (!empty($operator = $this->re("#{$this->t('OPERATED BY')}\s+(.+)#i", $node))) {
                        $s->airline()->operator($operator);
                    }
                } else {
                    // default info
                    $s->departure()
                        ->date(strtotime($this->normalizeTime($timeDep), $this->normalizeDate($dateText)));
                    $s->arrival()->noDate();
                    $r->general()
                        ->noConfirmation();
                }
            }
        }

        return true;
    }

    private function parseHotels(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Address:'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-hotels]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }

            if (isset($this->status)) {
                $r->general()->status($this->status);
            }

            //hotel info
            $hotelName = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);
            $r->hotel()
                ->name($hotelName)
                ->address($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Address:'))}]/following::text()[normalize-space()!=''][1]",
                    $root));

            //room type
            $room = $r->addRoom();
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Address:'))}]/preceding::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#(\d+)\s*\-\s*(.+)#", $node, $m)) {
                $r->booked()
                    ->rooms($m[1]);
                $room->setType(trim($m[2], "*"));
            } else {
                $room->setType(trim($node, "*"));
            }

            // dates
            $datesHotel = $this->http->FindNodes("//text()[normalize-space()='{$hotelName}']/preceding::tr[{$this->ends('-dddd', 'translate(normalize-space(),\'0123456789\',\'dddddddddd\')')}][1]");
            $datesHotel = array_map([$this, "normalizeDate"], $datesHotel);
            $cnt = count($datesHotel) - 1;

            if (end($datesHotel) !== strtotime($cnt . " days", $datesHotel[0])) {
                $this->logger->debug("error in hotel reservation");

                return false;
            }
            $r->booked()
                ->checkIn($datesHotel[0])
                ->checkOut(end($datesHotel));

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(),'for:')]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $r->general()
                    ->travellers($node, true);
            }

            // confirmation
            $r->general()
                ->noConfirmation();
        }

        return true;
    }

    private function parseRentals(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Pick-up:'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-rentals]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            if (isset($this->status)) {
                $r->general()->status($this->status);
            }

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }
            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (empty($node)) {
                $node = array_filter(array_map("trim", explode(",",
                    $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Driver:'))}]", $root, false,
                        "#{$this->opt($this->t('Driver:'))}\s+(.+?)\s*:\d+#"))));
            }

            if (!empty($node)) {
                $r->general()
                    ->travellers($node, true);
            }
            $r->general()
                ->noConfirmation();

            // car
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#(.+?)\s+{$this->t('Rent A Car')}\s+[A-Z]+\s+\-\s+[A-Z]\s+(.+)#", $node, $m)) {
                $rentalCompany = $m[1];
                $r->car()
                    ->model($m[2]);
            } elseif (preg_match("#(.+?)\s+{$this->t('Rent A Car')}\s+(.+)#", $node, $m)) {
                $rentalCompany = $m[1];
                $r->car()
                    ->model($m[2]);
            } elseif (preg_match("#(.+?)\s+\-\s+(.+? {$this->t('OR SIMILAR')})$#i", $node, $m)) {
                $rentalCompany = $m[1];
                $r->car()
                    ->model($m[2]);
            } else {
                $this->logger->debug('other format rental');

                return false;
            }
            $type = [];
            $type[] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Category:'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $type[] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Type:'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $type[] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Transmission:'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Air Cond.:'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (!empty($node)) {
                $type[] = $this->t('Air Cond.:');
                $type[] = $node;
            }
            $r->car()->type(implode(" ", $type));

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
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Pick-up:'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#(.+) \/\s*(.+?)(?:{$this->opt($this->t('Phone:'))}\s+(.+)|$)#", $node, $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m[1]))
                    ->location($m[2])
                    ->phone($m[3], false, true);
            }

            //drop-off
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Drop-off:'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#(.+) \/\s*(.+?)(?:{$this->opt($this->t('Phone:'))}\s+(.+)|$)#", $node, $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m[1]))
                    ->location($m[2])
                    ->phone($m[3], false, true);
            }
        }

        return true;
    }

    private function parseTrains(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Train#'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-trains]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $r = $email->add()->train();

            if (isset($this->status)) {
                $r->general()->status($this->status);
            }

            foreach ($this->otaConfNo as $key => $value) {
                $r->ota()->confirmation($value, $key);
            }

            // date
            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[{$this->ends('-dddd', 'translate(normalize-space(),\'0123456789\',\'dddddddddd\')')}][1]",
                $root));

            // travellers
            $node = array_filter(array_map("trim", explode(",",
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('for:'))}]", $root, false,
                    "#{$this->opt($this->t('for:'))}\s+(.+)#"))));

            if (!empty($node)) {
                $r->general()
                    ->travellers($node, true);
            }
            $r->general()
                ->noConfirmation();
            $s = $r->addSegment();

            // points
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#{$this->opt($this->t('Train Transportation from'))}\s+(.+)\s+{$this->t('to')}\s+(.+)#",
                $node, $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            // number, dates
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Train#'))}]", $root);

            if (preg_match("#{$this->opt($this->t('Train#'))}\s+(\w+)\s+.+?\s+{$this->t('departing')}\s+(\d+:\d+)\s+{$this->t('arriving')}\s+(\d+:\d+)#",
                $node, $m)) {
                $s->extra()->number($m[1]);
                $s->departure()->date(strtotime($m[2], $date));
                $s->arrival()->date(strtotime($m[3], $date));
            }

            // cabin
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Train#'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#(\d+)\s*\-\s*{$this->opt($this->t('Adults'))}\s+(?:(\d+)\s*\-\s*{$this->opt($this->t('Children'))}\s+)?(.*\b{$this->t('Class')}\b.*)#",
                $node, $m)) {
                $s->extra()->cabin($m[3]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Mon 24-Jun-2019
            '#^(\w+)\s+(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
            '$2 $3 $4',
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
            if (isset($words['Depart from home town'], $words['BOOKING NUMBER:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Depart from home town'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['BOOKING NUMBER:'])}]")->length > 0
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

    private function ends($field, $source = 'normalize-space()')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring({$source},string-length({$source})+1-{$len},{$len})='{$f}'";
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
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }
}
