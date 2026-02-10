<?php

namespace AwardWallet\Engine\avis\Email;

class PlainText extends \TAccountCheckerExtended
{
    public $mailFiles = ""; // +1 bcdtravel(plain)[de]

    public $rePlain = "#Thank you Shimon, for renting with us!.*avis\.com|Vielen Dank für Ihre Internet-Reservierung bei Avis|Thank you for booking with Avis#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en,de";
    public $typesCount = "2";
    public $reFrom = "#avisreservations@avis\.com|avis\.reservations@avis\.de#i";
    public $reProvider = "#[.@]avis\.(?:com|de)#i";
    public $caseReference = "";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('/(?:Your\s+Confirmation\s+Number:|Ihre\s+Reservierungsnummer\s+lautet|Your reservation number is)\s+([\w\-]+)/i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        if (preg_match('/(.+)\s+^\s*(?:Anmietstation|Rental location):/m', $text, $matches)) {
                            preg_match_all('/(\d{1,2}\.\d{1,2}\.\d{2,4}[,\s]+\d{1,2}:\d{2}(?:\s*[ap]m)?)/i', $matches[1], $dateMatches);

                            if (count($dateMatches[1]) === 2) {
                                $result['PickupDatetime'] = strtotime($dateMatches[1][0]);
                                $result['DropoffDatetime'] = strtotime($dateMatches[1][1]);
                            }
                        }

                        $subjs = [];

                        if (preg_match('/(?:Pick-up Information|Anmietstation:|Rental location:)\s+(.*?)\n\s*\n\s*(?:Abgabestation:|Return location:|Return)\s*(.*?)\n\s*\n/is', $text, $m)) {
                            $subjs['Pickup'] = $m[1];
                            $subjs['Dropoff'] = $m[2];
                        } else {
                            return false;
                        }

                        $regex = '#';
                        $regex .= '\w+,\s+(\w+\s+\d+,\s+\d+\s+@\s+\d+:\d+\s+(?:am|pm))\s+';
                        $regex .= '((?s).*?)';
                        $regex .= '(\(\d\)\s+\d+.*)\s*';
                        $regex .= '((?s).*)';
                        $regex .= '#i';

                        foreach (['Pickup', 'Dropoff'] as $key) {
                            if (preg_match($regex, $subjs[$key], $matches)) {
                                $result[$key . 'Datetime'] = strtotime(str_replace('@', ',', $matches[1]));
                                $result[$key . 'Location'] = nice($matches[2], ',');
                                $result[$key . 'Phone'] = $matches[3];
                                $result[$key . 'Hours'] = nice($matches[4], ',');
                            } else {
                                if (preg_match('/^\s*(\w.+?)\s*(?:Öffnungszeiten am|Opening hours)/', $subjs[$key], $matches)) {
                                    $result[$key . 'Location'] = $matches[1];
                                }

                                if (preg_match('/\s*(?:Öffnungszeiten am (?:Abholtag|Abgabetag)|Opening hours)\s*:\s*(.+?)\s*(?:Telefon|Telephone)/m', $subjs[$key], $matches)) {
                                    $result[$key . 'Hours'] = $matches[1];
                                }

                                if (preg_match('/\s*(?:Telefon|Telephone)\s*\(?\s*([\-\+\d\s]{5,})/mi', $subjs[$key], $matches)) {
                                    $result[$key . 'Phone'] = trim($matches[1]);
                                }
                            }
                        }

                        return $result;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        if (preg_match('/(Group\s+\w+)\s*-\s+(.*\s+or\s+similar)/ui', $text, $matches)) {
                            $result['CarType'] = $matches[1];
                            $result['CarModel'] = $matches[2];
                        } elseif (preg_match('/Fahrzeuggruppe\s*:\s*(Gruppe\s+\w+)\s+\(([^)]+)\)/ui', $text, $matches)) {
                            $result['CarType'] = $matches[1];
                            $result['CarModel'] = $matches[2];
                        } elseif (preg_match('/(Group\s+\w+)\s*\(e.g.\s*(.+?)\)/ui', $text, $matches)) {
                            $result['CarType'] = $matches[1];
                            $result['CarModel'] = $matches[2];
                        }

                        return $result;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (!preg_match('/(?:Estimated\s+Total|Preis:|Price:)\s+(.+)/i', $text, $matches)) {
                            return false;
                        }

                        $result = [];

                        if (preg_match('/\((\w+)\)(.+)/u', $matches[1], $m)) {
                            $result['Currency'] = $m[1];
                            $result['TotalCharge'] = $this->normalizePrice($m[2]);
                        } elseif (preg_match('/([,.\d]+)\s*([A-Z\s]{2,})/', $matches[1], $m)) {
                            $result['TotalCharge'] = $this->normalizePrice($m[1]);
                            $result['Currency'] = $m[2];
                        }

                        return $result;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('#Thank\s+you\s+.*,\s+for\s+renting\s+with\s+us!\s+Your\s+car\s+is\s+reserved#')) {
                            return 'Confirmed';
                        } elseif (re('#your reservation has been cancelled#i')) {
                            return [
                                'Status'    => 'Cancelled',
                                'Cancelled' => true,
                            ];
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'de'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }
}
