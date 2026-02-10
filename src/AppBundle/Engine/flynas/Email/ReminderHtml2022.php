<?php

namespace AwardWallet\Engine\flynas\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ReminderHtml2022 extends \TAccountChecker
{
    public $mailFiles = "flynas/it-589899689.eml, flynas/it-614157685-ar.eml, flynas/it-616845668.eml, flynas/it-619448951.eml, flynas/it-702502596.eml, flynas/it-704026788.eml, flynas/it-705395000.eml";

    public $lang = '';

    public static $dictionary = [
        'ar' => [
            'flight'            => ['رقم الرحلة', 'الرحلة'],
            'departure'         => ['المغادرة', 'الإقلاع'],
            'arrival'           => 'الوصول',
            'Hi'                => 'عزيزي',
            'confNumber'        => [':رقم الحجز الخاص بك', ': رقم الحجز الخاص بك', 'رقم الحجز الخاص بك:'],
            'Your'              => 'الخاص بك',
            'travelDate'        => [':تاريخ الرحلة', ': تاريخ الرحلة', 'تاريخ السفر:'],
            'departureFrom'     => [':رحلة المغادرة من', ': رحلة المغادرة من'],
            'Total travel time' => ['مدة الرحلة', 'إجمالي وقت الرحلة'],
            'to'                => 'إلى',
            // 'Stops' => '',
            // 'stop(s)' => '',
            'Airport'        => 'مطار',
            'Terminal'       => 'صالة',
            'Passenger Name' => 'اسم المسافر',
            // Price
            'Fare Details' => 'تفاصيل السعر',
            'Fare Price'   => 'أسعار التذاكر',
            'Sub Total'    => 'السعر الإجمالي',
            'Total'        => 'المجموع',

            'Your booking is eligible for' => 'حجزك يؤهلك للحصول على',
        ],
        'en' => [
            'flight'     => ['Flight'],
            'departure'  => ['Departure'],
            'arrival'    => 'Arrival',
            'Hi'         => ['Hi', 'Dear', 'Hello'],
            'confNumber' => [
                'Your Booking Reference:', 'Your Booking Reference :',
                'Booking reference:', 'Booking reference :',
            ],
            // 'Your' => '',
            'travelDate'    => ['Travel Date:', 'Travel Date :'],
            'departureFrom' => ['Departure from:', 'Departure from :'],
            // 'Total travel time' => '',
            // 'to' => '',
            // 'Stops' => '',
            // 'stop(s)' => '',
            // 'Airport' => '',
            // 'Terminal' => '',

            // 'Passenger Name' => '',
            // Price
            // 'Fare Details' => '',
            // 'Fare Price' => '',
            // 'Sub Total' => '',
            // 'Total' => '',

            // 'Your booking is eligible for' => '',
        ],
    ];

    private $detectors = [
        'ar' => ['تفاصيل الرحلة', 'تفاصيل رحلتك'],
        'en' => ['Flight Information', 'Flight details', 'Check in online now', 'Your Flight Details'],
    ];

