<?php

namespace AwardWallet\Engine\allegiant\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "allegiant/it-11999766.eml, allegiant/it-12137449.eml, allegiant/it-12221790.eml, allegiant/it-13036453.eml, allegiant/it-13041151.eml, allegiant/it-13697853.eml, allegiant/it-4137908.eml, allegiant/it-4172161.eml, allegiant/it-4197862.eml, allegiant/it-4339108.eml, allegiant/it-5490804.eml, allegiant/it-60307951.eml, allegiant/it-60963043.eml, allegiant/it-61127565.eml";
    public static $dict = [
        'en' => [
            'Your booking is' => ['Your booking is', 'Your booking has been'],
            'Record locator'  => 'confirmation number',
            'Passenger'       => 'Passenger Name',
            'Check-in'        => ['Check-in', 'Check-in Date'],
            'Check-out'       => ['Check-out', 'Check-out Date'],
        ],
    ];

    private $reBody = [
        'en' => ['confirmation', 'Flight'],
    ];
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return ($this->http->XPath->query('//text()[contains(normalize-space(.),"Thanks for traveling with Allegiant")]')->length > 0
            && $this->http->XPath->query('//text()[contains(normalize-space(.),"Here is your itinerary")]')->length > 0)
            || ($this->http->XPath->query('//text()[contains(normalize-space(.),"Allegiant Travel Company")]')->length > 0
                && $this->http->XPath->query('//text()[contains(normalize-space(.),"Your booking has been canceled.")]')->length > 0)
        ;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'AllegiantAir.com - Itinerary') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@allegiantdeals.com') !== false || stripos($from, '@t.allegiant.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $status = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Your booking is'))}][1]", null, true, "/{$this->opt($this->t('Your booking is'))}\s+(\w+)(?:\s*[,.;!]|$)/u");

        if (empty($status) && $this->http->XPath->query("//text()[{$this->contains('Your confirmation number is:')}]")->length === 1) {
            $status = 'confirmed';
        }
        $reservationDate = strtotime($this->http->FindSingleNode("//text()[{$this->contains('Book Date')}]/following::text()[normalize-space()][1]"));

        // FLIGHTS

        if ($this->http->FindSingleNode("//*[contains(text(),'Flight Details')]")) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($this->http->FindSingleNode("(//*[contains(text(), '" . $this->t('Record locator') . "')]/ancestor-or-self::td[1])[1]", null, true, "#:\s*([A-Z\d]+)#"))
                ->travellers(array_values(array_unique($this->http->FindNodes("//text()[contains(.,'" . $this->t('Passenger') . "')]/following::text()[normalize-space(.)][1]", null, "#(.*?)(?: - |$)#"))), true)
            ;

            if ($reservationDate) {
                $f->general()->date($reservationDate);
            }

            if ($status) {
                $f->general()->status($status);
            }

            if (strcasecmp($status, 'canceled') === 0) {
                $f->general()->cancelled();
            }

            // Segments
            $xpath = "//table/descendant::tr[1][contains(.,'Date') and contains(.,'Flight') and count(descendant::tr)=0]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                // Airline
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Here is your itinerary and receipt. Thanks for traveling with Allegiant.")]')->length > 0
                    || $this->http->XPath->query('//node()[contains(normalize-space(.),"How to Allegiant")]')->length > 0
                    || $this->http->XPath->query('//node()[contains(normalize-space(.),"Thanks for traveling with Allegiant")]')->length > 0) {
                    $s->airline()->name('G4');
                }
                $s->airline()->number($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Flight'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d+$/'));

                $patternsAirport = '/(?<name>.+?)?\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/'; // St Petersburg Clearwater International Airport (PIE)

                // Departure
                $date = $this->normalizeDate($this->http->FindSingleNode(".//*[contains(text(),'Date')]/following::text()[normalize-space(.)][1]", $root));
                $airportDep = $this->http->FindSingleNode("./following::text()[contains(.,'Departure Airport')][1]/following::text()[string-length(normalize-space(.))>2][1]", $root);

                if (preg_match($patternsAirport, $airportDep, $m)) {
                    $s->departure()
                        ->name($m['name'])
                        // Phoenix-Mesa Gateway Airport is defined on the site as IWA, but the real code is AZA
                        ->code(str_replace('IWA', 'AZA', $m['code']))
                    ;
                }
                $node = implode(" ", $this->http->FindNodes("./following::text()[contains(.,'Departs')][1]/following::text()[string-length(normalize-space(.))>1][position()<3 and not(contains(.,'Arrival'))]", $root));

                if (!empty($date)) {
                    $s->departure()->date(strtotime($node, $date));
                }

                // Arrival
                $airportArr = $this->http->FindSingleNode("./following::text()[contains(.,'Arrival Airport')][1]/following::text()[string-length(normalize-space(.))>2][1]", $root);

                if (preg_match($patternsAirport, $airportArr, $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        // Phoenix-Mesa Gateway Airport is defined on the site as IWA, but the real code is AZA
                        ->code(str_replace('IWA', 'AZA', $m['code']))
                    ;
                }
                $node = implode(" ", $this->http->FindNodes("./following::text()[contains(.,'Arrives')][1]/following::text()[string-length(normalize-space(.))>1][position()<3 and not(contains(.,'" . $this->t('Passenger') . "'))]", $root));

                if (!empty($date)) {
                    $s->arrival()->date(strtotime($node, $date));
                }

                // Extra
                $seats = array_filter($this->http->FindNodes("./following::text()[normalize-space() = 'Seat Assignment'][position() <=" . count($f->getTravellers()) . "]/following::text()[normalize-space()][1]", $root, "#^\s*(\d{1,3}[A-Z])\s*(?:,|$)#"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }

        // HOTELS
        if ($this->http->FindSingleNode("//*[contains(text(),'Hotel Details')]")) {
            $h = $email->add()->hotel();

            // Travel Agency
            $h->ota()
                ->confirmation($this->http->FindSingleNode("(//*[contains(text(), '" . $this->t('Record locator') . "')]/ancestor-or-self::td[1])[1]", null, true, "#:\s*([A-Z\d]+)#"));

            // General
            $conf = $this->http->FindSingleNode("(//*[contains(text(),'Hotel Details')]/following::*[contains(text(), '" . $this->t('Record locator') . "')]/ancestor-or-self::td[1])[1]", null, true, "#:\s*([A-Z\d]+)#");

            if ((empty($conf) && empty($this->http->FindSingleNode("(//*[contains(text(),'Hotel Details')]/following::*[contains(text(), '" . $this->t('Record locator') . "')])[1]"))) || strlen($conf) < 3) {
                $h->general()->noConfirmation();
            } else {
                $h->general()->confirmation($conf);
            }

            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Customer Name'))}]/following::text()[normalize-space()][1]");

            if (!empty($traveller)) {
                $h->general()->traveller($traveller, true);
            }

            if ($reservationDate) {
                $h->general()->date($reservationDate);
            }

            if ($status) {
                $h->general()->status($status);
            }

            if (strcasecmp($status, 'canceled') === 0) {
                $h->general()
                    ->cancelled();
            }

            // Hotel
            $name = $this->http->FindSingleNode("//*[contains(text(),'Hotel Details')]/following::tr[1]//h4");
            $address = $this->http->FindSingleNode("//*[contains(text(),'Hotel Details')]/following::tr[1]");
            $address = trim(str_replace($name, "", str_replace("Map", "", $address)));

            if (preg_match("#(.+)\s+Phone:\s*(.+)#", $address, $m)) {
                $address = trim($m[1]);
                $phone = trim($m[2]);
            }
            $name = str_replace("®", "", $name);
            $h->hotel()
                ->name($name)
                ->address($address)
                ->phone($phone ?? null, true, true)
            ;

            // Booked
            $checkIn = $this->http->FindSingleNode("//*[{$this->contains($this->t('Check-in'))}]/following-sibling::span");

            if (empty($checkIn)) {
                $checkIn = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Details']/following::text()[starts-with(normalize-space(), 'Check-in')]/following::text()[normalize-space()][1]");
            }

            $checkOut = $this->http->FindSingleNode("//*[{$this->contains($this->t('Check-out'))}]/following-sibling::span");

            if (empty($checkOut)) {
                $checkOut = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Details']/following::text()[starts-with(normalize-space(), 'Check-out')]/following::text()[normalize-space()][1]");
            }

            $h->booked()
                ->checkIn($this->normalizeDateRelative($checkIn, $reservationDate))
                ->checkOut($this->normalizeDateRelative($checkOut, $reservationDate));

            $COMMON_XPATH = "//table[contains(@class,'block-grid')]//tr/td/h4[contains(.,";

            if (($result = $this->http->FindSingleNode($COMMON_XPATH . "'Number of Guests')]/following-sibling::span"))) {
                $h->booked()->guests($result);
            }

            if (($result = $this->http->FindSingleNode($COMMON_XPATH . "'Number of Rooms')]/following-sibling::span"))) {
                $h->booked()->rooms($result);
            }

            // Rooms
            if (($result = $this->http->FindSingleNode($COMMON_XPATH . "'Room Type')]/following-sibling::span"))) {
                $h->addRoom()->setType($result);
            }
        }

        // RENTALS
        if ($this->http->FindSingleNode("//*[contains(text(),'Car Rental')]")) {
            $r = $email->add()->rental();

            // Travel Agency
            $r->ota()
                ->confirmation($this->http->FindSingleNode("(//*[contains(text(), '" . $this->t('Record locator') . "')]/ancestor-or-self::td[1])[1]", null, true, "#:\s*([A-Z\d]+)#"));

            // General
            $confirmation = $this->http->FindSingleNode("(//*[contains(text(),'Car Rental')]/following::*[contains(text(),'{$this->t('Record locator')}')]/ancestor-or-self::td[1])[1]", null, true, '/:\s*([-A-Z\d]{5,})$/');

            if ($confirmation) {
                $r->general()->confirmation($confirmation);
            }
            $traveller = $this->http->FindSingleNode("//text()[contains(.,'Customer Name')]/following::text()[normalize-space(.)][1]");

            if (!empty($traveller)) {
                $r->general()->traveller($traveller, true);
            }

            if ($reservationDate) {
                $r->general()->date($reservationDate);
            }

            if ($status) {
                $r->general()->status($status);
            }

            if (strcasecmp($status, 'canceled') === 0) {
                $r->general()
                    ->cancelled();
            }

            $node = $this->http->FindNodes("//*[contains(text(),'Pickup')]/following-sibling::span");

            if (empty($node)) {
                $node = $this->http->FindNodes("//text()[contains(.,'Car Rental')]//following::*[contains(text(),'Pickup')]/following::span[normalize-space() and not(.//span)][position()<3]");
            }

            if (isset($node[0]) && isset($node[1])) {
                $r->pickup()
                    ->date($this->normalizeDateRelative($node[0] . ", " . $node[1], $reservationDate));
            }

            $node = $this->http->FindNodes("//*[contains(text(),'Return')]/following-sibling::span");

            if (empty($node)) {
                $node = $this->http->FindNodes("//text()[contains(.,'Car Rental')]//following::*[contains(text(),'Return')]/following::span[normalize-space() and not(.//span)][position()<3]");
            }

            if (isset($node[0]) && isset($node[1])) {
                $r->dropoff()
                    ->date($this->normalizeDateRelative($node[0] . ", " . $node[1], $reservationDate));
            }

            $company = $this->http->FindSingleNode("//*[contains(text(),'Company')]/following-sibling::span");

            if (empty($company)) {
                $company = $this->http->FindSingleNode("(//*[contains(text(),'Car Rental')]/following::*[contains(text(),'Company')]/following::span[normalize-space()][1])[1]");
            }

            if (($code = $this->normalizeRentalProvider($company))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($company);
            }

            $carType = $this->http->FindSingleNode("//*[contains(text(),'Vehicle Type')]/following-sibling::span");

            if (empty($carType)) {
                $carType = $this->http->FindSingleNode("(//*[contains(text(),'Car Rental')]/following::*[contains(text(),'Vehicle Type')]/following::span[normalize-space()][1])[1]");
            }
            $r->car()->type($carType);

            $locationPickup = $this->http->FindSingleNode("//table[not(.//table) and {$this->starts($this->t('Car Rental'))}]/following-sibling::table[{$this->contains(['To pick up your car at', 'To pick up your rental car at'])}]");

            if (preg_match("/To pick up your(?: rental)? car at (.+?), /i", $locationPickup, $matches)) {
                $r->pickup()->location($matches[1]);
                $r->dropoff()->same();
            } elseif (empty($r->getDropOffDateTime())
                && $this->http->XPath->query("//tr/*[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Return'))}] ]")->length === 0
            ) {
                $email->removeItinerary($r);
            }

            if (empty($r->getPickUpLocation()) && empty($r->getDropOffLocation())) {
                $cell = implode("\n", $this->http->FindNodes("//tr[starts-with(normalize-space(), 'Company')]/following::text()[normalize-space()='Pickup']/ancestor::td[1]/descendant::text()[normalize-space()]"));

                if (preg_match("/^Pickup\n\w+\,\s*\w+\s*\d+\n[\d\:]+\s*A?P?M$/", $cell)) {
                    $email->removeItinerary($r);
                }
            }
        }

        $payName = $this->http->FindNodes("//text()[normalize-space()='myAllegiant points']/ancestor::td[contains(.,'Payment') and contains(.,'Type')][1]//text()[normalize-space()]");
        $payValue = $this->http->FindNodes("//text()[normalize-space()='myAllegiant points']/ancestor::td[contains(.,'Payment') and contains(.,'Type')][1]/following-sibling::td[normalize-space()][last()]//text()[normalize-space()]");

        if (!empty($payName) && !empty($payValue) && count($payName) == count($payValue)) {
            foreach ($payName as $key => $value) {
                if ($value == 'myAllegiant points') {
                    $email->price()->spentAwards($payValue[$key]);
                }
            }
        }

        $totals = $this->http->FindNodes("//*[contains(text(),'Total') and contains(text(),'Cost')]/parent::*/following::td[1][not(starts-with(normalize-space(), '-'))]");

        foreach ($totals as $value) {
            $tot = $this->getTotalCurrency($value);

            if (!empty($tot['Currency']) && $tot['Total'] !== '') {
                $total = isset($total) ? $total + $tot['Total'] : (float) $tot['Total'];
                $currency = $tot['Currency'];
            }
        }

        if (isset($total)) {
            $email->price()
                ->total($total)
                ->currency($currency);
        }

        $cost = $this->http->FindSingleNode("//text()[normalize-space() = 'Airfare' or normalize-space() = 'Package']/parent::*/following::td[1]");
        $tot = $this->getTotalCurrency($cost);

        if (!empty($tot['Currency']) && $tot['Total'] !== '') {
            $email->price()
                ->cost($tot['Total']);
        }

        $tRoots = $this->http->XPath->query("//text()[normalize-space() = 'Airfare' or normalize-space() = 'Package']/ancestor::tr[1]/following-sibling::*");
        $taxes = [];

        foreach ($tRoots as $tRoot) {
            $value = $this->http->FindSingleNode("./td[2]", $tRoot);

            $amount = $this->getTotalCurrency($value);
            $name = $this->http->FindSingleNode("./td[1]", $tRoot);

            if (preg_match("#^Total\s+#", $name)) {
                break;
            }

            if (trim($value) == 'INCLUDED') {
                continue;
            }

            if (strpos(trim($value), '-') === 0) {
                if (preg_match("#\bDiscount\b#i", $name) && $amount['Total'] !== '') {
                    $discount = isset($discount) ? $discount + $amount['Total'] : (float) $amount['Total'];
                }

                continue;
            }

            if (!empty($amount['Currency']) && $amount['Total'] !== '') {
                $taxes[] = ['name'=> $name, 'amount' => $amount['Total']];
            } else {
                $taxes = [];

                break;
            }
        }

        if (!empty($discount)) {
            $email->price()->discount($discount);
        }

        foreach ($taxes as $tax) {
            $email->price()
                ->fee($tax['name'], $tax['amount']);
        }
    }

    private function normalizeRentalProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'alamo' => ['ALAMO'],
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
            '#\S+\s*,\s*(\S+)\s*(\d+),\s*(\d+)#',
        ];
        $out = [
            '$2 $1 $3',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeDateRelative($date, $relativeDate)
    {
        $year = date('Y', $relativeDate);
//        $this->logger->debug('$date = ' . $date);
        $in = [
            // Tue, Jun 16, 4:00 pm
            '#^(\w+),\s*(\w+)\s+(\d+)\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m))$#iu',
            // Sun, Apr 09
            '#^(\w+),\s*(\w+)\s+(\d+)\s*$#iu',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
            '$1, $3 $2 ' . $year . '',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';
        $node = str_replace("$", "USD", $node);

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
