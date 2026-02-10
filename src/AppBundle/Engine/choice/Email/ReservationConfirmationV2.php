<?php

namespace AwardWallet\Engine\choice\Email;

use PlancakeEmailParser;

class ReservationConfirmationV2 extends \TAccountCheckerExtended
{
    public $mailFiles = "choice/it-1.eml, choice/it-1682417.eml, choice/it-1688384.eml, choice/it-1692677.eml, choice/it-1762818.eml, choice/it-1813020.eml, choice/it-1847608.eml, choice/it-2.eml, choice/it-2029876.eml, choice/it-3.eml";

    private $from = '/[@\.]choicehotels\.com/i';

    private $detects = [
        'Choice Hotels International, Inc. All rights reserved',
        'Choice Privileges',
    ];

    private $prov = "choice";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->plainText = text($this->getDocument('plain'));

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $regex = '#\n\s*Confirmation\s+Number\s*:\s*([^\n]+)#';

                        return orval(
                            re($regex),
                            re($regex, $this->plainText)
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+(.*)\s+Phone:\s+(.*)\s+Map\s*/\s*Directions#i', $text, $m)) {
                            return [
                                'HotelName' => nice(preg_replace('#\s*\(\S+\)#', '', $m[1])),
                                'Address'   => nice($m[2]),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check\s+In', 'CheckOut' => 'Check\s+Out'] as $key => $value) {
                            $dateStr = nice(re('#' . $value . ':\s+\w+,\s+(\w+\s+\d+,\s+\d+)#i'));
                            $timeStr = nice(re('#' . $value . '\s+Time:\s+(\d+:\d+\s*(?:am|pm))#i'));

                            if ($dateStr and $timeStr) {
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $roomTypes = [
                            '1\s+Double Bed,\s+2\s+Single\s+Beds,\s+No\s+Smoking',
                            '1\s+Double\s+Bed,\s+No\s+Smoking',
                            '1\s+King\s+Bed,\s+Suite,\s+No\s+Smoking',
                            '1\s+King\s+Bed,\s+No\s+Smoking',
                        ];
                        $n = xpath('//tr[contains(., "Room Description") and not(.//tr)]/following-sibling::tr[2]');

                        if ($n->length > 0) {
                            $n = $n->item(0);
                            $subj = implode("\n", nodes('./td[2]//text()', $n)) . "\n";

                            if (preg_match('#\s*(' . implode('|', $roomTypes) . ')\s+(.*)\s*#i', $subj, $m)) {
                                $roomType = $m[1];
                                $roomTypeDescription = nice($m[2]);
                            } else {
                                $roomType = $roomTypeDescription = null;
                            }

                            return [
                                'Guests'              => node('./td[6]', $n),
                                'Kids'                => node('./td[8]', $n),
                                'Rate'                => ($rate = node('./td[last()]', $n)) ? $rate : node('./td[last() - 1]', $n),
                                'RoomType'            => $roomType,
                                'RoomTypeDescription' => $roomTypeDescription,
                            ];
                        } elseif (preg_match('#\d+\s+for\s+\d+\s+nights?\s+(\d+)\s+(\d+)\s+(.*)#', $this->plainText, $m)) {
                            return [
                                'Guests'              => $m[1],
                                'Kids'                => $m[2],
                                'Rate'                => $m[3],
                                'RoomType'            => nice(re('#\n\s*Room\s+Adult[^\n]+\s+(.*)\s{3,}#', $this->plainText)),
                                'RoomTypeDescription' => nice(clear('#^[^,]+#', glue(re('#Children\s+Nightly\s+Rate\s+(.*?)[^\s]+\s*\d+\s+for\s+\d+\s+nights?\s+#ims', $this->plainText)))),
                            ];
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Rooms\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return preg_replace('#\s*Sub\s+Total.*|\s*Points\s+Redeemed.*#i', '', nice(orval(
                            cell('Cancellation Deadline', +1),
                            glue(re('#(\n\s*Reservations may be changed.*?)\n\s*Triple#ims', $this->plainText))))
                        );
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(re('#\n\s*Estimated\s+Tax(?:es)?\s+([^\n]+)#', $this->plainText))
                        );
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $regex = '#\n\s*Estimated Total:\s*([^\n]+)#';
                        $totalStr = orval(
                            preg_replace('#\(.*#i', '', re($regex)),
                            re($regex, $this->plainText)
                        );

                        if (re('#\(US\s+Dollar\)#i', $totalStr)) {
                            return [
                                'Total'    => cost($totalStr),
                                'Currency' => 'USD',
                            ];
                        } else {
                            return total($totalStr, 'Total');
                        }
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Points\s+Redeemed:\s+(.*)#i');
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#Choice\s+Privileges\s*\#\s*:\s+([\w\-]+)#i');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell("Reservation Status:", +1, 0);
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }
}
