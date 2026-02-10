<?php

namespace AwardWallet\Engine\china\Email;

class It1919393 extends \TAccountCheckerExtended
{
    public $mailFiles = "china/it-1919393.eml";

    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#China\s*Airlines#i";
    public $reProvider = "#[@.]email[.]china-airlines[.]com#i";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re('/Name:\s*(.+?)\s*Check\s*in/is');

                        return [nice($name)];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("##");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re('/Flight\s*No:\s*(.+?)\s*Departure\s*Time/is');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = re("#From:\s*(.+?)\s*To:#is");

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = re('/Departure\s*Time:\s*(.+?)\s*Boarding\s*Time/is');
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = re('/To:\s*(.+?)\s*$/im');

                            return nice($name);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $cls = re('/Cabin\s*Class:\s*(.+?)\s*Boarding/is');

                            return nice($cls);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = re('/Seat\s*No:\s*(.+?)\s*Cabin/is');

                            return nice($seat);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'china-airlines.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'notice@email.china-airlines.') !== false || stripos($headers['from'], 'notice@china-airlines.') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if ((stripos($textPdf, 'china-airlines.com') !== false || stripos($textPdf, 'China Airlines web') !== false) && stripos($textPdf, 'Boarding') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }
}
