<?php

namespace AwardWallet\Engine\europcar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "europcar/it-278932353.eml"; // +2 bcdtravel(html)[de,en]

    private $subjects = [
        'de' => ['Bestätigung - Änderung Ihrer Reservierung'],
        'en' => ['Reservation Confirmation'],
    ];

    private $langDetectors = [
        'de' => ['Ihre Reservierungsnummer lautet:'],
        'en' => ['Your Reservation Number is:'],
    ];
    private $lang = '';
    private static $dict = [
        'de' => [
            'Your Reservation Number is:'   => 'Ihre Reservierungsnummer lautet:',
            'Driver Name:'                  => 'Fahrername',
            'Additional Drivers:'           => 'Zusätzliche Fahrer:',
            'Date & Time:'                  => 'Datum & Uhrzeit',
            'Location:'                     => ['Anmietstation:', 'Zustaendige Station:', 'Zuständige Station:'],
            'Telephone Number:'             => 'Telefonnummer:',
            'Vehicle Type:'                 => 'Fahrzeugtyp:',
            'References:'                   => 'Referenzen:',
            'Account Charges'               => 'Account-Gebühren',
            'Total Cost - Guaranteed price' => 'Total Cost - Guaranteed price',
        ],
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@europcar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"europcar.biz Support Team") or contains(.,"@europcar.com")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('Reservation' . ucfirst($this->lang));

        return $email;
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
        $patterns = [
            'phone' => '[+(\d][-+.\s\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $r = $email->add()->rental();

        // confirmationNumber
        $confirmationTitle = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Your Reservation Number is:')) . ']');
        $confirmation = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Your Reservation Number is:')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^(\d{5,})$/');

        if ($confirmation) {
            $r->general()->confirmation($confirmation, str_replace(':', '', $confirmationTitle));
        }

        // travellers
        $r->addTraveller($this->http->FindSingleNode('//td[not(.//td) and ' . $this->contains($this->t('Driver Name:')) . ']/following-sibling::td[normalize-space(.)][1]'));
        $additionalDrivers = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->contains($this->t('Additional Drivers:')) . ']/following-sibling::td[normalize-space(.)][1]');

        if ($additionalDrivers) {
            $r->setTravellers(preg_split('/\s*,\s*/', $additionalDrivers));
        }

        // pickUpDateTime
        $r->pickup()->date2($this->http->FindSingleNode('//tr[ ./td[5] and ./td[1][' . $this->contains($this->t('Date & Time:')) . '] ]/td[2]'));

        // dropOffDateTime
        $r->dropoff()->date2($this->http->FindSingleNode('//tr[ ./td[5] and ./td[4][' . $this->contains($this->t('Date & Time:')) . '] ]/td[5]'));

        $xpathFragmentLocation1 = '//tr['
            . ' ./td[5]'
            . ' and ( ./preceding-sibling::tr[ ./td[1][' . $this->contains($this->t('Location:')) . '] ] or ./td[1][' . $this->contains($this->t('Location:')) . '] )'
            . ' and ./following-sibling::tr[ ./td[1][' . $this->contains($this->t('Telephone Number:')) . '] ] '
            . ']';

        // pickUpLocation
        $pickUpLocationTexts = $this->http->FindNodes($xpathFragmentLocation1 . '/td[2]');
        $pickUpLocation = preg_replace('/\s+/', ' ', implode(' ', $pickUpLocationTexts));
        $r->pickup()->location($pickUpLocation);

        $xpathFragmentLocation2 = '//tr['
            . ' ./td[5]'
            . ' and ( ./preceding-sibling::tr[ ./td[4][' . $this->contains($this->t('Location:')) . '] ] or ./td[4][' . $this->contains($this->t('Location:')) . '] )'
            . ' and ./following-sibling::tr[ ./td[4][' . $this->contains($this->t('Telephone Number:')) . '] ] '
            . ']';

        // dropOffLocation
        $dropOffLocationTexts = $this->http->FindNodes($xpathFragmentLocation2 . '/td[5]');
        $dropOffLocation = preg_replace('/\s+/', ' ', implode(' ', $dropOffLocationTexts));
        $r->dropoff()->location($dropOffLocation);

        // pickUpPhone
        $pickUpPhone = $this->http->FindSingleNode("//tr[ ./td[5] and ./td[1][{$this->contains($this->t('Telephone Number:'))}] ][last()]/td[2]", null, true, "/^{$patterns['phone']}$/");

        if ($pickUpPhone) {
            $r->pickup()->phone($pickUpPhone);
        }

        // dropOffPhone
        $dropOffPhone = $this->http->FindSingleNode("//tr[ ./td[5] and ./td[4][{$this->contains($this->t('Telephone Number:'))}] ][last()]/td[5]", null, true, "/^{$patterns['phone']}$/");

        if ($dropOffPhone) {
            $r->dropoff()->phone($dropOffPhone);
        }

        $xpathFragmentCarModel = 'not(.//td) and ' . $this->contains($this->t('Vehicle Type:'));

        // carModel
        $r->car()->model($this->http->FindSingleNode('//td[' . $xpathFragmentCarModel . ']/following-sibling::td[normalize-space(.)][1]'));

        // carImageUrl
        $carImage = $this->http->FindSingleNode('//img[ ./preceding::td[' . $xpathFragmentCarModel . '] and ./following::td[not(.//td) and ' . $this->contains($this->t('References:')) . '] and contains(@src,"carvisuals") ]/@src');

        if ($carImage) {
            $r->car()->image($carImage);
        }

        $payment = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->contains($this->t('Total Cost - Guaranteed price')) . ']/following-sibling::td[normalize-space(.)][1]');

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) { // 182.38 EUR
            // p.currencyCode
            // p.total
            $r->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            // p.fees
            $feesRows = $this->http->XPath->query('//tr[ not(.//tr) and ./preceding-sibling::tr[' . $this->contains($this->t('Account Charges')) . '] and ./following-sibling::tr[' . $this->contains($this->t('Total Cost - Guaranteed price')) . '] ]');

            foreach ($feesRows as $feesRow) {
                $feeName = $this->http->FindSingleNode('./descendant::td[not(.//td) and normalize-space(.)][ position()=1 and not(position()=last()) ]', $feesRow);
                $feeValue = $this->http->FindSingleNode('./descendant::td[normalize-space(.)][last()]', $feesRow);

                if (preg_match('/^(?<amount>\d[,.\'\d\s]*)\s*' . preg_quote($matches['currency'], '/') . '\b/', $feeValue, $m)) {
                    $r->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{1,2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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
