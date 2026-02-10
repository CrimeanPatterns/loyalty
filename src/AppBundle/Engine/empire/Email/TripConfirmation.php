<?php

namespace AwardWallet\Engine\empire\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TripConfirmation extends \TAccountChecker
{
    public $mailFiles = "empire/it-281404539.eml, empire/it-294491316.eml, empire/it-357054358.eml, empire/it-87285219.eml, empire/it-87285244.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'TRIP CONFIRMATION' => ['TRIP CONFIRMATION', 'TRIP RECEIPT'],
            'STATUS:'           => ['STATUS:'],
            'confNumber'        => ['Reservation#:', 'Reservation #:'],
            'Vehicle Type:'     => ['Vehicle Type:'],
            'passengerName'     => ['Passenger Name:', 'Passenger:'],
            'checkIn'           => ['Pickup Date:', 'Pickup Date :', 'Pickup Date/Time:'],
            'statusVariants'    => ['ACTIVE', 'HOLD', 'COMPLETED'],
            'totalCharges'      => ['Total:', 'Total Charges:'],
            'baseCharges'       => ['Base Flat Charge:', 'Trip Charges:'],
        ],
    ];

    // used in parser empire/TripConfirmationPdf
    public static $detectProvider = [
        'aaawt' => [
            '@aaaworldwidetrans.com',
            'www.aaaworldwidetrans.com',
        ],
        'aambassador' => [
            '@aambassador.com',
            'www.aambassador.com',
        ],
        'miramar' => [
            '@mtsbahamas.com',
            'www.mtsbahamas.com',
            'MIRAMAR TRANSPORTATION',
        ],
        'etg' => [
            'www.etgweb.com',
            '@executivecharge.com',
        ],
        'mears' => [
            '@mears.com',
            'www.mearsconnect.com',
        ],
        'gem' => [
            '@gemlimo.com',
            'www.gemlimo.com',
        ],
        'mylimo' => [
            '@mylimo.com',
            'www.mylimo.com',
        ],
        'bls' => [
            '@blsco.com',
            'www.blsco.com',
        ],
        'sterling' => [
            'sterlinglimoservice.com',
            '@sterlinglimoservice.com',
        ],
        'gracelimo' => [
            '@gracelimo.com',
            'gracelimo.com',
        ],
        'sts' => [
            '@nashvillelimo.com',
            '.nashvillelimo.com',
        ],
        'empire' => [ // always last!
            '@GreeleyTransport.com', // unknown provider: GREELEY TRANSPORTATION
            'www.GreeleyTransport.com',

            '@jjtransportation.com', // unknown provider: J&J Transportation
            'www.jjtransportation.com',

            '@shuttleexpress.com', // unknown provider: Shuttle Express
            'www.shuttleexpress.com',

            '@premiere-limo.com', // unknown provider: Premiere Limousine
            'www.premiere-limo.com',

            '@rudylimo.com', // unknown provider: Rudy's Transportation
            'www.rudylimo.com',

            '@awgambassador.com', // unknown provider: AWG Ambassador
            'www.awgambassador.com',

            '@lerostg.com', // unknown provider: Leros Point to Point
            'www.leroslimo.com',

            '@avalontrans.com', // unknown provider: Avalon Transportation
            'www.avalontrans.com',
            '//avalontrans.com',

            '@goriteway.com', // unknown provider: GO Riteway
            'www.goriteway.com',

            '@rmalimo.com', // unknown provider: RMA Limo
            'www.rmalimo.com',

            '@bmclimo.co', // unknown provider: BRITISH MOTOR COACH
            'www.bmclimo.com',

            '@klsworldwide.com', // unknown provider: KLS WORLDWIDE
            'www.klsworldwide.com',

            '@hoytlivery.com', // unknown provider: HOYT LIVERY
            'www.hoytlivery.com',

            '@nplimo.com', // unknown provider: NORTH POINT GLOBAL TRANS
            'www.nplimo.com',

            '@localmotionofboston.com', // unknown provider: LOCAL MOTION OF BOSTON
            'www.localmotionofboston.com',

            '@ecslimo.com', // unknown provider: ECS TRANSPORTATION GROUP
            'www.ecslimo.com',

            '@trophylimo.com', // unknown provider: TROPHY LIMOUSINE WORLDWIDE
            'www.TrophyLimo.com',

            '@ztrip.com', // unknown provider: WHC KCL, LLC DBA KCTG-CAREY KC
            'www.kctg.com',

            '@sunnysworldwide.com', // unknown provider: SUNNY'S WORLDWIDE
            'www.sunnylimo.com',

            '@seattlelimo.com', // unknown provider: SEATTLE LIMO
            'www.seattlelimo.com',

            '@belllimousine.com', // unknown provider: BELL LIMOUSINE
            'www.belllimousine.com',
            '@belltransportation.com',

            '@belllimousine.com', // unknown provider: ARROW
            'www.belllimousine.com',
            '@belltransportation.com',

            '@arrowlimo.com', // unknown provider: CLASSIC LUXURY TRANSPORTATION
            'www.arrowlimo.com',

            '@empirecls.com',
            'www.empirecls.com',
        ],
    ];

    private $subjects = [
        'en' => ['Trip Confirmation - ', 'Trip Change - '],
    ];

    private $detectors = [
        'en'  => ['TRIP CONFIRMATION', 'Click here to track your Driver on'],
        'en2' => ['TRIP RECEIPT', 'Click here to track your Driver on'],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@empirecls.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detected = false;

        foreach (self::$detectProvider as $pDetects) {
            foreach ($pDetects as $p) {
                if (stripos($headers['from'], $p) !== false) {
                    $detected = true;

                    break 2;
                }
            }
        }

        if ($detected !== true) {
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
        $detected = false;

        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query("//tr[*[normalize-space()][1][{$this->eq($dict['TRIP CONFIRMATION'])}]]"
                    . "/following::tr[normalize-space()][1][*[normalize-space()][1][{$this->eq($dict['confNumber'])}]][*[normalize-space()][3][{$this->eq($dict['Vehicle Type:'])}]]"
                    . "/following::tr[normalize-space()][1][*[normalize-space()][1][{$this->eq($dict['passengerName'])}]]")->length > 0
            ) {
                $detected = true;

                break;
            }
        }

        if ($detected !== true) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $body = str_replace(["&nbsp;"], "", $this->http->Response['body']);

        $this->http->SetBody($body);
        $providerCode = '';

        foreach (self::$detectProvider as $pCode => $pDetects) {
            if ($this->http->XPath->query('//*[' . $this->contains($pDetects) . ']')->length > 0) {
                $providerCode = $pCode;

                break;
            }
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TripConfirmation' . ucfirst($this->lang));

        $this->parseTransfer($email);

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

    private function parseTransfer(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'\/’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $tf = $email->add()->transfer();

        $status = $this->http->FindSingleNode("//td[{$this->eq($this->t('TRIP CONFIRMATION'))}]/following-sibling::td[{$this->starts($this->t('STATUS:'))}]", null, true, "/{$this->opt($this->t('STATUS:'))}[*\s]*({$this->opt($this->t('statusVariants'))})[*\s]*$/i");

        if (!empty($status)) {
            $tf->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^([A-Z\d]{4,})(?:[ ]*\*|$)/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $tf->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellerShift = $this->http->XPath->query("//tr/*[normalize-space()][1][{$this->eq($this->t('passengerName'))}]/preceding-sibling::*")->length + 1;

        $travellerParts = [];
        $travellerParts[] = $this->http->FindSingleNode("//tr[ *[{$travellerShift}][{$this->eq($this->t('passengerName'))}] ]/*[{$travellerShift}+1]", null, true, "/^{$patterns['travellerName']}$/u");
        $passengerRows = $this->http->XPath->query("//tr[ *[{$travellerShift}][{$this->eq($this->t('passengerName'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($passengerRows as $pRow) {
            $travellerName = $this->http->FindSingleNode("*[{$travellerShift}+1]", $pRow, true, "/^{$patterns['travellerName']}$/u");

            if (!$travellerName || $this->http->FindSingleNode("*[{$travellerShift}]", $pRow, false) !== null) {
                break;
            }
            $travellerParts[] = $travellerName;
        }

        if (count(array_filter($travellerParts))) {
            $tf->general()->traveller(implode(' ', $travellerParts));
        } else {
            $passName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TRIP')]/following::text()[starts-with(normalize-space(), 'Passenger') and contains(normalize-space(), 'Name:')][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "#^{$patterns['travellerName']}$#u");
            $passSurname = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TRIP')]/following::text()[starts-with(normalize-space(), 'Passenger') and contains(normalize-space(), 'Name:')][1]/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][1]");

            if (!empty($passName) && !empty($passSurname)) {
                $tf->general()->traveller($passName . ' ' . $passSurname, true);
            }
        }

        $lastDate = null;
        $s = $tf->addSegment();

        $adults = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Pax:')]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/');
        $s->extra()->adults($adults, false, true);

        $pickupAddress = $this->http->FindSingleNode("//td[{$this->eq($this->t('Pickup Address:'))}]/following-sibling::td[1]");

        if (preg_match("/^(?<address>.{3,}?)\s*\[\s*Arrv\s*:\s*(?<time1>{$patterns['time']})\s*Dep\s*:\s*(?<time2>{$patterns['time']})\s*\]$/i", $pickupAddress, $m)) {
            // it-281404539.eml
            $pickupAddress = $m['address'];
            $timeArr = $m['time1'];
            $timeDep = $m['time2'];
        } else {
            $timeArr = $timeDep = null;
        }

        $startTime = $this->http->FindSingleNode("//td[{$this->eq($this->t('Start Time:'))}]/following-sibling::td[normalize-space()][1]", null, true, "/^{$patterns['time']}$/");
        $endTime = $this->http->FindSingleNode("//td[{$this->eq($this->t('End Time:'))}]/following-sibling::td[normalize-space()][1]", null, true, "/^{$patterns['time']}$/");
        $pickupDate = strtotime($this->http->FindSingleNode("//td[{$this->eq($this->t('Pickup Date:'))}]/following-sibling::td[normalize-space() and following-sibling::td[{$this->eq($this->t('Start Time:'))}]][1]", null, true, '/^.{6,}$/'));

        if (empty($pickupDate)) {
            $pickupDate = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Date:'))}]/following::text()[normalize-space()][1]"));
        }

        if (empty($pickupDate)) {
            $pickupDate = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup') and contains(normalize-space(), 'Date/Time:')]/following::text()[normalize-space()][1]"));
        }
        $pickupTime = $timeArr ?? $startTime ?? $this->http->FindSingleNode("//td[{$this->eq($this->t('Pickup Time:'))}]/following-sibling::td[normalize-space() and following-sibling::td[{$this->eq($this->t('End Time:'))}]][1]", null, true, "/^{$patterns['time']}$/");

        if (empty($pickupTime)) {
            $pickupTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Time:'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match("/^(?<location>.+?)\s*{$this->opt($this->t('Arriving from'))}\s+[A-Z]{3}(?:\s+{$this->opt($this->t('at'))}\s*:\s*(?<time>.{3,}))?$/", $pickupAddress, $m)) {
            if (preg_match("/^(?<location>.{3,}?)\s*,[^,]+{$this->opt($this->t('Flight'))}\s*#\s*\d+/", $m['location'], $m2)) {
                $pickupAddress = $m2['location'];
            } else {
                $pickupAddress = $m['location'];
            }

            if (!empty($m['time']) && preg_match("/^{$patterns['time']}$/", $m['time'])) {
                $pickupTime = $m['time'];
            }
        }

        //^(?<location>[A-Z]{3}\s*\(.*(?:Airport|Intl)\))\s*.*(?:Flight)\s*#\s*[\dA-Z]+(?:\s+(?:at)\s*:\s*(?<time>[\d\:]+\s*A?P?M?))?
        //if (preg_match("/^(?<location>[A-Z]{3}\s*\(.*Airport\))\s*.*{$this->opt($this->t('Flight'))}\s*#\s*\d+(?:\s+{$this->opt($this->t('at'))}\s*:\s*(?<time>.{3,}))?$/i", $pickupAddress, $m)) {
        if (preg_match("/^(?<location>[A-Z]{3}\s*\(.*(?:Airport|Intl)\))\s*.*(?:Flight)\s*#\s*[\dA-Z]+(?:\s+(?:at)\s*:\s*(?<time>[\d\:]+\s*A?P?M?))?/i", $pickupAddress, $m)) {
            $pickupAddress = $m['location'];

            if (!empty($m['time']) && preg_match("/^{$patterns['time']}$/", $m['time'])) {
                $pickupTime = $m['time'];
            }
        }
        $s->departure()->address($pickupAddress);

        if ($pickupDate && $pickupTime) {
            $s->departure()->date(strtotime($pickupTime, $pickupDate));
            $lastDate = $s->getDepDate();
        }

        /* [2023-02-27]: Currently not relevant!
        $stopRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[{$this->starts($this->t('Pickup Address:'))}] and following-sibling::tr[{$this->starts($this->t('Dropoff Address:'))}] and {$this->starts($this->t('Stop'))} ]");

        foreach ($stopRows as $i => $sRow) {
            $stopAddress = implode(' ', $this->http->FindNodes("descendant-or-self::tr/*[not(.//tr) and {$this->starts($this->t('Stop'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $sRow));
            $s->arrival()->address($stopAddress)->noDate();

            $s = $tf->addSegment();
            $s->extra()->adults($adults, false, true);
            $s->departure()->address($stopAddress);

            if ($stopRows->length > ($i + 1) && !empty($lastDate)) {
                $s->departure()->date(strtotime('+' . ($i + 1) . ' minutes', $lastDate)); // dirty hack
            } else {
                $s->departure()->noDate();
            }
        }
        */

        $dropoffAddress = $this->http->FindSingleNode("//td[{$this->eq($this->t('Dropoff Address:'))}]/following-sibling::td[1]");
        $dropoffTime = null;

        if (preg_match("/^(?<location>.+?)\s*(?:{$this->opt($this->t('Departing to'))}|{$this->opt($this->t('Arriving from'))})\s+[A-Z]{3}(?:\s+{$this->opt($this->t('at'))}\s*:\s*(?<time>.{3,}))?$/", $dropoffAddress, $m)) {
            if (preg_match("/^(?<location>.{3,}?)\s*,[^,]+{$this->opt($this->t('Flight'))}\s*#\s*\d+/", $m['location'], $m2)) {
                $dropoffAddress = $m2['location'];
            } else {
                $dropoffAddress = $m['location'];
            }

            if (!empty($m['time']) && preg_match("/^{$patterns['time']}$/", $m['time'])) {
                $dropoffTime = $m['time'];
            }
        }

        if (stripos($dropoffAddress, ', Flight# ') !== false) {
            $dropoffAddress = preg_replace("/\,\s*Flight[#].+/", "", $dropoffAddress);
        }

        $s->arrival()->address($dropoffAddress);

        if (!$dropoffTime) {
            $dropoffTime = $timeDep ?? $endTime;
        }

        if (!empty($lastDate) && $dropoffTime) {
            $dropoffDate = strtotime($dropoffTime, $lastDate);

            if (!empty($dropoffDate) && $lastDate > $dropoffDate) {
                $dropoffDate = strtotime('+1 days', $dropoffDate); // dirty hack
            }

            $s->arrival()->date($dropoffDate);
        } elseif (!$dropoffTime) {
            $s->arrival()->noDate();
        }

        $xpathPayment = "//tr[ *[{$this->eq($this->t('Reservation Detail'))}] and *[{$this->eq($this->t('Description'))}] and *[{$this->eq($this->t('Charges'))}] ]/following-sibling::tr[ *[normalize-space()][2] ]";

        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/*[{$this->eq($this->t('totalCharges'))}]/following-sibling::*[normalize-space()]");

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[normalize-space()='Total:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $212.93
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $tf->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode($xpathPayment . "/*[{$this->eq($this->t('baseCharges'))}]/following-sibling::*[normalize-space()]");

            if (empty($baseFare)) {
                $baseFare = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Base') and contains(normalize-space(), 'Flat')]/ancestor::tr[1]/descendant::td[normalize-space()][2]");
            }

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $m)) {
                $tf->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discount = $this->http->FindSingleNode($xpathPayment . "/*[{$this->eq($this->t('Discount:'))}]/following-sibling::*[normalize-space()]");

            if (preg_match('/^[(\s]*(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*?)[\s)]*$/', $discount, $m)) {
                // ($37.23)
                $tf->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query($xpathPayment . "[ preceding-sibling::tr[*[{$this->eq($this->t('baseCharges'))}]] and following-sibling::tr[*[{$this->eq($this->t('totalCharges'))}]] ]");

            if ($feeRows->length === 0) {
                $feeRows = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Base') and contains(normalize-space(), 'Flat')]/ancestor::tr[1]/following-sibling::tr");
            }

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][last()]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][last()-1]', $feeRow, true, "/^(.+?)[\s:]*$/");
                    $tf->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                /*&& $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0*/
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
