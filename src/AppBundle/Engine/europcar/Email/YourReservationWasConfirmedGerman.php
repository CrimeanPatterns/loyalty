<?php

namespace AwardWallet\Engine\europcar\Email;

class YourReservationWasConfirmedGerman extends \TAccountCheckerExtended
{
    public $mailFiles = ""; // +1 bcdtravel(html)[de]

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Ihre Reservierungsnummer lautet:\s+(\d+)#');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re('#Abholung\s+(.*)#');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $s = re('#Abholung\s+.*?(\d+\.\d+\.\d+\s+\d+:\d+)#s');

                        if (preg_match('#(.*\.)(\d+)(\s+.*)#i', $s, $m)) {
                            return strtotime($m[1] . '20' . $m[2] . $m[3]);
                        }
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re('#Rückgabe\s+(.*)#');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $s = re('#Rückgabe\s+.*?(\d+\.\d+\.\d+\s+\d+:\d+)#s');

                        if (preg_match('#(.*\.)(\d+)(\s+.*)#i', $s, $m)) {
                            return strtotime($m[1] . '20' . $m[2] . $m[3]);
                        }
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#oder ähnlich\s+(.*)#');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re('#(.*)\s+oder ähnlich#');
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[normalize-space(.) = "Fahrzeugdetails"]/following-sibling::tr[4]//img/@src');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Fahrer:\s+(.*)#');
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        $patterns = [
                            'phoneNumber' => '[+)(\d][-)(\d ]{5,}[)(\d]\b', // +30 (2312) 204444
                        ];

                        if (preg_match('/^\s*Anmietstation\s*$(.+?)^\s*Rückgabestation\s*$(.+)/ms', $text, $matches)) {
                            if (preg_match('/Telefon\s+(' . $patterns['phoneNumber'] . ')/', $matches[1], $m)) {
                                $result['PickupPhone'] = $m[1];
                            }

                            if (preg_match('/Fax\s+(' . $patterns['phoneNumber'] . ')/', $matches[1], $m)) {
                                $result['PickupFax'] = $m[1];
                            }

                            if (preg_match('/Telefon\s+(' . $patterns['phoneNumber'] . ')/', $matches[2], $m)) {
                                $result['DropoffPhone'] = $m[1];
                            }

                            if (preg_match('/Fax\s+(' . $patterns['phoneNumber'] . ')/', $matches[2], $m)) {
                                $result['DropoffFax'] = $m[1];
                            }
                        }

                        return $result;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $payment = orval(
                            cell('Bei Ankunft zu zahlender Preis', +1),
                            re('/Bei Ankunft zu zahlender Preis\s+(.+)/')
                        );

                        return total($payment);
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@europcar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Ihre Reservierung bei EUROPCAR, Ihre Reservierungsnummer') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Wir danken für Ihre Reservierung bei Europcar") or contains(normalize-space(.),"Ihre Reservierung bei EUROPCAR") or contains(normalize-space(.),"© Europcar international") or contains(.,"@europcar.com")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Fahrer:") or contains(normalize-space(.),"Ausgewählte Extras:")]')->length > 0;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }
}
