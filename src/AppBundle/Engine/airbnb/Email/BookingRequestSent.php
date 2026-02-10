<?php

namespace AwardWallet\Engine\airbnb\Email;

use PlancakeEmailParser;

class BookingRequestSent extends \TAccountCheckerExtended
{
    public $mailFiles = "airbnb/it-1955422.eml";

    private $detects = [
        'Du skal vide, at din anmodning om at reservere',
    ];

    private $from = '/automated[@\.]airbnb\.com/i';

    private $prov = 'airbnb';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $n = xpath('//text()[contains(., "Ejendom")]/following-sibling::a[1]');

                        if ($n->length == 0) {
                            return null;
                        }
                        $name = node('.', $n->item(0));
                        $browser = new \HttpBrowser("none", new \CurlDriver());
                        $browser->GetURL(node('./@href', $n->item(0)));
                        $address = $browser->FindSingleNode('(//*[@id = "display-address"]//text()[string-length(normalize-space(.)) > 1])[1]');

                        if (!$address) {
                            $address = orval(re('#at din anmodning om at reservere 2 br. apt, (downtown), free parking. er blevet indsendt#'));
                        }
                        $type = $browser->FindSingleNode("//*[normalize-space(text())='Ejendomstype']/following::text()[string-length(normalize-space(.))>1][1]");

                        return [
                            'HotelName' => $name,
                            'Address'   => $address,
                            'RoomType'  => $type,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check-ind', 'CheckOut' => 'Check-ud'] as $key => $value) {
                            if (preg_match('#' . $value . ':\s+\w+,\s+(\d+)\.\s+(\w+)\s+(\d+)#i', $text, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ' ' . en($m[2]) . ' ' . $m[3]);
                            }
                        }

                        return $res;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Antal\s+gæster:\s+(\d+)#i');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Pris:\s+(.*)#i'), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Du skal vide, at din anmodning om at reservere.*?er blevet (indsendt)#i');
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
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

    public static function getEmailLanguages()
    {
        return ["da"];
    }
}
