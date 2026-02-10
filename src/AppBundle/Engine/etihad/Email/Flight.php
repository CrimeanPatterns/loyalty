<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "etihad/it-160434327.eml, etihad/it-22726064.eml, etihad/it-24895613.eml, etihad/it-30369808.eml, etihad/it-30447673.eml, etihad/it-40460950.eml, etihad/it-40486611.eml, etihad/it-40631487.eml";

    private $subjects = [
        'de' => ['Ihre Reisevorbereitung'],
        'it' => ['Preparati a viaggiare', 'Il check-in online è ora disponibile'],
        'fr' => ['Votre départ approche. Enregistrez-vous maintenant'],
        'en' => ['Get ready for your trip', 'Save time, check in now'],
    ];

    private $lang = '';

    private static $dict = [
        'de' => [
            'Booking reference' => 'Buchungsnummer',
            'Passenger(s)'      => 'Passagier(e)',
            'Operated by'       => 'Operato da',
            //            'Loyalty program #(s)' => '',
            'Status' => ['Gebucht'],
            // Pdf
//            'Document number:' => '',
        ],
        'it' => [
            'Booking reference' => ['Numero di prenotazione', 'Numero della prenotazione'],
            'Passenger(s)'      => 'Passeggero/i',
            'Operated by'       => 'Operato da',
            //            'Loyalty program #(s)' => '',
            'Status' => ['Prenotazione effettuata'],
        ],
        'fr' => [
            'Booking reference'    => 'Référence de la réservation',
            'Passenger(s)'         => 'Passager(s)',
            'Operated by'          => 'Exploité par',
            'Loyalty program #(s)' => 'Programme de fidélité #(s)',
            'Special request'      => 'Requête particulière',
            'Meal'                 => 'Repas',
            'Status'               => ['Confirmé'],
        ],
        'en' => [
            'Booking reference'    => 'Booking reference',
            'Passenger(s)'         => 'Passenger(s)',
            'Loyalty program #(s)' => ['Loyalty program #(s)', 'Frequent Flyer #(s)'],
            'Status'               => ['Booked', 'Confirmed'],
        ],
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);

        $pdfs = $parser->searchAttachmentByName('.*PBAReceiptPdf\.pdf');
