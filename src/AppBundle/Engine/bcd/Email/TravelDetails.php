<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TravelDetails extends \TAccountChecker
{
    public $mailFiles = "bcd/it-25421916.eml, bcd/it-24703332.eml";

    private $subjects = [
        'fr' => ['Voyage de ', ' vous informe que le vol '],
        'en' => [' trip to ', ' would like you to know that flight '],
        'de' => ['Geschäftsreise', 'nach'],
    ];

    private $langDetectors = [
        'fr' => ['va bientôt partir en voyage. Voici tous les détails', 'un changement concernant le voyage'],
        'en' => ['is traveling soon – here are the details', 'There has been a change to'],
        'de' => ['geht bald auf eine Geschäftsreise', 'Hier sind die Details'],
    ];
    private $lang = '';
    private static $dict = [
        'fr' => [
            'travellers' => [
                [
                    'start' => 'Bonjour,',
                    'end'   => 'va bientôt partir en voyage. Voici tous les détails',
                ],
                [
                    'start' => 'Il y a un changement concernant le voyage de ',
                    'end'   => '.',
                ],
            ],
            // FLIGHT
            'Flight' => 'Vol',
            // HOTEL
            'Hotel'         => 'Hôtel',
            'Phone number:' => 'Numéro de téléphone:',
            'Arrival'       => 'Arrivée',
            'Departure'     => 'Départ',
            // CAR
            'Car' => 'Location de voiture',
        ],
        'en' => [
            'travellers' => [
                [
                    'start' => 'Hi,',
                    'end'   => 'is traveling soon – here are the details',
                ],
                [
                    'start' => 'There has been a change to ',
                    'end'   => "'s trip",
                ],
            ],
            'Phone number:' => ['Phone number:', 'Contact:'],
            'Arrival'       => ['Arrival', 'Check-In'],
            'Departure'     => ['Departure', 'Check-Out'],
            // CAR
            'Car' => 'Car Rental',
        ],
        'de' => [
            'travellers' => [
                [
                    'start' => 'Der',
                    'end'   => 'geht bald auf eine Geschäftsreise. Hier sind die Details',
                ],
            ],
            'Flight' => ['Flug'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'TripSource') !== false
            || stripos($from, '@info.tripsource.com') !== false;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"BCD Travel. All rights reserved")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TravelDetails' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2; // Itineraries +  Updated Itinerary
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'missingValue' => '--',
            'confNumber'   => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'phone'        => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $xpathFragmentHeader = './preceding::table[normalize-space(.)][1]/descendant::tr[normalize-space(.)][last()]/*[normalize-space(.)][last()]';
        $xpathFragmentTd1 = './descendant::tr[count(./*[normalize-space(.)])>1]/*[normalize-space(.)][1]/descendant::text()[normalize-space(.)]';
        $xpathFragmentTdLast = './descendant::tr[count(./*[normalize-space(.)])>1]/*[normalize-space(.)][last()]/descendant::text()[normalize-space(.)]';

        if (is_array($this->t('travellers'))) {
            foreach ($this->t('travellers') as $value) {
                if (isset($value['start']) && isset($value['end'])) {
                    $travellersText = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->contains($value['start']) . ' and ' . $this->contains($value['end']) . ']', null, true, '/' . $this->opt($value['start']) . '\s*(.+?)\s*' . $this->opt($value['end']) . '/');
                    $travellers = array_filter(preg_split('/\s*,\s*/', $travellersText));

                    if (!empty($travellers)) {
                        break;
                    }
                }
            }
        }

        /* FLIGHT */

        $flightSegments = $this->http->XPath->query('//tr[ count(./*)=3 and ./*[2][' . $this->eq($this->t('Flight')) . '] ]/ancestor::table[1]/following::table[normalize-space(.)][1]');

        if ($flightSegments->length > 0) {
            $f = $email->add()->flight();

            // confirmation number
            $f->general()->noConfirmation();

            // travellers
            if (!empty($travellers)) {
                $f->general()->travellers($travellers);
            }
        }
        $patterns['airport'] = '/'
            . '^(?<name>.{2,})$' // Tampa International Airport
            . '\s+^(?<code>[A-Z]{3}|' . $patterns['missingValue'] . ')$' // TPA
            . '\s+^(?:[A-Z]{2}|' . $patterns['missingValue'] . ')$' // US
            . '\s+^(?<date>.{6,})$' // 08-Oct-2018 | 11:15
            . '/m';

        foreach ($flightSegments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode($xpathFragmentHeader, $segment);

            if (preg_match('/(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            } elseif (preg_match('/' . $patterns['missingValue'] . '\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->noName()
                    ->number($matches['flightNumber']);
            }

            $departureTexts = $this->http->FindNodes($xpathFragmentTd1, $segment);
            $departureText = implode("\n", $departureTexts);

            if (preg_match($patterns['airport'], $departureText, $matches)) {
                if ($matches['code'] === $patterns['missingValue']) {
                    $f->removeSegment($s);

                    continue;
                }

                // depName
                // depCode
                // depDate
                $s->departure()
                    ->name($matches['name'])
                    ->code($matches['code'])
                    ->date2($this->normalizeDate($matches['date']));
            }

            $arrivalTexts = $this->http->FindNodes($xpathFragmentTdLast, $segment);
            $arrivalText = implode("\n", $arrivalTexts);

            if (preg_match($patterns['airport'], $arrivalText, $matches)) {
                if ($matches['code'] === $patterns['missingValue']) {
                    $f->removeSegment($s);

                    continue;
                }

                // arrName
                // arrCode
                // arrDate
                $s->arrival()
                    ->name($matches['name'])
                    ->code($matches['code'])
                    ->date2($this->normalizeDate($matches['date']));
            }
        }

        /* HOTEL */
        $hotelSegments = $this->http->XPath->query('//tr[ count(./*)=3 and ./*[2][' . $this->eq($this->t('Hotel')) . '] ]/ancestor::table[1]/following::table[normalize-space(.)][1]');
        $patterns['hotelInfo'] = '/'
            . '^(?<name>[^\n]{4,})\n' // RED LION HOTEL BELLEVUE
            . '\s*(?<address>.{3,})\n' // 11211 MAIN STREET US
            . '\s*' . $this->opt($this->t('Phone number:')) . '\s*(?<phone>' . $patterns['phone'] . ')?' // Phone number: 14254555240
            . '/s';
        $patterns['hotelDates'] = '/'
            . '\b' . $this->opt($this->t('Arrival')) // Arrival
            . '\s+(?<dateCheckIn>.{6,})' // Monday 03-Sep-2018
            . '\s+' . $this->opt($this->t('Departure')) // Departure
            . '\s+(?<dateCheckOut>.{6,})' // Wednesday 05-Sep-2018
            . '/s';

        foreach ($hotelSegments as $segment) {
            $h = $email->add()->hotel();

            // confirmation number
            $h->general()->noConfirmation();

            // travellers
            if (!empty($travellers)) {
                $h->general()->travellers($travellers);
            }

            $hotelTexts = $this->http->FindNodes($xpathFragmentTd1, $segment);
            $hotelText = implode("\n", $hotelTexts);

            if (preg_match($patterns['hotelInfo'], $hotelText, $matches)) {
                // hotelName
                // address
                $h->hotel()
                    ->name($matches['name'])
                    ->address(preg_replace("#\s+#", ' ', $matches['address']));

                // phone
                if (!empty($matches['phone'])) {
                    $h->hotel()->phone($matches['phone']);
                }
            }

            $dateTexts = $this->http->FindNodes($xpathFragmentTdLast, $segment);
            $dateText = implode("\n", $dateTexts);

            if (preg_match($patterns['hotelDates'], $dateText, $matches)) {
                // checkInDate
                // checkOutDate
                $h->booked()
                    ->checkIn2($this->normalizeDate($matches['dateCheckIn']))
                    ->checkOut2($this->normalizeDate($matches['dateCheckOut']));
            }
        }

        /* CAR */
        $carSegments = $this->http->XPath->query('//tr[ count(./*)=3 and ./*[2][' . $this->eq($this->t('Car')) . '] ]/ancestor::table[1]/following::table[normalize-space(.)][1]');
        $patterns['rentalLocation'] = '/'
            . '^(?:' . $patterns['missingValue'] . ')'
            . '\s+^(?<address>.{3,})' // Paris Orly Airport West Terminal Paris Orly 94547
            . '/ms';

        foreach ($carSegments as $segment) {
            $r = $email->add()->rental();

            // company
            // confirmation number
            $companyInfo = $this->http->FindSingleNode($xpathFragmentHeader, $segment);

            if (preg_match('/^(?<company>.{2,})\s+(?<confNumber>' . $patterns['confNumber'] . ')$/', $companyInfo, $matches)) {
                $r->extra()->company($matches['company']);
                $r->general()->confirmation($matches['confNumber']);
            }

            // travellers
            if (!empty($travellers)) {
                $r->general()->travellers($travellers);
            }

            $locationTexts = $this->http->FindNodes($xpathFragmentTd1, $segment);
            $locationText = implode("\n", $locationTexts);

            if (preg_match($patterns['rentalLocation'], $locationText, $matches)) {
                // pickUpLocation
                // dropOffLocation
                $r->pickup()->location(preg_replace('/\s+/', ' ', $matches['address']));
                $r->dropoff()->noLocation();
            }

            $dateTexts = $this->http->FindNodes($xpathFragmentTdLast, $segment);
            $dateText = implode("\n", $dateTexts);
            // pickUpDateTime
            // dropOffDateTime
            $r->pickup()->date2($dateText);
            $r->dropoff()->noDate();
        }
    }

    private function normalizeDate($string = '')
    {
        $in = [
            '/^(.{6,}?)\s*\|\s*(\d{1,2}:\d{2})$/', // 07-Sep-2018 | 11:15
        ];
        $out = [
            '$1, $2',
        ];

        return preg_replace($in, $out, $string);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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
