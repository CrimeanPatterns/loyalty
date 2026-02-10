<?php

namespace AwardWallet\Engine\eurobonus\Email;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-5940407.eml, eurobonus/it-9944721.eml"; // +1 bcdtravel(html)[da]

    private $subjects = [
        'no' => ['Leiebilbestilling for'],
        'da' => ['Bestilling af udlejningsbil for'],
        'en' => ['Car hire booking for'],
    ];

    private static $detects = [
        'no' => [
            'Takk for at du valgte å leie bil gjennom vår partner',
        ],
        'da' => [
            'Tak, fordi du valgte at leje bil gennem vores partner',
        ],
        'en' => [
            'Thank you for choosing to hire a car through one of our partners',
        ],
    ];

    private $dict = [
        'no' => [
            'Fr'                  => ['Fr', 'Hr'],
            'Bonusprogramnummer:' => ['Bonusprogramnummer:', 'Bonusprogramnummer :'],
        ],
        'da' => [
            'Reservasjonsnummer'                      => 'Reservationsnummer',
            'Bekreftelsesnummer fra bilutleiefirmaet' => 'Bilreservationsnummer',
            'Passasjerinformasjon'                    => 'Rejseinformationer',
            'Fr'                                      => ['Fr', 'Hr'],
            'Henting'                                 => 'Afhentning',
            'Avlevering'                              => 'Aflevering',
            'Adresse'                                 => 'Adresse',
            'Samme som hentested'                     => 'Samme som afhentning',
            'Dato og klokkeslett'                     => 'Dato og klokkeslæt',
            'Åpningstid'                              => 'Åbningstider',
            'Tlf'                                     => 'Tlf',
            //            'Faks' => '',
            'Kategori'            => 'Kategori',
            'Bonusprogramnummer:' => ['Bonuskortnummer:', 'Bonuskortnummer :'],
            'Totalt for'          => 'Samlet for',
        ],
        'en' => [
            'Reservasjonsnummer'                      => 'Booking reservation number',
            'Bekreftelsesnummer fra bilutleiefirmaet' => 'Car confirmation number',
            'Passasjerinformasjon'                    => 'Traveler information',
            'Fr'                                      => ['Mr', 'Ms'],
            'Henting'                                 => 'Pick-up',
            'Avlevering'                              => 'Drop-off',
            'Adresse'                                 => 'Address',
            'Samme som hentested'                     => 'Same as pick-up',
            'Dato og klokkeslett'                     => 'Date and time',
            'Åpningstid'                              => 'Opening hours',
            'Tlf'                                     => 'Tel',
            'Faks'                                    => 'Fax',
            'Kategori'                                => 'Category',
            'Bonusprogramnummer:'                     => ['Frequent Flyer number:', 'Frequent Flyer number :'],
            'Totalt for'                              => 'Total for',
        ],
    ];

    private $lang = 'no';

    private $provider = 'sas.com';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detects as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $classParts = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => end($classParts) . ucfirst($this->lang),
        ];
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detects as $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false && stripos($body, $this->provider) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detects);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detects);
    }

    private function parseEmail()
    {
        $patterns = [
            'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
        ];

        $xpathFragmentCell = 'self::th or self::td';

        /** @var \AwardWallet\ItineraryArrays\CarRental $it */
        $it = ['Kind' => 'L'];

        $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Reservasjonsnummer')}')]/following::text()[normalize-space(.)][1]", null, true, '/(' . $patterns['confNumber'] . ')/');

        $it['Number'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Bekreftelsesnummer fra bilutleiefirmaet')}')]/following::text()[normalize-space(.)][1]", null, true, '/(' . $patterns['confNumber'] . ')/');

        $it['RenterName'] = $this->http->FindSingleNode("//text()[contains(., '{$this->t('Passasjerinformasjon')}')]/following::text()[{$this->starts($this->t('Fr'))}][1]");

        $xpath = "//tr[not(.//tr) and contains(., '{$this->t('Henting')}') and contains(., '{$this->t('Avlevering')}')]/";

        $it['PickupLocation'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Adresse')}')]/*[2]");

        $it['DropoffLocation'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Adresse')}')]/*[3]");

        if ($it['DropoffLocation'] === $this->t('Samme som hentested')) {
            $it['DropoffLocation'] = $it['PickupLocation'];
        }

        $pickUpDate = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Dato og klokkeslett')}')]/*[2]");
        $it['PickupDatetime'] = $this->normalizeDate($pickUpDate);

        $dropOffDate = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Dato og klokkeslett')}')]/*[3]");
        $it['DropoffDatetime'] = $this->normalizeDate($dropOffDate);

        $it['PickupPhone'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Tlf')}')]/*[2]");

        $it['PickupFax'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Faks')}')]/*[2]");

        $it['DropoffPhone'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Tlf')}')]/*[3]");

        if ($it['DropoffPhone'] === $this->t('Samme som hentested')) {
            $it['DropoffPhone'] = $it['PickupPhone'];
        }

        $it['DropoffFax'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Faks')}')]/*[3]");

        if ($it['DropoffFax'] === $this->t('Samme som hentested')) {
            $it['DropoffFax'] = $it['PickupFax'];
        }

        $it['PickupHours'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Åpningstid')}')]/*[2]");

        $it['DropoffHours'] = $this->http->FindSingleNode($xpath . "following::tr[contains(., '{$this->t('Åpningstid')}')]/*[3]");

        if ($it['DropoffHours'] === $this->t('Samme som hentested')) {
            $it['DropoffHours'] = $it['PickupHours'];
        }

        $it['CarImageUrl'] = $this->http->FindSingleNode("//img[contains(@src, 'retrieveCarItem?ctg=VEHICLE')]/@src");

        $carTypeModel = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Kategori'))}]/ancestor::*[self::th or self::td][1]/following-sibling::*[1]");
        $typeModel = explode('-', $carTypeModel);

        if (is_array($typeModel) && count($typeModel) > 1) {
            $it['CarModel'] = end($typeModel);
        }

        // AccountNumbers
        $ffNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Bonusprogramnummer:'))}]/ancestor::*[{$xpathFragmentCell}][1]/following-sibling::*[1]", null, true, '/(' . $patterns['confNumber'] . ')/');

        if ($ffNumber) {
            $it['AccountNumbers'] = [$ffNumber];
        }

        $total = $this->http->FindSingleNode("//td[contains(., '{$this->t('Totalt for')}')]/following-sibling::td[1]");
        // 1.574,92 DKK
        if (preg_match('/([\d\.\,]+)(\d{2})\s+([A-Z]{3})/', $total, $m)) {
            $it['TotalCharge'] = str_replace([',', '.'], ['', ''], $m[1]) . '.' . $m[2];
            $it['Currency'] = $m[3];
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    /**
     * fredag, 8. april 2016, 07:00.
     *
     * @param $str
     *
     * @return int|null
     */
    private function normalizeDate($str)
    {
        $res = null;
        $re = [
            '/\w*\s*(?<day>\d{1,2})\.*\s*(?<month>\w+)\s+(?<year>\d{4})\,\s+(?<time>\d{1,2}:\d{2})/',
        ];
        $res = array_map(function ($re) use ($str) {
            $date = '';

            if ($this->lang !== 'en' && preg_match($re, $str, $m)) {
                $date = $m['day'] . ' ' . \AwardWallet\Engine\MonthTranslate::translate($m['month'], $this->lang) . ' ' . $m['year'] . ', ' . $m['time'];
            } elseif (preg_match($re, $str, $m)) {
                $date = $m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time'];
            }

            return $date;
        }, $re);

        return is_array($res) ? strtotime(current($res)) : null;
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
}
