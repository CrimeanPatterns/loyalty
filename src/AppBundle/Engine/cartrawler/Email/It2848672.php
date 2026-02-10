<?php

namespace AwardWallet\Engine\cartrawler\Email;

class It2848672 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*От\s*:[^\n]*?cartrawler#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Your car hire booking confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]cartrawler\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]cartrawler\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "uk";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "06.07.2015, 09:39";
    public $crDate = "06.07.2015, 08:59";
    public $xPath = "";
    public $mailFiles = "cartrawler/it-2848672.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

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
                        return orval(
                            str_replace(':', '', re('#(?:Car\s+rental\s+provider\s+confirmation\s+number)\s*:\s*([\w:\-]+)#i')),
                            cell('Reservation number', +1),
                            cell('Номер резервування', +1)
                        );
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Header'   => ['Pick-up details'],
                            'Location' => ['Location'],
                            'Date'     => ['Дата', 'Date'],
                            'Time'     => ['Час', 'Time'],
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
                            'Постачальник послуг із прокату автомобілів',
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
                        return cell(['Car Code', 'Код автомобіля'], +1);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return cell(['Car Type', 'Марка і модель автомобіля'], +1);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return beautifulName(node('//td[normalize-space(.) = "Name"]/following-sibling::td[1]'));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell(['Selling rate', 'Selling Rate'], +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#and\s+have\s+now\s+(confirmed)\s+your\s+booking#i')
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
        return ["uk"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
