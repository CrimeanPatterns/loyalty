<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "avis/it-35814681.eml"; // +2 bcdtravel(html)[fr,en]

    private $subjects = [
        'fr' => ['Confirmation de réservation'],
        'en' => ['Reservation Confirmation', ', your reservation has been cancelled'],
    ];

    private $lang = '';

    private $langDetectors = [
        'fr' => ['SUCCURSALE DE PRISE EN CHARGE'],
        'en' => ['PICK UP LOCATION'],
    ];

    private static $dictionary = [
        'fr' => [
            'Thank you'           => 'Merci',
            'CONFIRMATION NUMBER' => 'VOTRE NUMÉRO DE CONFIRMATION',
            'PICK UP'             => 'COLLECTE',
            'DROP OFF'            => 'RESTITUTION',
            'YOUR CAR'            => 'VOTRE VOITURE',
            'similar'             => 'similaire',
            'ESTIMATED TOTAL'     => 'TOTAL ESTIMÉ',
            'PICK UP LOCATION'    => 'SUCCURSALE DE PRISE EN CHARGE',
            'DROP OFF LOCATION'   => 'SUCCURSALE DE RESTITUTION DU VÉHICULE',
            'at'                  => ['à', 'À'],
            //            'your reservation has been cancelled' => '',
        ],
        'en' => [
            'YOUR CAR'                       => ['YOUR CAR', 'YOUR VEHICLE'],
            'at'                             => ['at', 'At', 'AT'],
            'reservation has been cancelled' => ['reservation has been cancelled', 'reservation has been canceled'],
            'not a fee'                      => [
                'Base Rate', 'Amount Prepaid', // rate name
                'Surcharges/Fees', // fees array headers
                'Surcharges & GST', // fees array headers
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@e.avis.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === false) {
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
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Avis Rent A Car") or contains(.,"@e.avis.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//click.e.avis.com") or contains(@href,"//view.e.avis.com") or contains(@href,"avis@e.avis.com")]')->length === 0
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

            return null;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'phone'         => '/^(?:CALL: *)?[+(\d][-. \d)(]{5,}[\d)](?: Ext \d+)?$/', // +886-2-6620-6620 Ext 124
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'hours'         => '/(\d{1,2}\s*hrs|\d{1,2}:\d{2}|\b[12]\d{2}[05] - [12]\d{2}[05]\b)/i', // Mon 0645 - 1800;
        ];

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}])[1]/following::text()[string-length(normalize-space())>1][1]", null, true, '/^[A-Z\d]{5,}$/'))
        ;

        $renterNames = array_filter($this->http->FindNodes("(//text()[{$this->eq($this->t('CONFIRMATION NUMBER'))}])[1]/preceding::*[{$this->starts($this->t('Thank you'))}][1]", null, "/{$this->opt($this->t('Thank you'))}\s+({$patterns['travellerName']}),/i"));

        if (!empty($renterNames)) {
            $renterName = array_shift($renterNames);
        }

        if (empty($renterName)) {
            $renterNames = array_filter($this->http->FindNodes('//text()[' . $this->contains($this->t('reservation has been cancelled')) . '][1]', null, "/^\s*({$patterns['travellerName']}), (\w+ )*" . $this->opt($this->t('reservation has been cancelled')) . "/iu"));

            if (!empty($renterNames)) {
                $renterName = array_shift($renterNames);
            }
        }

        if (!empty($renterName)) {
            $r->general()
                ->traveller($renterName, false);
        }

        // Cancelled
        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t('reservation has been cancelled')) . "])[1]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        // Wed Dec 13, 2017 at 06:30 PM
        // Dim 14 Juil, 2019 à 1700
        // Sun, 11 Aug 2019 at 1400 hours
        $patterns['date'] = '\b(?:[[:alpha:]]{3,}\s+\d{1,2}\s*,\s*\d{4}|\d{1,2}\s+[[:alpha:]]{3,}\s*,?\s*\d{4})\b';
        // 10:30 AM    |    2100
        $patterns['time'] = '\b\d{1,2}:*\d{2}(?:\s*[AaPp][Mm])?\b';

        $re = "/(?<date>{$patterns['date']})\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})/";

        // Pick Up
        $datetimePickup = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('PICK UP'))}])[1]/following::text()[string-length(normalize-space())>1][1]/ancestor::*[self::td or self::div or self::p][1]");

        if (preg_match($re, $datetimePickup, $matches)) {
            $datePickup = $this->normalizeDate($matches['date']);
            $r->pickup()
                ->date(strtotime($datePickup . ', ' . $matches['time']));
        }

        $xpathFragment3 = "(//text()[{$this->eq($this->t('PICK UP LOCATION'))}])[1]/ancestor::tr[ following-sibling::tr[string-length(normalize-space())>1] ][1]";

        $pickupAddressTexts = $this->http->FindNodes($xpathFragment3 . '/following-sibling::tr[string-length(normalize-space(.))>1]');
        $pickupInfo = $pickupLocationTexts = [];

        foreach ($pickupAddressTexts as $i => $row) {
            $row = trim($row, ';, ');

            if (preg_match($patterns['phone'], $row) || preg_match($patterns['hours'], $row)) {
                $pickupLocation = implode(', ', $pickupLocationTexts);
                $r->pickup()->location($this->normalizeLocation($pickupLocation));
                $pickupInfo = array_slice($pickupAddressTexts, $i);

                break;
            } else {
                $pickupLocationTexts[] = $row;
            }
        }

        foreach ($pickupInfo as $i => $row) {
            $row = trim($row, ';, ');

            if (preg_match($patterns['phone'], $row)) {
                $r->pickup()
                    ->phone(preg_replace("/^\s*CALL:\s*/", '', $row));

                continue;
            }

            if (preg_match($patterns['hours'], $row)) {
                $r->pickup()
                    ->openingHours($row);

                continue;
            }

            if ($i + 1 === count($pickupInfo) && !empty($r->getPickUpPhone())) {
                $r->pickup()
                    ->openingHours($row);
            }
        }

        // Drop Off
        $datetimeDropoff = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('DROP OFF'))}])[1]/following::text()[string-length(normalize-space())>1][1]/ancestor::*[self::td or self::div or self::p][1]");

        if (preg_match($re, $datetimeDropoff, $matches)) {
            $dateDropoff = $this->normalizeDate($matches['date']);
            $r->dropoff()
                ->date(strtotime($dateDropoff . ', ' . $matches['time']));
        }

        $xpathFragment4 = "(//text()[{$this->eq($this->t('DROP OFF LOCATION'))}])[1]/ancestor::tr[ following-sibling::tr[string-length(normalize-space())>1] ][1]";
        $dropoffAddressTexts = $this->http->FindNodes($xpathFragment4 . '/following-sibling::tr[string-length(normalize-space(.))>1]');
        $dropoffInfo = $dropoffLocationTexts = [];

        foreach ($dropoffAddressTexts as $i => $row) {
            $row = trim($row, ';, ');

            if (preg_match($patterns['phone'], $row) || preg_match($patterns['hours'], $row)) {
                $dropoffLocation = implode(', ', $dropoffLocationTexts);
                $r->dropoff()->location($this->normalizeLocation($dropoffLocation));
                $dropoffInfo = array_slice($dropoffAddressTexts, $i);

                break;
            } else {
                $dropoffLocationTexts[] = $row;
            }
        }

        foreach ($dropoffInfo as $i => $row) {
            $row = trim($row, ';, ');

            if (preg_match($patterns['phone'], $row)) {
                $r->dropoff()
                    ->phone(preg_replace("/^\s*CALL:\s*/", '', $row));

                continue;
            }

            if (preg_match($patterns['hours'], $row)) {
                $r->dropoff()
                    ->openingHours($row);

                continue;
            }

            if ($i + 1 === count($dropoffInfo) && !empty($r->getDropOffPhone())) {
                $r->dropoff()
                    ->openingHours($row);
            }
        }

        $xpathFragment1 = "(//text()[{$this->eq($this->t('YOUR CAR'))}])[1]/ancestor::tr[ following-sibling::tr[string-length(normalize-space())>1] ][1]";

        // Car
        $r->car()
            ->image($this->http->FindSingleNode($xpathFragment1 . '/following-sibling::tr[./descendant::img][1]/descendant::img[1]/@src', null, true, "/.+:\/\/.+/"), true, true)
            ->model($this->http->FindSingleNode($xpathFragment1 . "/following-sibling::tr[{$this->contains($this->t('similar'))}][1]"), true, true);

        $xpathFragment2 = "(//text()[{$this->eq($this->t('ESTIMATED TOTAL'))}])[1]/ancestor::tr[ following-sibling::tr[string-length(normalize-space())>1] ][1]";

        // Price
        $totalPayment = $this->http->FindSingleNode($xpathFragment2 . '/following-sibling::tr[string-length(normalize-space(.))>1 and not(contains(.,":"))][1]');

        if (preg_match('/^(?<currency>[^\d)(]+?)\s*(?<amount>\d[,.\'\d]*)/', $totalPayment, $matches)) {
            // $187.77
            $r->price()
                ->currency($this->currency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']))
            ;
            // Fees
            $feesRows = $this->http->XPath->query($xpathFragment2 . '/following-sibling::tr[contains(.,":")][1]/descendant::tr[contains(.,":") and not(.//tr)]');

            foreach ($feesRows as $feesRow) {
                $feeKey = $this->http->FindSingleNode('td[1]', $feesRow);
                $feeName = trim($feeKey, ': ');
                $feeValue = $this->http->FindSingleNode('td[last()]', $feesRow, true, '/^(?:' . preg_quote($matches['currency'], '/') . '\s*)?(\d[,.\'\d]*)$/');
                $feeCharge = $this->normalizeAmount($feeValue);

                if (preg_match("/^{$this->opt($this->t('Base Rate'))}(?:\:\s*.*)?$/i", $feeName) && $feeCharge !== null) {
                    $r->price()->cost($feeCharge);

                    continue;
                } elseif (preg_match("/{$this->opt($this->t('not a fee'))}\s*$/i", $feeName)) {
                    continue;
                }

                if ($feeName && $feeCharge !== null) {
                    $r->price()
                        ->fee($feeName, $feeCharge);
                }
            }
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeLocation(?string $s): string
    {
        // only for Milano Railway (V2D)
        $s = preg_replace("/^(.{3,}?)[;,\s]*{$this->opt($this->t('Car Pickup'))}.*$/i", '$1', $s);

        return $s;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $text)
    {
        if (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Dec 13, 2017
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})\s*,?\s*(\d{4})$/u', $text, $m)) {
            // 14 Juil, 2019
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            'C$'=> 'CAD',
            '$' => 'USD',
            '£' => 'GBP',
            '₹' => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
