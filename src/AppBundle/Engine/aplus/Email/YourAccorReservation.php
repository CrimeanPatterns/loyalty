<?php

namespace AwardWallet\Engine\aplus\Email;

class YourAccorReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank you for choosing Accorhotels!#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#reservations@accorhotels\.cn#i";
    public $reProvider = "#accorhotels\.cn#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "aplus/it-1919728.eml, aplus/it-1973568.eml";
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
                        return re('#Your reservation number is\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return
                        nice(
                            orval(
                                re('#(.*)\s+-\s+Managed by Accor#'),
                                cell('Hotel:', +1)
                            )
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Check-in date', +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Hotel address', +1, 0, '//text()'), ',');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return cell('Hotel Telephone', +1);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [cell('Guest name', +1)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Number of Guests', +1);

                        if (preg_match_all('#Adult\(s\)\s+(\d+)#i', $subj, $matches)) {
                            $guests = null;

                            foreach ($matches[1] as $m) {
                                $guests += $m;
                            }

                            return $guests;
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Room type', +1);

                        if (preg_match_all('#Room\s+\d+:\s+(.*?),#i', $subj, $matches)) {
                            $roomTypes = null;

                            foreach ($matches[1] as $m) {
                                $roomTypes[] = $m;
                            }

                            return implode($roomTypes);
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total Price', +1), 'Total');
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
