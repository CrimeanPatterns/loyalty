<?php

namespace AwardWallet\Engine\aplus\Email;

class ConfirmationOfReservationPlainText extends \TAccountCheckerExtended
{
    public $reFrom = "#(?:ACCOR\s+HOTELS\s+RESERVATION|accorhotels\.reservation@accorhotels\.com)#i";
    public $reProvider = "";
    public $rePlain = "#We\s+are\s+delighted\s+to\s+confirm\s+your\s+reservation\s+in\s+the\s+following\s+hotel\s*:\s+(Ibis\s+Brussels\s+off\s+Grand\s+Place|Novotel\s+Brussels\s+off\s+Grand'Place|Ibis\s+Bordeaux\s+Centre\s+Meriadeck)#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "aplus/it-1762302.eml, aplus/it-1762304.eml, aplus/it-1762305.eml, aplus/it-1762307.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#your\s+confirmation\s+number\s+is\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#following\s+hotel\s*:\s+(.*)\s+tel\s*:\s*(.*)\s+((?s).*?)Warning\s*:#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Phone'     => $m[2],
                                'Address'   => nice(trim($m[3]), ','),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn'  => ['Arriving', 'Check in'],
                            'CheckOut' => ['Leaving', 'Check out'],
                        ];

                        foreach ($arr as $key => $value) {
                            $dateStr = re('#' . $value[0] . '\s*:\s*(.*)#');
                            $timeStr = re('#\d+:\d+\s*(?:am|pm)?#', cell($value[1] . ' Policy', +1));
                            $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Hello\s+(.*),#')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#for\s+(\d+)\s+(?:person|adult\(s\))#');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#and\s+(\d+)\s+child\(ren\)#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s+Room\(s\)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell('Cancellation delay', +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Rate\s+-\s+(.*)\s+\(#');

                        return (!preg_match('#^\d+\s+Room\(s\)\s+for\s+\d+\s+persons?$#i', $subj)) ? $subj : null;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+from.*?to\s+\w+\s+\d+,\s+\d+\s+(.*)#');

                        if ($subj) {
                            return total($subj, 'Total');
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Date\s+of\s+your\s+reservation:\s+(.*)#'));
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
        return ["en"];
    }
}
