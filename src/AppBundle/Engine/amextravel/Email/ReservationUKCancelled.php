<?php

namespace AwardWallet\Engine\amextravel\Email;

class ReservationUKCancelled extends \TAccountCheckerExtended
{
    public $mailFiles = "amextravel/it-1696253.eml";

    public $rePlain = "";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#American\s+Express\s+Travel\s+UK\s+(?:Reservation|CANCELLATION)#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#americanexpresstraveluk@travel\.americanexpress\.com#i";
    public $reProvider = "#travel\.americanexpress\.com#i";
    public $caseReference = "6695";
    public $isAggregator = "";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#Price\s+includes:\s+All\s+air\s+taxes#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#You\s+have\s+successfully\s+cancelled\s+this\s+booking#', $text)) {
                            return [
                                'Cancelled'     => true,
                                'Status'        => 'Cancelled',
                                'RecordLocator' => re('#Booking\s+Number\s*:\s+([\w\-]+)#'),
                            ];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Payments Received', +1);

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Tax', +1);

                        if ($subj) {
                            return cost($subj);
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(implode('-', array_reverse(explode('/', re("#\n\s*Booking Date\s+([^\n]+)#")))));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [];
                    },
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
