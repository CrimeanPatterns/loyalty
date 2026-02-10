<?php

namespace AwardWallet\Engine\dollar\Email;

use PlancakeEmailParser;

class It2125611 extends \TAccountCheckerExtended
{
    public $mailFiles = "dollar/it-2125564.eml, dollar/it-2125588.eml, dollar/it-2125605.eml, dollar/it-2125611.eml";

    private $from = "#\.dollar\.#i";

    private $prov = "dollar";

    private $detects = [
        'THANK YOU FOR SHOPPING DOLLAR',
    ];

    private $subjects = [
        'Your Dollar Rent A Car Reservation',
        'Dollar Rent A Car Reservation Confirmation',
    ];

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
                        return re("#(?:Confirmation\s*\#|confirmation number)\s*:\s*([A-Z\d-]+)#x");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pickup Location\s*:\s*([^\n]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Pickup Date/Time\s*:\s*([^\n]+)#")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Return Location\s*:\s*([^\n]+)#");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Return Date/Time\s*:\s*([^\n]+)#")));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pickup Location Phone\s*:\s*([^\n]+)#");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Return Location Phone\s*:\s*([^\n]+)#");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return trim(orval(
                            re("#\s+from your friends at\s*([^\n.]+)#x"),
                            re("#THANK YOU FOR SHOPPING\s+([^\n.]+)#")
                        ));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarType'  => re("#\n\s*Vehicle Type\s*:\s*([^\n]*?)\s+\(([^\)]+)\)#"),
                            'CarModel' => re(2),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return niceName(orval(
                            re("#\n\s*We look forward to seeing you,\s*([^\n.]+)#x"),
                            re("#\n\s*Name\s*:\s*([^\n]+)#")
                        ));
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Promo Code\s*:\s*([^\n]+)#ix");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total Estimated Charges\s*:\s*([^\n]+)#"));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $tax = 0;
                        re("#\n\s*[^\n]*?[ \t]+(?:CHARGE|TAX)\s*:\s*([^\n]+)#", function ($m) use (&$tax) {
                            $tax += cost($m[1]);
                        }, $text);

                        return $tax;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Dollar Express ID\s*:\s*([^\n]+)#ix");
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\s+Discount\s*:\s*([^\n]+)#"));
                    },

                    "Fees" => function ($text = '', $node = null, $it = null) {
                        $fees = [];
                        re("#\n\s*([^\n]*?)[ \t]+FEE\s*:\s*([^\n]+)#", function ($m) use (&$fees) {
                            $fees[] = ['Name' => $m[1], 'Charge' => cost($m[2])];
                        }, $text);

                        return $fees;
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
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }
}