    private $subjectNotUnique = [
        // en
        'Check In for flight',
        // ar
        'تم تأكيد حجزك رقم',
    ];
    private $subjectUnique = [
        // en
        'Your flight reminder from flynas ',
        'flynas Booking Confirmation',
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flynas.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjectUnique as $subj) {
            if (strpos($headers['subject'], $subj) !== false) {
                return true;
            }
        }

        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> '))) {
            foreach ($this->subjectNotUnique as $subj) {
                if (strpos($headers['subject'], $subj) !== false) {
                    return true;
                }
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".flynas.com/") or contains(@href,".flynas.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ReminderHtml2022' . ucfirst($this->lang));

        if (preg_match("/\b([A-Z]{3}) ?- ?([A-Z]{3})\b/", $parser->getSubject(), $m)) {
            $airportsFromSubject = [$m[1], $m[2]];
        } else {
            $airportsFromSubject = [];
        }

        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Passenger Name'))}]]/following-sibling::*[normalize-space()]/*[1]");

        if (count($travellers) > 0) {
            $f->general()->travellers($this->niceTravellers($travellers));
        } else {
            $traveller = null;
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null,
                "/^{$this->opt($this->t('Hi'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count($travellerNames) === 0) {
                $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Hi'))}]",
                    null,
                    "/(?:^|[,;:!?]\s*)({$this->patterns['travellerName']})[,\s]+{$this->opt($this->t('Hi'))}$/u"));
            }

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }
            $f->general()->traveller($this->niceTravellers($traveller));
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/')
        ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/preceding::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, "/^[\s:：]*(?:{$this->opt($this->t('Your'))}\s+)?(.+?)(?:\s+{$this->opt($this->t('Your'))})?[\s:：]*$/u");
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments1 = $this->http->XPath->query("//*[ count(*[normalize-space()])=3 and *[{$this->eq($this->t('flight'))}] and *[{$this->eq($this->t('departure'))}] ]/following-sibling::*[count(*[normalize-space()])=3]");
        $segments2 = $this->http->XPath->query("//*[ count(*[normalize-space()])=4 and *[{$this->eq($this->t('flight'))}] and *[{$this->eq($this->t('Stops'))}] ]/following-sibling::*[count(*[normalize-space()])=3]");
        $segments3 = $this->http->XPath->query("//*[ (count(*[normalize-space()])=6 or count(*[normalize-space()])=7) and */descendant::text()[normalize-space()][1][{$this->eq($this->t('flight'))}] and */descendant::text()[normalize-space()][1][{$this->eq($this->t('departure'))}] ]");

        if ($segments1->length > 0 && $segments2->length === 0 && $segments3->length === 0) {
            $this->logger->debug('Found segments type-1.');
            $this->parseSegments1($f, $segments1, $airportsFromSubject);
        } elseif ($segments1->length === 0 && $segments2->length > 0 && $segments3->length === 0) {
            $this->logger->debug('Found segments type-2.');
            $this->parseSegments2($f, $segments2);
        } elseif ($segments1->length === 0 && $segments2->length === 0 && $segments3->length > 0) {
            $this->logger->debug('Found segments type-3.');
            $this->parseSegments3($f, $segments3);
        }

        // Price
        $curPos = 2;
        $amountPos = 3;

        if (in_array($this->lang, ['ar'])) {
            $curPos = 3;
            $amountPos = 2;
        }
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Details'))}]/following::tr[not(.//tr)][*[1][{$this->eq($this->t('Total'))}]]/*[{$amountPos}]");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Details'))}]/following::tr[not(.//tr)][*[1][{$this->eq($this->t('Total'))}]]/*[{$curPos}]");

        if (!empty($total) && !empty($currency)) {
            $currency = preg_replace('/^\s*ريال سعودي\s*$/u', 'SAR', $currency);
            $f->price()
                ->currency($currency)
                ->total(PriceHelper::parse($total, $currency));

            $f->price()
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Details'))}]/following::tr[not(.//tr)][*[1][{$this->eq($this->t('Fare Price'))}]]/*[{$amountPos}]"), $currency));

            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Fare Details'))}]/following::tr[not(.//tr)][count(*) = 3][preceding::text()[{$this->eq($this->t('Fare Price'))}]][following::text()[{$this->eq($this->t('Total'))}]][not(.//text()[{$this->eq($this->t('Sub Total'))}])]");

            foreach ($feeNodes as $fRoot) {
                $f->price()
                    ->fee($this->http->FindSingleNode('*[1]', $fRoot),
                        PriceHelper::parse($this->http->FindSingleNode("*[{$amountPos}]", $fRoot), $currency)
                    );
            }
        }

        // Program
        $earned = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking is eligible for'))}]/ancestor::*[count(.//text()[normalize-space()]) > 1][1]",
            null, true, "/^\s*{$this->opt($this->t('Your booking is eligible for'))}\s*(\d+.*)/");

        if (!empty($earned)) {
            $f->program()
                ->earnedAwards($earned);
        }

        return $email;
    }

    public function niceTravellers($name)
    {
        return preg_replace("/^\s*(Mr|Ms|Mstr|Miss|Mrs)\.?\s+/i", '', $name);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    /**
     * @param string|null $text Unformatted string with terminal
     * @param string|array $label Simple string or array with terminal label
     */
    public static function normalizeTerminal(?string $text, $label = 'Terminal'): string
    {
        // used in flynas/BoardingPass2023Pdf, flynas/BookingHtml2016

        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            '/\s+/',
            '/^(?:' . self::opt($label) . '\s+)+([^\-–]+)$/iu',
            '/^([^\-–]+?)(?:\s+' . self::opt($label) . ')+$/iu',
        ];
        $out = [
            ' ',
            '$1',
            '$1',
        ];

        return preg_replace($in, $out, $text);
    }

    private function parseSegments1(Flight $f, \DOMNodeList $segments, array $airportsFromSubject): void
    {
        // examples: it-589899689.eml, it-614157685-ar.eml

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            $date = $route = $duration = null;

            $dateText = $this->http->FindSingleNode("preceding::node()[{$this->contains($this->t('travelDate'))}][1]/ancestor-or-self::tr[ descendant::text()[normalize-space()][2] ][1]", $root);

            if (preg_match("/^{$this->opt($this->t('travelDate'))}[:\s]*(.+\b\d{4})$/", $dateText, $m)
                || preg_match("/^(.+\b\d{4})[:\s]*{$this->opt($this->t('travelDate'))}$/", $dateText, $m)
            ) {
                $date = strtotime($m[1]);
            }

            if ($segments->length === 1) { // it-589899689.eml
                $routeText = $this->http->FindSingleNode("preceding::node()[{$this->contains($this->t('departureFrom'))}][1]/ancestor-or-self::tr[ descendant::text()[normalize-space()][2] ][1]", $root);

                if (preg_match("/^{$this->opt($this->t('departureFrom'))}\s*([^|]{5,}?)(?:\s*\||$)/", $routeText, $m)
                    || preg_match("/(?:^|\|\s*)([^|]{5,}?)\s*{$this->opt($this->t('departureFrom'))}$/", $routeText, $m)
                ) {
                    $route = $m[1];
                }

                if (preg_match("/{$this->opt($this->t('Total travel time'))}\s*[:]+\s*(\d[^|]*)$/", $routeText, $m)
                    || preg_match("/^([^|]*\d)\s*[:]+\s*{$this->opt($this->t('Total travel time'))}(?:\s*\||$)/", $routeText, $m)
                ) {
                    $duration = $m[1];
                }
            }

            $durationSeg = $this->http->FindSingleNode("preceding-sibling::*[normalize-space() and not(descendant::*[{$this->eq($this->t('departure'))}])]", $root);

            if (preg_match("/^(?<duration>[^|]+?\d)\s*[:]+\s*{$this->opt($this->t('Total travel time'))}\s*[|]+\s*(?<route>[^|]{5,})$/u", $durationSeg, $m)) {
                // دقيقة   25  ساعة    06 : مدة الرحلة    |   الدار البيضاء - جدة
                $route = $m['route'];
                $duration = $m['duration'];
            }

            $airports = preg_split('/(\s+[-]+\s+)+/', $route);

            if (count($airports) < 2) {
                $airports = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $route);
            }

            if (count($airports) === 2) {
                $s->departure()->name($airports[0]);
                $s->arrival()->name($airports[1]);
            }

            $s->extra()->duration($duration, false, true);

            $segType = 'A'; // it-589899689.eml

            $flight = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, $pattern = "/^((?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+))$/");

            if (!$flight) {
                $flight = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, $pattern);
                $segType = 'B'; // it-614157685-ar.eml
            }

            if (preg_match($pattern, $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $this->logger->debug('Type segment-' . $i . ': ' . $segType);

            $departure = $this->http->FindSingleNode("*[normalize-space()][2]", $root);

            $arrival = '';

            if ($segType === 'A') {
                $arrival = $this->http->FindSingleNode("*[normalize-space()][3]", $root);
            } elseif ($segType === 'B') {
                $arrival = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
            }

            // Terminal 1 – New Airport  - 15:20    |    Terminal 4  - 18:00
            $patterns['terminalTime'] = "/^(?<terminal>.+?)\s*[-–]+\s*(?<time>{$this->patterns['time']})/";

            // 04:35  -  الصالة 1 في المطار الجديد    |    09:50 - صالة 5
            $patterns['timeTerminal'] = "/^(?<time>{$this->patterns['time']})\s*[-–]+\s*(?<terminal>.+)$/";

            if (preg_match($patterns['terminalTime'], $departure, $m)
                || preg_match($patterns['timeTerminal'], $departure, $m)
            ) {
                $s->departure()->terminal(self::normalizeTerminal($m['terminal']))->date(strtotime($m['time'], $date));
            }

            if (preg_match($patterns['terminalTime'], $arrival, $m)
                || preg_match($patterns['timeTerminal'], $arrival, $m)
            ) {
                $s->arrival()->terminal(self::normalizeTerminal($m['terminal']))->date(strtotime($m['time'], $date));
            }

            if (!empty($airportsFromSubject[0]) && $i === 0) {
                $s->departure()->code($airportsFromSubject[0]);
            } elseif (!empty($airportsFromSubject[0]) && $i > 0) {
                $s->departure()->noCode();
            }

            if (!empty($airportsFromSubject[1]) && $i === $segments->length - 1) {
                $s->arrival()->code($airportsFromSubject[1]);
            } elseif (!empty($airportsFromSubject[1]) && $i < $segments->length - 1) {
                $s->arrival()->noCode();
            }
        }
    }

    private function parseSegments2(Flight $f, \DOMNodeList $segments): void
    {
        // examples: it-616845668.eml

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $route = $duration = null;

            $dateText = $this->http->FindSingleNode("preceding::node()[{$this->contains($this->t('travelDate'))}][1]/ancestor-or-self::tr[ descendant::text()[normalize-space()][2] ][1]", $root);

            if (preg_match("/^{$this->opt($this->t('travelDate'))}[:\s]*(.{2,15}\b\d{4})(?:\b|\D)/", $dateText, $m)) {
                $date = strtotime($m[1]);
            }

            if ($segments->length === 1) {
                $routeText = $this->http->FindSingleNode("preceding::node()[{$this->contains($this->t('Total travel time'))}][1]/ancestor-or-self::tr[ descendant::text()[normalize-space()][2] ][1]", $root);

                if (preg_match("/(?:^|\|\s*)([^|]{2,}\s+{$this->opt($this->t('to'))}\s+[^|]{2,}?)(?:\s*\||$)/", $routeText, $m)) {
                    $route = $m[1];
                }

                if (preg_match("/{$this->opt($this->t('Total travel time'))}\s*[:]+\s*(\d[^|]*)$/", $routeText, $m)) {
                    $duration = $m[1];
                }
            }

            $airports = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $route);

            if (count($airports) === 2) {
                $s->departure()->name($airports[0]);
                $s->arrival()->name($airports[1]);
            }

            $xpath = "*[normalize-space()][2]/descendant::*[ *[normalize-space()][3] ][1]";

            $flight = $this->http->FindSingleNode($xpath . "/*[normalize-space()][1]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $departure = $this->http->FindSingleNode($xpath . "/*[normalize-space()][2]", $root);
            $arrival = $this->http->FindSingleNode($xpath . "/*[normalize-space()][3]", $root);

            // JED22:15 - Terminal 1 – New Airport
            $pattern = "/^(?<code>[A-Z]{3})\s*(?<time>{$this->patterns['time']})\s*[-–]+\s*(?<terminal>.+)$/";

            if (preg_match($pattern, $departure, $m)) {
                $s->departure()->code($m['code'])->date(strtotime($m['time'], $date))->terminal(self::normalizeTerminal($m['terminal']));
            }

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()->code($m['code'])->date(strtotime($m['time'], $date))->terminal(self::normalizeTerminal($m['terminal']));
            }

            $stops = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/^(\d{1,3})\s*{$this->opt($this->t('stop(s)'))}$/i");
            $s->extra()->stops($stops, false, true)->duration($duration, false, true);
        }
    }

    private function parseSegments3(Flight $f, \DOMNodeList $segments): void
    {
        // examples: it-619448951.eml

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $route = $duration = null;

            $dateText = $this->http->FindSingleNode("preceding::node()[{$this->contains($this->t('travelDate'))}][1]/ancestor-or-self::tr[ descendant::text()[normalize-space()][2] ][1]", $root);

            if (preg_match("/^{$this->opt($this->t('travelDate'))}[:\s]*(.{2,15}\b\d{4})(?:\b|\D)/", $dateText, $m)) {
                $date = strtotime($m[1]);
            }

            if ($segments->length === 1) {
                $routeText = $this->http->FindSingleNode("preceding::node()[{$this->contains($this->t('Total travel time'))}][1]/ancestor-or-self::tr[ descendant::text()[normalize-space()][2] ][1]", $root);

                if (preg_match("/(?:\b\d{4}|\|)\s*([^|]{2,}\s+{$this->opt($this->t('to'))}\s+[^|]{2,}?)(?:\s*\||$)/u", $routeText, $m)) {
                    $route = $m[1];
                }

                if (preg_match("/{$this->opt($this->t('Total travel time'))}\s*[:]+\s*(\d[^|]*)$/u", $routeText, $m)) {
                    $duration = $m[1];
                }
            }

            $airports = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $route);

            if (count($airports) === 2) {
                $s->departure()->name($airports[0]);
                $s->arrival()->name($airports[1]);
            }

            $s->departure()->code($this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^[A-Z]{3}$/"));
            $s->arrival()->code($this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/^[A-Z]{3}$/"));

            $flight = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/^{$this->opt($this->t('flight'))}\s*(.+)$/");

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $departure = $this->http->FindSingleNode("*[normalize-space()][5]", $root, true, "/^{$this->opt($this->t('departure'))}\s*(.+)$/");
            $arrival = $this->http->FindSingleNode("*[normalize-space()][6]", $root, true, "/^{$this->opt($this->t('arrival'))}\s*(.+)$/");

            // DUBAI 20:45
            // RUH16:20 - Terminal 2
            // Riyadh 16:25 King Khalid International Airport - Terminal 3
            // Jeddah 20:25 King Abdulaziz International Airport Terminal 1 – New Airport
            $pattern = "/^(?<city>.{2,}?)\s*(?<time>{$this->patterns['time']})\s*(?<city2>\S.+)?\s*$/u";

            if (preg_match($pattern, $departure, $m)) {
                $s->departure()->date(strtotime($m['time'], $date));

                $depName = $s->getDepName();

                if (empty($s->getDepName()) || mb_strlen($s->getDepName()) < mb_strlen($m['city'])) {
                    $depName = $m['city'];
                }

                if (!empty($m['city2'])) {
                    $containAirport = false;
                    $containTerminal = false;

                    if (preg_match("/{$this->opt($this->t('Airport'))}/u", $m['city2'] ?? '', $mat)) {
                        $containAirport = true;
                    }

                    if (preg_match("/{$this->opt($this->t('Terminal'))}/u", $m['city2'] ?? '', $mat)) {
                        $containTerminal = true;
                    }

                    if ($containAirport && $containTerminal) {
                        if (preg_match("/^\s*(?<airport>.*{$this->opt($this->t('Airport'))}.*)\s+[-–]\s+(?<terminal>.*{$this->opt($this->t('Terminal'))}.*)\s*$/u",
                                $m['city2'] ?? '', $mat)
                            || preg_match("/^\s*(?<airport>.*{$this->opt($this->t('Airport'))}.*)\s+(?<terminal>{$this->opt($this->t('Terminal'))}\s*\S.*)\s*$/u",
                                $m['city2'] ?? '', $mat)
                        ) {
                            $s->departure()
                                ->terminal(self::normalizeTerminal($mat['terminal'], $this->t('Terminal')));
                            $depName .= ', ' . trim($mat['airport']);
                        }
                    } elseif ($containAirport) {
                        $depName .= ', ' . trim($m['city2']);
                    } elseif ($containTerminal) {
                        $s->departure()
                            ->terminal(self::normalizeTerminal($m['city2'], $this->t('Terminal')));
                    }
                }

                $s->departure()->name($depName);
            }

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()->date(strtotime($m['time'], $date));

                $arrName = $s->getArrName();

                if (empty($s->getArrName()) || mb_strlen($s->getArrName()) < mb_strlen($m['city'])) {
                    $arrName = $m['city'];
                }

                if (!empty($m['city2'])) {
                    $containAirport = false;
                    $containTerminal = false;

                    if (preg_match("/{$this->opt($this->t('Airport'))}/u", $m['city2'] ?? '', $mat)) {
                        $containAirport = true;
                    }

                    if (preg_match("/{$this->opt($this->t('Terminal'))}/u", $m['city2'] ?? '', $mat)) {
                        $containTerminal = true;
                    }

                    if ($containAirport && $containTerminal) {
                        if (preg_match("/^\s*(?<airport>.*{$this->opt($this->t('Airport'))}.*)\s+[-–]\s+(?<terminal>.*{$this->opt($this->t('Terminal'))}.*)\s*$/u",
                                $m['city2'] ?? '', $mat)
                            || preg_match("/^\s*(?<airport>.*{$this->opt($this->t('Airport'))}.*)\s+(?<terminal>{$this->opt($this->t('Terminal'))}\s*\S.*)\s*$/u",
                                $m['city2'] ?? '', $mat)
                        ) {
                            $s->arrival()
                                ->terminal(self::normalizeTerminal($mat['terminal'], $this->t('Terminal')));
                            $arrName .= ', ' . trim($mat['airport']);
                        }
                    } elseif ($containAirport) {
                        $arrName .= ', ' . trim($m['city2']);
                    } elseif ($containTerminal) {
                        $s->arrival()
                            ->terminal(self::normalizeTerminal($m['city2'], $this->t('Terminal')));
                    }
                }

                $s->arrival()->name($arrName);
            }

            $s->extra()->duration($duration, false, true);

            $flightNumber = trim($s->getAirlineName() . ' ' . $s->getFlightNumber());

            if (!empty($flightNumber)) {
                $seatXpath = "//tr[*[1][{$this->eq($this->t('Passenger Name'))}]]/following-sibling::*[normalize-space()]";
                $seatsNodes = $this->http->XPath->query($seatXpath);

                foreach ($seatsNodes as $sRoot) {
                    $name = $this->niceTravellers($this->http->FindSingleNode('*[1]', $sRoot));
                    $flights = $this->http->FindNodes('*[2]//text()[normalize-space()]', $sRoot);
                    $fSeats = $this->http->FindNodes('*[3]//text()[normalize-space()]', $sRoot);
                    $values = array_combine($flights, $fSeats);
                    $values = array_filter($values, function ($v) { return ($v !== '-') ? true : false; });

                    if (!empty($values) && count($flights) == count($fSeats) && isset($values[$flightNumber])) {
                        $s->extra()
                            ->seat($values[$flightNumber], true, true, $name);
                    }
                }
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['flight']) || empty($phrases['departure'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[ */descendant::text()[normalize-space()][1][{$this->eq($phrases['flight'])}] and */descendant::text()[normalize-space()][1][{$this->eq($phrases['departure'])}] ]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
