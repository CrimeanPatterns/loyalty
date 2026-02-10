<?php

namespace AwardWallet\Engine\breeze\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightDelayed extends \TAccountChecker
{
    public $mailFiles = "breeze/it-268363232.eml, breeze/it-299631503.eml, breeze/it-328320815-2.eml, breeze/it-335674152-3.eml, breeze/it-339139729-4-changed.eml, breeze/it-493790373.eml, breeze/it-619770320.eml, breeze/it-661780577.eml, breeze/it-663420471.eml, breeze/it-711598302.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Confirmation Number:', 'CONFIRMATION NUMBER:', 'Confirmation #:'],
            'flight'         => ['Flight #', 'FLIGHT #', 'Flight', 'FLIGHT'],
            'direction'      => ['Outbound Trip', 'Return Trip', 'New Outbound Trip', 'New Return Trip', 'Outbound Flight',
                'Trip to ', 'New Trip to ', 'Return Flight', 'Trip Details', ],
            'statusPhrases'                            => ['YOUR FLIGHT RESERVATION IS', 'Your flight reservation is'],
            'statusVariants'                           => ['DELAYED', 'delayed', 'Delayed', 'ON TIME', 'on time', 'On time', 'CONFIRMED', 'confirmed', 'CHANGED', 'changed'],
            'guest∆'                                   => ['GUEST ∆', 'Guest ∆'],
            'guestNumber'                              => ['GUEST NUMBER', 'Guest Number', 'Breezy Rewards™', 'BREEZY REWARDS™'],
            'baseFare'                                 => ['BASE FARE', 'Base Fare'],
            'taxes'                                    => ['TAXES, FEES AND CHARGES', 'Taxes, Fees and Charges', 'Taxes and Carrier Fees'],
            'FlightSegmentNotCancelledStatusesDelayed' => ['Delayed', 'DELAYED', 'U P D A T E D', 'Updated'],
        ],
    ];

    private $flightNumbers;
    private $subjects = [
        'en' => ['Booking Confirmation -', 'Delay -', 'Nice! Your Breeze booking is confirmed'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flybreeze.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], ' Breeze ') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//img[contains(@src,"/breeze/images/logos/emailLogo.") or contains(@src,"/breeze/images/emails/red_plane.") or contains(@src,"/breeze/images/emails/plane.")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@flybreeze.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,".flybreeze.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightDelayed' . ucfirst($this->lang));

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status); // it-328320815-2.eml
        }

        $xpathConfNo = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('confNumber'))}] ]";

        $confirmations = array_filter($this->http->FindNodes($xpathConfNo . "/*[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/'));

        if (empty($confirmations)) {
            $xpathConfNoTemp = "//td[not(.//td)][{$this->starts($this->t('confNumber'))}]";
            $confirmations = array_filter($this->http->FindNodes($xpathConfNoTemp, null, "/^\s*{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})\s*$/"));

            if (!empty($confirmations)) {
                $xpathConfNo = $xpathConfNoTemp;
            }
        }

        if ($confirmations) {
            $confirmationTitle = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('confNumber'))}])[1]", null, true, "/^{$this->opt($this->t('confNumber'))}/");
            $confirmations = array_unique($confirmations);

            foreach ($confirmations as $conf) {
                $f->general()->confirmation($conf, rtrim($confirmationTitle, ': '));
            }
        }

        $xpathSegFilter = "not(ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][5]/descendant::*[{$this->eq($this->t('Original Trip'))}])"; // it-339139729-4-changed.eml

        $xpath = $xpathConfNo . "/following::tr[count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2][{$xpathSegFilter}]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            $xpath = $xpathConfNo . "/following::tr[count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2][{$xpathSegFilter}]";
            $segments = $this->http->XPath->query($xpath);
        }
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateVal = $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][5]/descendant::*[not(.//tr) and {$this->starts($this->t('direction'))}]/following-sibling::*[normalize-space()][1]", $root, true, "/^[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u"); // it-335674152-3.eml

            if (empty($dateVal)) {
                $dateVals = array_filter($this->http->FindNodes("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][5]/descendant::*[not(.//tr) and {$this->starts($this->t('direction'))}]/following-sibling::*[normalize-space()][1]", $root, "/^[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u"));

                if (count($dateVals) === 1) {
                    $dateVal = array_shift($dateVals);
                }
            }

            if (empty($dateVal)) {
                $dateVal = $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][4]/preceding::text()[normalize-space()][not({$this->eq($this->t('FlightSegmentNotCancelledStatusesDelayed'))})][not({$this->eq($this->t('Canceled'))})][1]", $root, true, "/^[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u");
            }

            if (empty($dateVal)) {
                $dateVal = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('direction'))}][1]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][1]", $root, true, "/^[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u");
            }

            if (empty($dateVal)) {
                $dateVal = $this->http->FindSingleNode("ancestor::table[1]/preceding-sibling::*[1][count(.//text()[normalize-space()]) < 3]//text()[normalize-space()][1]", $root, true, "/^[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u");
            }

            if (!empty($this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][4]/preceding::text()[normalize-space()][{$this->eq($this->t('Canceled'))}][1]", $root))) {
                $s->extra()
                    ->status('Cancelled')
                    ->cancelled();
            }

            $xpathDate = "preceding-sibling::tr[normalize-space()][not({$this->contains($this->t('New Departure'))} or {$this->contains($this->t('New Arrival'))})][1]";

            $dateDepVal = $this->http->FindSingleNode($xpathDate . "/*[normalize-space()][1][not({$this->contains($this->t('Boarding Time'))})]", $root, true, "/^.*\d.*$/") ?? $dateVal;
            $dateDep = strtotime($dateDepVal);
            $dateArrVal = $this->http->FindSingleNode($xpathDate . "/*[normalize-space()][2]", $root, true, "/^.*\d.*$/") ?? $dateVal;
            $dateArr = strtotime($dateArrVal);
            $timeDep = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/");

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            $patterns['flightNumber'] = "/^\s*(?:{$this->opt($this->t('flight'))}\s*)?((?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)(?<other>\s*\|\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*\d+)?(?: \D+)?)\s*$/";

            $xpathAirports = $xpathDate . "/preceding::tr[normalize-space()][1]/descendant-or-self::tr[ *[3] ][1]";

            $flightRow = $this->http->FindSingleNode($xpathAirports . "/preceding::tr[normalize-space()][3]", $root, true, $patterns['flightNumber'])
                ?? $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]", $root, true, $patterns['flightNumber']) // it-328320815-2.eml
                ?? $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][3]/*[1]", $root, true, $patterns['flightNumber']) // it-328320815-2.eml
                ?? $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][4]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][1]", $root, true, $patterns['flightNumber']) // it-335674152-3.eml
                ?? $this->http->FindSingleNode("(./preceding::text()[{$this->starts($this->t('direction'))}])[last()]/ancestor::tr[1]/following::tr[2]/descendant::text()[normalize-space()][1]", $root, true, $patterns['flightNumber'])
            ;

            if (preg_match($patterns['flightNumber'], $flightRow, $m)) {
                if (isset($m['other'])) {
                    $m[1] = preg_replace('/\s+/', '', $m[1]);
                    $flight = explode('|', preg_replace('/\s+/', '', $m[1]));

                    if (isset($this->flightNumbers[$m[1]])) {
                        $m['name'] = null;
                        $m['number'] = null;
                        $this->flightNumbers[$m[1]]++;

                        if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)$/', $flight[$this->flightNumbers[$m[1]]] ?? '', $mat)
                            || preg_match('/^(?<number>\d+)$/', $flight[$this->flightNumbers[$m[1]]] ?? '', $mat)
                        ) {
                            $m['name'] = $mat['name'] ?? '';
                            $m['number'] = $mat['number'];
                        }
                    } else {
                        $this->flightNumbers[$m[1]] = 0;
                    }
                }

                $s->airline()
                    ->name(empty($m['name']) ? 'Breeze Airways' : $m['name']) // hard-code
                    ->number($m['number'])
                ;
            }

            $duration = $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][4]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][2]", $root, true, "/^\d\s*[HM][\s\dHM]*$/i"); // it-335674152-3.eml

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('direction'))}]/ancestor::tr[1]/following::tr[2]/descendant::text()[normalize-space()][2]", $root, true, "/^\d\s*[HM][\s\dHM]*$/i");
            }

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][3]/*[2]", $root, true, "/^\d\s*[HM][\s\dHM]*$/i");
            }

            $s->extra()->duration($duration, false, true);

            if (preg_match("/\d\s*({$this->opt($this->t('statusVariants'))})$/", $flightRow, $m)) {
                $s->extra()->status($m[1]);
            }

            $patterns['nameCode'] = "/^((?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\))$/";

            $airportDep = $this->http->FindSingleNode($xpathAirports . "/*[1]", $root)
                ?? $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][1]", $root, true, $patterns['nameCode']) // it-335674152-3.eml
                ?? implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][position()<3]/*[1]", $root)) // it-328320815-2.eml
            ;

            if (empty($airportDep)) {
                $airportDep = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ')')][1]/ancestor::tr[1]/descendant::td[1]", $root);
            }

            $airportArr = $this->http->FindSingleNode($xpathAirports . "/*[3]", $root)
                ?? $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][2]", $root, true, $patterns['nameCode'])
                ?? implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][position()<3]/*[position()>1][last()]", $root))
            ;

            if (empty($airportArr)) {
                $airportArr = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ')')][1]/ancestor::tr[1]/descendant::td[2]", $root);
            }

            if (preg_match($patterns['nameCode'], $airportDep, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);
            } else {
                $s->departure()->name($m['name']);
            }

            if (preg_match($patterns['nameCode'], $airportArr, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);
            } else {
                $s->arrival()->name($m['name']);
            }

            if ($s->getDepCode() && $s->getArrCode()) {
                $routeFormats = [];

                foreach ((array) $this->t('to') as $phrase) {
                    $routeFormats[] = $s->getDepCode() . ' ' . $phrase . ' ' . $s->getArrCode();
                }

                $xpathRoute = "count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($s->getDepCode())}] and descendant::text()[normalize-space()][2][{$this->eq($s->getArrCode())}] or {$this->eq($routeFormats)}";
                $seats = array_filter($this->http->FindNodes("//tr[{$this->eq($this->t('Seats'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathRoute}] ]/*[normalize-space()][2]", null, "/^\d+[A-Z]$/"));

                if (count($seats) === 0) {
                    // it-335674152-3.eml
                    $seats = array_filter($this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathRoute}] ]/*[normalize-space()][2]", null, "/^{$this->opt($this->t('Seat:'))}\s*(\d+[A-Z])$/"));
                }

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/preceding::text()[contains(normalize-space(), 'Guest ')][1]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Guest '))}\s*\d+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");

                    if (empty($pax)) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/preceding::text()[contains(normalize-space(), 'Guest ')][1]/ancestor::div[2]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Guest '))}\s*\d+/");
                    }

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, false, false, $pax);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }
                $s->extra()->seats($seats);
            }
        }

        $xpathGuest = "//div[not(following-sibling::*[normalize-space()]) and {$this->starts($this->t('guest∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]";
        $xpathGuest2 = "//tr[not(preceding-sibling::*[normalize-space()]) and {$this->starts($this->t('guest∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]";
        $guestTitle = "{$this->starts($this->t('guest∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}";

        $travellers = $this->http->FindNodes($xpathGuest . "/preceding-sibling::div[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u");

        if (count(array_filter($travellers)) === 0) {
            // it-335674152-3.eml
            $travellers = $this->http->FindNodes($xpathGuest2 . "/following-sibling::tr[normalize-space()][1]/descendant::*[../self::tr and normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u");
        }

        if (count(array_filter($travellers)) === 0) {
            // it-335674152-3.eml
            $travellers = $this->http->FindNodes("//td[not(.//td)][{$guestTitle}]", null, "/^\s*{$this->opt(str_replace('∆', '', $this->t('guest∆')))}\s*\d*\s*({$patterns['travellerName']})$/u");
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        $accounts = $this->http->FindNodes($xpathGuest . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Loyalty #'))}] ]/*[normalize-space()][2]", null, "/^[-A-z\d]{5,}$/");

        if (count(array_filter($accounts)) === 0) {
            // it-335674152-3.eml
            $accounts = $this->http->FindNodes("//tr/*[normalize-space()][2][{$this->eq($this->t('guestNumber'))}]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ *[normalize-space()][2] ][1]/*[normalize-space()][2]", null, "/^\d{5,}$/");
        }

        if (count(array_filter($accounts)) === 0) {
            $accounts = $this->http->FindNodes("//td[not(.//td)][.//text()[{$this->eq($this->t('guestNumber'))}]]", null, "/^\s*{$this->opt($this->t('guestNumber'))}\s*(\d{5,})\s*$/");
        }

        if (count($accounts) > 0) {
            foreach (array_unique($accounts) as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/preceding::text()[contains(normalize-space(), 'Guest ')][1]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Guest '))}\s*\d+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");

                if (empty($pax)) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/preceding::text()[contains(normalize-space(), 'Guest ')][1]/ancestor::div[2]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Guest '))}\s*\d+/");
                }

                if (empty($pax)) {
                    $pax = null;
                }

                $title = array_values(array_filter($this->http->FindNodes("//text()[{$this->eq($account)}]/preceding::text()[normalize-space()][1]", null, "/.*Breezy Rewards.*/i")));
                $f->program()->account($account, false, $pax, $title[0] ?? null);
            }
            //$f->program()->accounts(array_unique($accounts), false);
        }

        $infants = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Infant'))}] ]/*[normalize-space()][2]", null, "/^{$patterns['travellerName']}$/u");

        if (count($infants) > 0) {
            $f->general()->infants(array_unique($infants), true);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Amount Paid'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) { // it-328320815-2.eml
            // $1358.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('baseFare'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[preceding-sibling::tr[*[1][{$this->eq($this->t('baseFare'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('taxes'))}]] and count(*[normalize-space()])=2]"
                . " | //tr[preceding-sibling::*[descendant::*[../self::tr and not(.//tr) and normalize-space()][1][{$this->eq($this->t('taxes'))}]] and following-sibling::*[descendant::*[../self::tr and not(.//tr) and normalize-space()][1][{$this->eq($this->t('Total Amount Paid'))}]] and count(*[normalize-space()])=2]"
                . " | //tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('taxes'))}] and following::tr[count(*[normalize-space()])=2][1][*[normalize-space()][1][{$this->eq($this->t('Total Amount Paid'))}]] ]" // it-339139729-4-changed.eml
            );

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['confNumber']) && !empty($phrases['flight'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['flight'])}]")->length > 0
                || !empty($phrases['flight'])
                && $this->http->XPath->query("//*[{$this->contains($phrases['direction'])}]")->length > 0
            ) {
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
