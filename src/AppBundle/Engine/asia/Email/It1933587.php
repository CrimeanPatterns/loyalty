<?php

namespace AwardWallet\Engine\asia\Email;

class It1933587 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@cathaypacific[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@cathaypacific[.]com#i";
    public $reProvider = "#[@.]cathaypacific[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "asia/it-1402357.eml, asia/it-1933587.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Booking Reference Number:');

                        return re("#$q\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//strong[normalize-space(text()) = 'Passengers']/following::table[1]//strong");

                        return $ppl;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = orval(
                            between('Total New Fare', 'Fare Difference'),
                            between('Total Fare:', 'Payment')
                        );

                        return total($tot);
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        $table = xpath("//strong[contains(text(), 'Other FFPs')]/following::table[1]");

                        if (!$table->length) {
                            return;
                        }
                        $table = $table->item(0);

                        $name = xpath('./thead/tr[1]/th[1]', $table);

                        if (!$name->length) {
                            return;
                        }
                        $name = text($name->item(0));
                        $amount = xpath('./tbody/tr[1]/td[1]', $table);

                        if (!$amount->length) {
                            return;
                        }
                        $amount = text($amount->item(0));

                        return "$amount $name";
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q_changed = whiten('You have made changes to the following itinerary[.]');

                        if (re("/$q_changed/i")) {
                            return 'changed';
                        }

                        $q_confirmed = whiten('The following booking is confirmed[.]');

                        if (re("/$q_confirmed/i")) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Itinerary Details')]/following::table[1]/tbody/tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('./td[2]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $code = node('./td[4]');

                            return re('/[A-Z]+/', $code);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = nice(node('./td[1]'));
                            $from = node('./td[4]');
                            $time = re('/(\d+:\d+)/', $from);

                            $dt = "$date $time";

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $code = node('./td[5]');

                            return re('/[A-Z]+/', $code);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = nice(node('./td[1]'));
                            $to = node('./td[5]');
                            $time = re('/(\d+:\d+)/', $to);

                            $dt = "$date $time";
                            $dt = strtotime($dt);

                            if (re('/\+1/', $to)) {
                                $dt = strtotime('+1 day', $dt);
                            }

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $air = node('./td[8]');

                            return nice($air);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node('./td[9]');

                            if (preg_match('/\s*(.+?)\s*[(](\w+)[)]/', $info, $ms)) {
                                return [
                                    'Cabin'        => nice($ms[1]),
                                    'BookingClass' => nice($ms[2]),
                                ];
                            }

                            return nice($info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $dur = node('./td[7]');

                            return nice($dur);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = node('./td[6]');

                            return re('/\d+/', $stops);
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
        return ["en"];
    }
}
