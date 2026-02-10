<?php

namespace AwardWallet\Engine\aurigny\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingReference extends \TAccountChecker
{
    public $mailFiles = "aurigny/it-30001208.eml, aurigny/it-30281543.eml, aurigny/it-69818080.eml, aurigny/it-69818384.eml";
    private $subjects = [
        'en' => ['booking reference:', 'booking reference :'],
    ];
    private $langDetectors = [
        'en' => ['Booking Ref:', 'Arrive'],
    ];
    private $status = [];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Booking reference' => ['Booking reference', 'Booking Ref:'],
            //            'Booking made on:' => '',
            'Depart:' => ['Depart:', 'Depart'],
            'Arrive:' => ['Arrive:', 'Arrive'],
            //            'Dep:' => '',
            //            'Arr:' => '',
            'statusVariants' => ['Confirmed', 'Cancelled'],
            //            ' to ' => '',
            'Date:' => ['Date:', 'Date'],
            //            'Passengers' => '',
            'Total fare, cost and fees' => ['Total fare, cost and fees', 'Total Fare Cost & Fees'],
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Aurigny') !== false
            || stripos($from, '@aurigny.com') !== false
            || stripos($from, '@aurignybeta.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Aurigny') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@aurignybeta.com") or contains(.,"Aurigny.com") or contains(.,"aurigny.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.aurigny.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $type = '';

        $f = $email->add()->flight();

        if ($this->http->XPath->query("//tr[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Depart:'))}] and ./following-sibling::*[normalize-space(.)][2] ]")->length > 0) {
            $type = '1';
            $this->parseEmail_1($f);
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Depart'))}]/ancestor::table[1]")->length > 0) {
            $type = '3';
            $this->parseEmail_3($f);
        } else {
            $type = '2';
            $this->parseEmail_2($f);
        }

