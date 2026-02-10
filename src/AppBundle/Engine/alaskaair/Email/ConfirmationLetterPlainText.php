<?php

namespace AwardWallet\Engine\alaskaair\Email;

class ConfirmationLetterPlainText extends \TAccountCheckerExtended
{
    public $mailFiles = "alaskaair/it-11015400.eml, alaskaair/it-12021980.eml, alaskaair/it-12168100.eml, alaskaair/it-12879753.eml, alaskaair/it-1705711.eml, alaskaair/it-2270259.eml, alaskaair/it-5.eml";

    private $reservationDate;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // Confirmation Letter - COFNHQ 11/15/17 - from Alaska Airlines
                    $this->reservationDate = strtotime(re('/\b\d+\/\d+\/\d+\b/', $this->parser->getSubject()));

                    if (!$this->reservationDate) {
                        $this->reservationDate = strtotime($this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Confirmation Letter -")]', null, true, '/\b\d+\/\d+\/\d+\b/'));
                    }

                    if (!$this->reservationDate) {
                        $this->logger->debug('Relative date not found!');

                        return null;
                    }
                    $text = $this->parser->getPlainBody();

                    if (empty($text)) {
                        $text = strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $this->parser->getHtmlBody()));
                    }

                    $text = str_replace('&gt;', '>', $text);
                    $text = preg_replace('/^[> ]+/m', '', $text);

                    if (!preg_match('/^[ ]*Flight:/im', $text)) { // it-12168100.eml
                        $this->http->SetEmailBody($text);
                        $textNodes = $this->http->FindNodes('//text()[normalize-space(.)]');
                        $text = implode("\n", $textNodes);
                    }

                    return [str_replace("\r", '', $text)];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation Code\s*:\s*(\w+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengersStr = re('#TRAVELERS\s+((?s).*?)\s+FLIGHT\s+INFORMATION#i');

                        return explode("\n", $passengersStr);
                    },

                    //                    "AccountNumbers" => function($text = '', $node = null, $it = null){
                    //                        $result = [];
                    //                        if ( preg_match_all('/^[ ]*Traveler[ ]*:.+#[ ]*([*\d ]*\d{2}[*\d ]*)\b[ ]*$/mi', $text, $ffNumberMatches) ) { // Traveler: ... # *****8936
                    //                            $result = array_unique($ffNumberMatches[1]);
                    //                        }
                    //						return $result;
                    //					},

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        if (preg_match_all('/^[> ]*Ticket[ ]*[:]+[ ]*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})[ ]*(?:\(|$)/im', $text, $ticketNumberMatches)) {
                            // Ticket: 0272135405739
                            $result = array_unique($ticketNumberMatches[1]);
                        }

                        return $result;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (empty(re("/\b(New Ticket Value)\b/"))) {
                            return [
                                "TotalCharge" => cost(re("#\n\s*Total Fare\s*:\s*([^\n]+)#i")),
                                "Currency"    => currency(re(1)),
                            ];
                        }

                        return null;
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/^[> ]*(\d[\d,]* *miles) have been redeemed from Mileage Plan /im', $text, $m)) {
                            return $m[1];
                        }

                        return null;
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('/^[> ]*Base Fare and Surcharges\s*:\s*(.*)/im', $text, $m) && count($m[0]) === 1) {
                            return cost($m[1][0]);
                        }

                        return null;
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('/^[> ]*Taxes and Other Fees\s*:\s*(.*)/im', $text, $m) && count($m[0]) === 1) {
                            return cost($m[1][0]);
                        }

                        return null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*Flight:\s+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*Flight\s*:\s*(.*?)\s+(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $r = '#' . $value . ':\s+(.*?)\s+\(([A-Z]{3})\)\s+on\s+\w+,\s+(\w+\s+\d+)\s+at\s+(\d+:\d+\s*(?:am|pm))#i';

                                if (preg_match($r, $text, $m)) {
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($m[3] . ', ' . date('Y', $this->reservationDate) . ', ' . $m[4]);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                // Class: S(Coach)
                                'BookingClass' => re("#Class\s*:\s*([A-Z])\s*\(([[:alpha:]\s]+)\)#"),
                                'Cabin'        => re(2),
                            ];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Equipment\s*:\s*(.+)#"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seatsStr = re('#\n\s*Seats\s*:\s*([^\n]+)#');

                            if (preg_match_all('#\s*\d+[A-Z]\s*#i', $seatsStr, $m)) {
                                return array_map('trim', $m[0]);
                            }

                            return null;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Alaska Airlines') !== false
            || stripos($from, '@alaskaair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Confirmation Letter.* from Alaska Airlines/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img")->length === 0) {
            if (empty($textBody = $parser->getPlainBody())) {
                $textBody = text($parser->getHTMLBody());
            }

            if (
                strpos($textBody, 'Thank you for booking with Alaska') === false
                && strpos($textBody, 'from Alaska Airlines') === false
                && strpos($textBody, 'traveling on Alaska Airlines') === false
                && strpos($textBody, 'Contact Alaska Airlines') === false
                && strpos($textBody, 'Alaska Airlines. All rights reserved') === false
                && stripos($textBody, '@alaskaair.com') === false
                && stripos($textBody, 'www.alaskaair.com') === false
            ) {
                return false;
            }

            return stripos($textBody, 'Confirmation code:') !== false;
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }
}
