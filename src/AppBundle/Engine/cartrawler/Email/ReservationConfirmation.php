<?php

namespace AwardWallet\Engine\cartrawler\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "/Your car hire booking confirmation/";
    public $langSupported = "en, da, de";
    public $typesCount = "1";
    public $reFrom = "#noreply@cartrawler\.com#i";
    public $reProvider = "#cartrawler\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "cartrawler/it-1969391.eml, cartrawler/it-1969567.eml, cartrawler/it-1970100.eml, cartrawler/it-1973020.eml, cartrawler/it-1982045.eml";
    public $pdfRequired = "0";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".cartrawler.com")]')->length === 0
            & $this->http->XPath->query('//node()[contains(.,"@cartrawler.com")]')->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query("//*[normalize-space() = 'Pick up Details']"
                . "[following::text()[normalize-space()][position() < 15][normalize-space() = 'Location']]"
                . "[following::text()[normalize-space()][position() < 15][normalize-space() = 'Time']]"
                )->length > 0
            || $this->http->XPath->query("//*[normalize-space() = 'Afhentningsoplysninger']"
                . "[following::text()[normalize-space()][position() < 15][normalize-space() = 'Sted']]"
                . "[following::text()[normalize-space()][position() < 15][normalize-space() = 'Tid']]"
                )->length > 0
            || $this->http->XPath->query("//*[normalize-space() = 'Details zur Abholung']"
                . "[following::text()[normalize-space()][position() < 15][normalize-space() = 'Ort']]"
                . "[following::text()[normalize-space()][position() < 15][normalize-space() = 'Zeit']]"
                )->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            str_replace(':', '', re('#(?:Car\s+rental\s+provider\s+confirmation\s+number|Bekræftelsesnummer\s+fra\s+biludlejningsfirma|Bestätigungsnummer\s+des\s+Mietwagenanbieters)\s*:\s*([\w:\-]+)#i')),
                            cell('Reservation number', +1)
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Header'   => ['Pick up Details', 'Afhentningsoplysninger', 'Details zur Abholung'],
                            'Location' => ['Location', 'Sted', 'Ort'],
                            'Date'     => ['Date', 'Dato', 'Datum'],
                            'Time'     => ['Time', 'Tid', 'Zeit'],
                        ];

                        foreach ($variants as &$array) {
                            foreach ($array as &$val) {
                                $val = 'contains(normalize-space(.), "' . $val . '")';
                            }
                        }
                        $rentalInfoNodes = xpath('//tr[(' . implode(' or ', $variants['Header']) . ') and not(.//tr)]/ancestor::tr[2]/following-sibling::tr[(' . implode(' or ', $variants['Location']) . ') and (' . implode(' or ', $variants['Date']) . ')]/td');

                        if (!$rentalInfoNodes or $rentalInfoNodes->length != 3) {
                            return null;
                        }

                        foreach (['Pickup' => 0, 'Dropoff' => 2] as $key => $value) {
                            $res[$key . 'Location'] = node('.//td[' . implode(' or ', $variants['Location']) . ']/following-sibling::td[1]', $rentalInfoNodes->item($value));
                            $dateStr = node('.//td[' . implode(' or ', $variants['Date']) . ']/following-sibling::td[1]', $rentalInfoNodes->item($value));
                            $timeStr = node('.//td[' . implode(' or ', $variants['Time']) . ']/following-sibling::td[1]', $rentalInfoNodes->item($value));

                            if ($dateStr and $timeStr) {
                                $res[$key . 'Datetime'] = strtotime($dateStr . ', ' . $timeStr);
                            }
                        }

                        return $res;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Car Rental Provider',
                            'Biludlejningsfirma',
                            'Mietwagenanbieter',
                        ];
                        array_walk($variants, function (&$value, $key) { $value = 'normalize-space(.) = "' . $value . '"'; });
                        $subj = node('(//td[' . implode(' or ', $variants) . ']/following-sibling::td[string-length(normalize-space(.)) > 1])[1]');

                        if (!$subj) {
                            if (node('//img[contains(@src, "https://cdn.cartrawler.com/otaimages/vendor/large/SIXT.jpg")]/@src')) {
                                $subj = 'Sixt';
                            }
                        }

                        return $subj;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell(['Car Code', 'Bilkode', 'Fahrzeugcode'], +1);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return cell(['Car Type', 'Biltype', 'Fahrzeugtyp'], +1);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return node('//td[normalize-space(.) = "Name"]/following-sibling::td[1]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell(['Selling Rate', 'Salgspris', 'Preis'], +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#and\s+have\s+now\s+(confirmed)\s+your\s+booking#i'),
                            re('#Ihre\s+Buchung\s+bei\s+.*\s+wurde\s+(bestätigt)\.#i')
                        );
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en", "da", "de"];
    }
}