        // p.currencyCode
        // p.total
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total fare, cost and fees'))}]/following::text()[normalize-space(.)][1]");

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)) {
            // £413.94
            $f->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']))
            ;
        }

        $email->setType('BookingReference' . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2;
    }

    private function parseEmail_1(Flight $f)
    {
        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[normalize-space(.)][1]", null, true, '/^[A-Z\d]{5,}$/');
        $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        $passengers = [];

        // segments
        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Arrive:'))}]/ancestor::tr[ ./following-sibling::*[normalize-space(.)] ][1]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $xpathFragmentFlight = "./ancestor::td[ ./preceding-sibling::*[normalize-space(.)] ][1]/preceding-sibling::*[normalize-space(.)][1]/descendant::text()[normalize-space(.)]";

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode($xpathFragmentFlight . '[1]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            // travellers
            // seats
            if ($flight) {
                $seats = [];
                $passengerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space(.)='{$flight}']/following::tr[normalize-space(.)][1]/descendant::tr[not(.//tr) and count(./*)=3]");

                foreach ($passengerRows as $row) {
                    $passenger = $this->http->FindSingleNode('./*[1]', $row, true, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/');

                    if ($passenger) {
                        $passengers[] = $passenger;
                    }
                    $seat = $this->http->FindSingleNode('./*[3]', $row, true, '/^\d{1,5}[A-Z]$/');

                    if ($seat) {
                        $seats[] = $seat;
                    }
                }

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // status
            $status = $this->http->FindSingleNode($xpathFragmentFlight . '[2]', $segment, true, "/^{$this->opt($this->t('statusVariants'))}$/");
            $s->extra()->status($status, false, true);

            $date = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][1][ ./*[1][./descendant::text()[{$this->eq($this->t('Date:'))}]] ]/*[2]", $segment);

            $patterns['timeAirport'] = '/(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s*(?<airport>.{3,})/'; // 09:30 London Gatwick S

            $departure = $this->http->FindSingleNode("./preceding-sibling::*[normalize-space(.)][1][ ./*[1][./descendant::text()[{$this->eq($this->t('Depart:'))}]] ]/*[2]", $segment);

            if (preg_match($patterns['timeAirport'], $departure, $m)) {
                if ($date) {
                    $s->departure()->date2($date . ' ' . $m['time']);
                }
                $s->departure()
                    ->name($m['airport'])
                    ->noCode()
                ;
            }

            $arrival = $this->http->FindSingleNode("./*[2]", $segment);

            if (preg_match($patterns['timeAirport'], $arrival, $m)) {
                if ($date) {
                    $s->arrival()->date2($date . ' ' . $m['time']);
                }
                $s->arrival()
                    ->name($m['airport'])
                    ->noCode()
                ;
            }
        }

        // travellers
        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }
    }

    /**
     * @param type $f
     */
    private function parseEmail_2(Flight $f)
    {
        // confirmation number
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]");

        if (preg_match("/({$this->opt($this->t('Booking reference'))})\s*([A-Z\d]{5,})$/", $confirmationNumber, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        // reservationDate
        $bookingDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking made on:'))}]", null, true, "/{$this->opt($this->t('Booking made on:'))}\s*(.{6,})/");

        if ($bookingDate) {
            $f->general()->date2($this->normalizeDate($bookingDate));
        }

        $passengers = [];

        // segments
        $segments = $this->http->XPath->query("//tr[ count(./*)=4 and ./*[4]/descendant::text()[{$this->starts($this->t('Arr:'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./*[1]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            // travellers
            // seats
            if ($flight) {
                $seats = [];
                $passengerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers'))}]/following::tr[ count(./*)=3 and ./*[1]/descendant::text()[normalize-space(.)='{$flight}'] ]");

                foreach ($passengerRows as $row) {
                    $passenger = $this->http->FindSingleNode('./*[2]', $row, true, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/');

                    if ($passenger) {
                        $passengers[] = $passenger;
                    }
                    $seat = $this->http->FindSingleNode('./*[3]', $row, true, '/^\d{1,5}[A-Z]$/');

                    if ($seat) {
                        $seats[] = $seat;
                    }
                }

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // depName
            // arrName
            $route = $this->http->FindSingleNode('./*[2]', $segment);

            if (preg_match("/^(.{3,}){$this->opt($this->t(' to '))}(.{3,})$/", $route, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->noCode()
                ;
                $s->arrival()
                    ->name($m[2])
                    ->noCode()
                ;
            }

            $date = 0;
            $dateText = $this->http->FindSingleNode('./*[3]', $segment) ?? '';
            $dateDep = $this->normalizeDate($dateText);

            if (!empty($f->getReservationDate()) && $dateDep) {
                $date = EmailDateHelper::parseDateRelative($dateDep, $f->getReservationDate());
            }

            // depDate
            $timeDep = $this->http->FindSingleNode("./*[4]/descendant::text()[{$this->starts($this->t('Dep:'))}]", $segment, true, "/^{$this->opt($this->t('Dep:'))}\s*({$this->patterns['time']})$/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            // arrDate
            $timeArr = $this->http->FindSingleNode("./*[4]/descendant::text()[{$this->starts($this->t('Arr:'))}]", $segment, true, "/^{$this->opt($this->t('Arr:'))}\s*({$this->patterns['time']})$/");

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }
        }

        // travellers
        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }
    }

    private function parseEmail_3(Flight $f)
    {
        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[normalize-space(.)][1]", null, true, '/^[A-Z\d]{5,}$/');
        $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        $passengers = [];

        // segments
        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Arrive:'))}]/ancestor::tr[ ./following-sibling::*[normalize-space(.)] ][1]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./preceding::tr[1]/descendant::td[1]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            // travellers
            // seats
            if ($flight) {
                $seats = [];
                $passengerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space(.)='{$flight}']/following::tr[normalize-space(.)][1]/descendant::text()[{$this->starts($this->t('MR'))}]/ancestor::tr[1]");
            }

            foreach ($passengerRows as $row) {
                $passenger = $this->http->FindSingleNode('./*[1]', $row, true, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/');

                if ($passenger) {
                    $passengers[] = $passenger;
                }
                $seat = $this->http->FindSingleNode('./*[3]', $row, true, '/^\d{1,5}[A-Z]$/');

                if ($seat) {
                    $seats[] = $seat;
                }
            }

            if (count($seats)) {
                $s->extra()->seats($seats);
            }

            // status
            $status = $this->http->FindSingleNode('./td[1]', $segment, true, "/^{$this->opt($this->t('statusVariants'))}$/");
            $s->extra()
                ->status($status, false, true);
            $this->status[] = $status;

            $date = $this->http->FindSingleNode("./following::td[{$this->eq($this->t('Date:'))}][1]/following::td[1]", $segment, true, '/(\d+\/\d+\/\d{4})/');
            $patterns['timeAirport'] = '/(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)\s*(?<airport>.{3,})/'; // 09:30 London Gatwick S

            $departure = $this->http->FindSingleNode("./preceding::td[{$this->eq($this->t('Depart:'))}][1]/following::td[1]", $segment);

            if (preg_match($patterns['timeAirport'], $departure, $m)) {
                if ($date) {
                    $s->departure()->date2(str_replace("/", ".", $date) . ' ' . $m['time']);
                }
                $s->departure()
                    ->name($m['airport'])
                    ->noCode();
            }

            $arrival = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Arrive:'))}][1]/following::td[1]", $segment);

            if (preg_match($patterns['timeAirport'], $arrival, $m)) {
                if ($date) {
                    $s->arrival()->date2(str_replace("/", ".", $date) . ' ' . $m['time']);
                }
                $s->arrival()
                    ->name($m['airport'])
                    ->noCode();
            }
        }

        // travellers
        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }

        if (count(array_unique($this->status)) == 1 && $this->status[0] == 'Confirmed') {
            $f->general()
                ->status('Confirmed');
        }

        if (count(array_unique($this->status)) == 1 && $this->status[0] == 'Cancelled') {
            $f->general()
                ->status('Cancelled')
                ->cancelled();
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^[^\d\W]{2,}\s+(\d{1,2})\/(\d{1,2})\/(\d{4})$/u', $string, $matches)) {
            // Mon 25/09/2017
            if ((int) $matches[1] > 12) {
                $day = $matches[1];
                $month = $matches[2];
            } else {
                $month = $matches[1];
                $day = $matches[2];
            }
            $year = $matches[3];
        } elseif (preg_match('/^[^\d\W]{2,}\s+(\d{1,2})\s*([^\d\W]{3,})$/u', $string, $matches)) {
            // Sat 11 Nov
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