//            $this->logger->debug('$body = '.print_r( $body,true));

        if (count($pdfs) == 1) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
            if (preg_match_all("/\ *{$this->opt($this->t('Document number:'))} *(\d{13})\s*\n/", $body, $m)) {
                if (!empty($email->getItineraries())) {
                    $email->getItineraries()[0]
                        ->issued()->tickets($m[1], false);
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Etihad Guest FAQ") or contains(normalize-space(.),"FAQ Etihad Guest") or contains(.,"@etihad.ae")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//my.bookings.etihad.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->http->XPath->query("//img[{$this->contains(['/flight_icon.'], '@src')} and {$this->contains(['etihad.com'], '@src')}]")->length > 0
            && $this->assignLang()
        ) {
            return true;
        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Etihad Airways') !== false
            || preg_match('/[.@]etihad\.[a-z]{2,4}/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): Email
    {
        $type = '';
        $patterns = [
            'time'          => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
            'travellerName' => '[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]](?:\s*\(.+?\))?', // Mr. Hao-Li Huang | Miss Aaradhya Jain (Child)
        ];

        $f = $email->add()->flight();

        if ($conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference'))}]/following::text()[normalize-space(.)!=''][1]")) {
            $f->general()->confirmation($conf);
        }

        // travellers
        // ticketNumbers
        // Case 1: passengers after flight info; $type = 1;
        $passengers = [];
        $seatsByPassengers = [];
        $passengerRows = $this->http->XPath->query("//table[ ./descendant::td[normalize-space(.)!=''][1][{$this->eq($this->t('Passenger(s)'))}] ]/following-sibling::table[normalize-space(.)!='']");

        foreach ($passengerRows as $row) {
            $passenger = $this->http->FindSingleNode('./descendant::tr[count(./*)=3]/*[1]', $row);

            if (preg_match("/^({$patterns['travellerName']})(?:\s*\(\w+\)\s*|\s+)(\d{3}[-\s]*\d{2,}[-\s]*\d{2})$/", $passenger, $m)) {
                // it-30447673.eml
                if ($str = trim(strstr($m[1], '(', true))) {
                    $m[1] = $str;
                }
                $passengers[] = $m[1];
//                $f->addTraveller($m[1]);
                $f->addTicketNumber($m[2], false);
            } elseif (preg_match("/^{$patterns['travellerName']}(?:\s*\(\w+\))?\s*$/", $passenger)) {
                if ($str = trim(strstr($passenger, '(', true))) {
                    $passenger = $str;
                }
                $passengers[] = $passenger;
            }
            $td2 = $this->http->FindSingleNode('./descendant::tr[count(./*)=3]/*[3]', $row);

            if (preg_match("/^\d{3}[-\s]*\d{2,}[-\s]*\d{2}$/", $td2)) {
                // it-22726064.eml, it-24895613.eml
                $f->addTicketNumber($td2, false);
            } elseif ($td2) {
                // it-30447673.eml, it-30369808.eml
                $seatsByPassengers[] = preg_split('/\s*,\s*/', $td2);
            }
        }
        if (!empty($passengers)) {
            $type = 1;
        }

        if (empty($passengers)) {
            // Case 2: passengers before flight info; $type = 2;
            $passengers = array_filter($this->http->FindNodes("//tr[*[normalize-space(.)][1][{$this->eq($this->t('Details'))}] and *[normalize-space(.)][2][{$this->eq($this->t('Total'))}]]/preceding-sibling::tr[normalize-space()][1]", null, "/^\s*(.+?)\s*(\(.+\)\s*)?$/"));
            if (!empty($passengers)) {
                $type = 2;
            }
        }
        $f->general()
            ->travellers(preg_replace("/^\s*(Mr|Miss|Mstr|Ms|Mrs)\s+/", '', $passengers), true);


        // accountNumbers
        $ffAccountRows = $this->http->XPath->query("//table[ ./descendant::td[normalize-space(.)!=''][1][{$this->eq($this->t('Loyalty program #(s)'))}] ]/following-sibling::table[normalize-space(.)!='']");

        foreach ($ffAccountRows as $row) {
            $ffNumber = $this->http->FindSingleNode('./descendant::tr[count(./*)=5]/*[3]', $row, true, "/^(?:[A-Z]+[-\s]*)?\d{7,}$/");

            if ($ffNumber) {
                // it-30369808.eml
                $f->addAccountNumber($ffNumber, false);
            }
        }

        $xpath = "//img[{$this->contains(['/flight_icon.'], '@src')} and {$this->contains(['etihad.com'], '@src')}]/ancestor::table[2]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);

        if (0 === $segments->length) {
            $this->logger->debug("Segments didn't found by xpath: {$xpath}");

            return $email;
        }

        $mealResult = [];

        if (($rootMeal = $this->http->XPath->query("//table[ ./descendant::td[normalize-space(.)!=''][1][{$this->eq($this->t('Special request'))}] ]/following-sibling::table[normalize-space(.)!='']/descendant::text()[{$this->contains($this->t('Meal'))}]"))->length > 0) {
            foreach ($rootMeal as $r) {
                $meal = $this->http->FindSingleNode(".", $r);
                $node = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]", $r);

                if (preg_match_all("#\(\s*([A-Z]{3})\s*\-\s*([A-Z]{3})\s*\)#", $node, $m, PREG_SET_ORDER)) {
                    foreach ($m as $v) {
                        $mealResult[$v[1] . '-' . $v[2]][] = $meal;
                    }
                }
            }
            $newRes = [];

            foreach ($mealResult as $key => $value) {
                $newRes[$key] = implode("|", array_unique($value));
            }
            $mealResult = $newRes;
        }

        $seatsResult = [];
        $countTripSegments = $segments->length;

        for ($i = 0; $i < $countTripSegments; $i++) {
            $seatsBySegment = [];

            foreach ($seatsByPassengers as $seats) {
                if (!empty($seats[$i]) && preg_match('/^\d{1,5}[A-Z]$/', $seats[$i])) {
                    $seatsBySegment[] = $seats[$i];
                }
            }
            $seatsResult[] = $seatsBySegment;
        }

        foreach ($segments as $key => $root) {
            $s = $f->addSegment();

            $ddate = $this->normalizeDate($this->getNode($root) ?? '');

            if (preg_match('/([A-Z]{3})\s+(.+)/', $this->getNode($root, 1, 2), $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($m[2]);
            }

            $adate = $this->normalizeDate($this->getNode($root, 2) ?? '');

            if (preg_match('/([A-Z]{3})\s*(.+)/', $this->getNode($root, 2, 2), $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->name($m[2]);
            }

            $dtime = $this->http->FindSingleNode("descendant::tr[count(td)=3][last()]/td[normalize-space(.)!=''][1]", $root, true, "/^({$patterns['time']})/");

            if ($ddate && $dtime) {
                $s->departure()
                    ->date(strtotime($ddate . ', ' . $dtime));
            }

            $atime = $this->http->FindSingleNode("descendant::tr[count(td)=3][last()]/td[normalize-space(.)!=''][last() and position()>1]", $root, true, "/^({$patterns['time']})/");

            if ($adate && $atime) {
                $s->arrival()
                    ->date(strtotime($adate . ', ' . $atime));
            }

            $terminalDep = $this->http->FindSingleNode("./descendant::tr[count(./*)=3][last()]/td[normalize-space(.)!=''][1]/descendant::td[not(.//td) and {$this->contains($this->t('Terminal'))}]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*([A-z\d ]+)$/");
            if (empty($terminalDep)) {
                $terminalDep = $this->http->FindSingleNode("./descendant::tr[count(./*)=3][last()]/td[normalize-space(.)!=''][1]/descendant::td[not(.//td)][starts-with(normalize-space(), 'T')]",
                    $root, true, "/^\s*T([A-z\d \-]+)$/");
            }
            $s->departure()->terminal($terminalDep, false, true);

            $terminalArr = $this->http->FindSingleNode("./descendant::tr[count(./*)=3][last()]/td[normalize-space(.)!=''][last() and position()>1]/descendant::td[not(.//td) and {$this->contains($this->t('Terminal'))}]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*([A-z\d ,]+)$/");
            if (empty($terminalArr)) {
                $terminalArr = $this->http->FindSingleNode("./descendant::tr[count(./*)=3][last()]/td[normalize-space(.)!=''][last() and position()>1]/descendant::td[not(.//td)][starts-with(normalize-space(), 'T')]",
                    $root, true, "/^\s*T([A-z\d \-]+)$/");
            }
            $s->arrival()->terminal($terminalArr, false, true);

            $xp = "descendant::table[count(descendant::tr)>=3][last()]";

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $this->http->FindSingleNode("{$xp}/descendant::tr[1]", $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (($cabin = $this->http->XPath->query("{$xp}/descendant::tr[normalize-space(.)!=''][last()]/descendant::text()[normalize-space()!='']",
                    $root))->length == 2
            ) {
                $i1 = $this->http->FindSingleNode(".", $cabin->item(0));
                $i2 = $this->http->FindSingleNode(".", $cabin->item(1));
                if (!preg_match("/^[A-Z\d\W]+$/", $i1 . $i2)) {
                    $s->extra()
                        ->cabin($i1)
                        ->status($i2);
                }
            } elseif (($cabin = $this->http->XPath->query("{$xp}/descendant::tr[normalize-space(.)!=''][last()]/descendant::text()[normalize-space()!='']",
                    $root))->length == 1) {
                $cabin = $this->http->FindSingleNode(".", $cabin->item(0));

                if (is_array($status_array = $this->t('Status')) && !preg_match("/^[A-Z\d\W]+$/", $cabin)) {
                    if (in_array($cabin, $status_array)) {
                        $s->extra()
                            ->status($cabin);
                    } elseif (preg_match("#(.+)\s+({$this->opt($status_array)})#", $cabin, $m) > 0) {
                        $s->extra()
                            ->cabin($m[1])
                            ->status($m[2]);
                    } elseif (preg_match("#(\w+)\s+(.+)#", $cabin, $m) > 0) {
                        $s->extra()
                            ->cabin($m[1])
                            ->status($m[2]);
                    } else {
                        $s->extra()
                            ->cabin($cabin);
                    }
                }
            }

            if (($aircraft = $this->http->XPath->query("{$xp}/descendant::tr[normalize-space(.)!=''][position()=2 and not({$this->starts($this->t('Operated by'))})]/descendant::text()[normalize-space()!='']", $root))->length === 1) {
                $s->extra()->aircraft($this->http->FindSingleNode(".", $aircraft->item(0)));
            }

            $operator = $this->http->FindSingleNode("{$xp}/descendant::tr[{$this->eq($this->t('Operated by'))}]/following-sibling::tr[normalize-space(.)!=''][1]", $root);

            if (!$operator) {
                $operator = $this->http->FindSingleNode("{$xp}/descendant::tr[{$this->contains($this->t('Operated by'))}]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");
            }

            if ($operator) {
                $s->airline()->operator($operator);
            }

            if (!empty($seatsResult[$key])) {
                $s->extra()->seats($seatsResult[$key]);
            } elseif ($type = 2 && !empty($s->getDepCode()) and !empty($s->getArrCode())) {
                $codes = $s->getDepCode() . '_' . $s->getArrCode();
                $seats = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), '{$codes}')]", null,
                    "/^\s*{$codes}\s*-\s*(\d{1,3}[A-Z])\s*$/"));
                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            if (!empty($mealResult) && $s->getDepCode() && $s->getArrCode()
                && isset($mealResult[$s->getDepCode() . '-' . $s->getArrCode()])) {
                $s->extra()->meal($mealResult[$s->getDepCode() . '-' . $s->getArrCode()]);
            }
        }

        return $email;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})[.\s]+(\d{4})$/u', $string, $matches)) {
            // 21 déc. 2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
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

    private function getNode(\DOMNode $root, int $td = 1, int $tr = 1, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("descendant::tr[count(td)=3][td[img]]/td[normalize-space(.)][{$td}]/descendant::tr[normalize-space(.)][{$tr}]", $root, true, $re);
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking reference'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
