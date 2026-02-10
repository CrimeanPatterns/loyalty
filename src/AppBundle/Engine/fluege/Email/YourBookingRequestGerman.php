<?php

namespace AwardWallet\Engine\fluege\Email;

class YourBookingRequestGerman extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#vielen\s+Dank\s+für\s+Ihre\s+verbindliche\s+Buchungsanfrage\s+bei\s.*?\bfluege\.de#si', 'blank', '4000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Ihre Buchungsanfrage', 'blank', ''],
    ];
    public $reFrom = [
        ['#service@fluege-service\.de#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]fluege#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "13.08.2015, 11:03";
    public $crDate = "20.04.2015, 11:06";
    public $xPath = "";
    public $mailFiles = "fluege/it-2638040.eml, fluege/it-2963902.eml, fluege/it-3291464.eml, fluege/it-5912736.eml, fluege/it-5958473.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->confNo = re("#.*?([A-Z\d-]+)$#", re('#Ihre\s+Buchungsanfrage\s+hat\s+die\s+Bestätigungsnummer:\s+\S+/([\w-]+)#i'));
                    $fn = cell('Vorname:', +1);
                    $ln = cell('Nachname:', +1);
                    $this->passenger = ($fn and $ln) ? $fn . ' ' . $ln : null;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->confNo;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [$this->passenger];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Gesamtpreis', +1));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text())='Flugdetails' or normalize-space(text())='Flugnummer']/ancestor::tr[2]/following-sibling::tr[contains(., 'Klasse:')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            // echo "----------------------------\n";
                            // echo text($text)."\n";
                            // echo "----------------------------\n";
                            $r = '#^';
                            $r .= '\w+,\s+(?P<Date>\d+\.\d+\.\d+)\s+';
                            $r .= '(?P<DepName>[^\n]*?)\s+\((?P<DepCode>\w{3})\)\s+Ab\s+(?P<Time>\d+:\d+)\s+';
                            $r .= '[^\n]*?\s+\((?P<AirlineName>\w{2})\s+(?P<FlightNumber>\d+)\s*\)(?>\s+operated\s+by\s[^\n]*)?\s+';
                            $r .= 'Klasse:\s+(?P<Cabin>[^\n]*?)\s*';
                            $r .= '(?:Freigepäck:|$)';
                            $r .= '#i';
                            $depInfo = text($text);
                            $res = null;
                            $dateStr = null;

                            if (preg_match($r, $depInfo, $m)) {
                                $dateStr = $m['Date'];
                                $res['DepDate'] = strtotime($m['Date'] . ', ' . $m['Time']);
                                $keys = ['DepName', 'DepCode', 'AirlineName', 'FlightNumber', 'Cabin'];

                                foreach ($keys as $k) {
                                    $res[$k] = $m[$k];
                                }
                            }

                            $arrInfo = text(xpath('./following-sibling::tr[2]'));

                            $r = '#^(?:\w+,\s+(?P<Date>\d+\.\d+\.\d+)\s+|)(?P<ArrName>.*?)\s+\((?P<ArrCode>\w{3})\)\s+An\s+(?P<Time>\d+:\d+)\s+.*$#i';

                            if (preg_match($r, $arrInfo, $m)) {
                                if ($m['Date']) {
                                    $dateStr = $m['Date'];
                                }

                                foreach (['ArrName', 'ArrCode'] as $k) {
                                    $res[$k] = $m[$k];
                                }
                                $res['ArrDate'] = ($dateStr) ? strtotime($dateStr . ' ' . $m['Time']) : null;
                            }

                            return $res;
                        },
                    ],
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
