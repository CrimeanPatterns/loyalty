<?php

namespace AwardWallet\Engine\cardelmar\Email;

use AwardWallet\Engine\MonthTranslate;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "cardelmar/it-6874746.eml";

    public $reSubject = [
        'de' => [
            'Ihre CarDelMar Buchung',
        ],
        'en' => [
            'Your CarDelMar reservation',
        ],
    ];

    public static $dictionary = [
        'de' => [
            //			'Driver:' => '',
            'Reservation Number:' => 'Buchungsnummer:',
            //			'Pick-up location:' => '',
            //			'Return location:' => '',
            'Address:' => 'Adresse:',
            'Phone:'   => 'Telefon:',
            //			'Fax:' => '',
            'Opening hours:'    => 'Öffnungszeiten:',
            'Rental period:'    => 'Anmietzeitraum:',
            ' to '              => ' bis ',
            'Pick-up time:'     => 'Abholzeit:',
            'Return time:'      => 'Abgabezeit:',
            'Vehicle category:' => 'Fahrzeuggruppe:',
            'Example:'          => 'Beispielfahrzeug:',
            'Rental costs:'     => 'Ihr Mietpreis:',
        ],
        'en' => [],
    ];

    protected $lang = null;

    protected $langDetectors = [
        'de' => [
            'Herzlichen Dank für Ihre Reservierun',
        ],
        'en' => [
            'Client:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cardelmar.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"CarDelMar")]')->length === 0 && $this->http->XPath->query('//a[contains(@href,"//www.cardelmar.co.uk")]')->length === 0) {
            return false;
        }

        foreach ($this->langDetectors as $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $line . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'documents@cardelmar.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $line . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }

        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'YourReservation' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function translate($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 13/06/2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function parseEmail()
    {
        $it = [];
        $it['Kind'] = 'L';

        $it['RenterName'] = $this->getField($this->translate('Driver:'));

        $it['Number'] = $this->getField($this->translate('Reservation Number:'));

        $locationPickup = $this->getField($this->translate('Pick-up location:'));
        $adressPickup = $this->getField($this->translate('Address:'));

        if ($locationPickup && $adressPickup) {
            $it['PickupLocation'] = $locationPickup . ', ' . $adressPickup;
        } elseif ($locationPickup) {
            $it['PickupLocation'] = $locationPickup;
        } elseif ($adressPickup) {
            $it['PickupLocation'] = $adressPickup;
        }

        $locationDropoff = $this->getField($this->translate('Return location:'));
        $adressDropoff = $this->getField($this->translate('Address:'), 2);

        if ($locationDropoff && $adressDropoff) {
            $it['DropoffLocation'] = $locationDropoff . ', ' . $adressDropoff;
        } elseif ($locationDropoff) {
            $it['DropoffLocation'] = $locationDropoff;
        } elseif ($adressDropoff) {
            $it['DropoffLocation'] = $adressDropoff;
        }

        $it['PickupPhone'] = $this->getField($this->translate('Phone:'));

        $it['DropoffPhone'] = $this->getField($this->translate('Phone:'), 2);

        $it['PickupFax'] = $this->getField($this->translate('Fax:'));

        $it['DropoffFax'] = $this->getField($this->translate('Fax:'), 2);

        $it['PickupHours'] = $this->getField($this->translate('Opening hours:'));

        $it['DropoffHours'] = $this->getField($this->translate('Opening hours:'), 2);

        $rentalPeriodText = $this->getField($this->translate('Rental period:'));
        $rentalPeriodDates = explode($this->translate(' to '), $rentalPeriodText);

        if (count($rentalPeriodDates) === 2) {
            $datePickup = $rentalPeriodDates[0];
            $dateDropoff = $rentalPeriodDates[1];
        }

        $timePickup = $this->getField($this->translate('Pick-up time:'));

        if ($datePickup && $timePickup) {
            $it['PickupDatetime'] = strtotime($this->normalizeDate($datePickup) . ', ' . $timePickup);
        }

        $timeDropoff = $this->getField($this->translate('Return time:'));

        if ($dateDropoff && $timeDropoff) {
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($dateDropoff) . ', ' . $timeDropoff);
        }

        $it['CarType'] = $this->getField($this->translate('Vehicle category:'));

        $it['CarModel'] = $this->getField($this->translate('Example:'));

        $payment = $this->getField($this->translate('Rental costs:'));

        if (preg_match('/^([,.\d\s]+)([A-Z]{3})/', $payment, $matches)) {
            $it['TotalCharge'] = $this->normalizePrice($matches[1]);
            $it['Currency'] = $matches[2];
        }

        return $it;
    }

    private function getField($str, $pos = 1)
    {
        return $this->http->FindSingleNode("(//td[normalize-space(.)='{$str}'])[{$pos}]/following-sibling::td[1]");
    }
}
