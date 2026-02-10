<?php

namespace AwardWallet\Engine\costravel\Email;

class It1903445 extends \TAccountCheckerExtended
{
    public $rePlain = "#customercare@costcotravel.com|Costco Travel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    //var $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "2";
    public $reFrom = "#costcotravel#i";
    public $reProvider = "#costravel#i";
    public $caseReference = "9062";
    public $xPath = "";
    public $mailFiles = "costravel/it-1.eml, costravel/it-1903445.eml, costravel/it-1976636.eml, costravel/it-2005842.eml, costravel/it-2024315.eml, costravel/it-2033346.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "simpletable");

                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        $node = re("#[A-Za-z]+ Confirmation\s*Number:\s*([^\n]+)#");

                        if ($node == null) {
                            $node = re("#\n\s*Confirmation Number\s*:\s*([^\n]+)#");
                        }

                        if ($node != null) {
                            return $node;
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Pick-Up Location')]/ancestor-or-self::tr/following-sibling::tr[1]/td[2]");

                        if ($node == null) {
                            $date = node("//*[contains(text(), 'Pick-up')]/ancestor-or-self::tr[1]/td[2]/b");
                            $node = node("//*[contains(text(), 'Pick-up')]/ancestor-or-self::tr[1]/td[2]");
                            $node = str_replace($date, "", $node);
                        }

                        return trim($node);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Pick-up')]/ancestor-or-self::tr[1]/td[2]/b");

                        if ($node == null) {
                            $node = re("#Pick-Up Date/Time\s*([^\n]+)#");
                        }
                        $node = preg_replace("#\w*[.]*,\s*(\w+)[ .]+(\d{1,2})[,\s]+(\d{4})\s*(\d+:\d+.+)#", '$2 $1 $3, $4', $node);
                        $node = uberDatetime($node);

                        return totime($node);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Drop-Off Location')]/ancestor-or-self::tr/following-sibling::tr[1]/td[last()]");

                        if ($node == null) {
                            $date = node("//*[contains(text(), 'Drop-off')]/ancestor-or-self::tr[1]/td[2]/b");
                            $node = node("//*[contains(text(), 'Drop-off')]/ancestor-or-self::tr[1]/td[2]");
                            $node = str_replace($date, "", $node);
                        }

                        return trim($node);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Drop-off')]/ancestor-or-self::tr[1]/td[2]/b");

                        if ($node == null) {
                            $node = re("#Drop-Off Date/Time\s*([^\n]+)#");
                        }
                        $node = preg_replace("#\w*[.]*,\s*(\w+)[ .]+(\d{1,2})[,\s]+(\d{4})\s*(\d+:\d+.+)#", '$2 $1 $3, $4', $node);
                        $node = uberDatetime($node);

                        return totime($node);
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Pick-up hours:')]/ancestor-or-self::tr/following-sibling::tr[1]/td[2]");

                        return $node;
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Pick-up hours:')]/ancestor-or-self::tr/following-sibling::tr[1]/td[last()]");

                        return $node;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\s*([A-Za-z]+) Confirmation\s*Number:#");

                        return $node;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $node = node("//ul/preceding-sibling::h3");

                        if ($node != null) {
                            $node = re("#-\s*([^\n]+)#", $node);

                            return $node;
                        } else {
                            $node = node("//*[contains(text(), 'Category Name')]/ancestor-or-self::tr/following-sibling::tr[1]/td[2]");

                            return $node;
                        }
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $node = node("//ul/li[1]");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Car Details')]/ancestor-or-self::tr/following-sibling::tr[1]/td[11]");
                            $node = explode(". ", $node);
                        }

                        if (is_array($node)) {
                            return $node[0];
                        } else {
                            return $node;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Renter's Name\s*:\s*([^\n]+)#");

                        if ($node == null) {
                            $node = re("#Renter Name\s*:\s*([^\n]+)#");
                        }

                        return $node;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Total Rental Price", +1);

                        if ($node == null) {
                            $node = node("(//*[contains(text(), 'Rental Price')]/ancestor-or-self::tr/td[last()])[2]");
                        }

                        return total($node);
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Taxes & Fees", +1);

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Taxes and Fees')]/ancestor-or-self::tr/td[last()]");
                        }

                        return cost($node);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Your rental car has been successfully reserved#");

                        if ($node != null) {
                            return "confirmed";
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@costcotravel.com') !== false
            && isset($headers['subject']) && (
                stripos($headers['subject'], 'Costco Travel - Booking #') !== false
            );
    }
}
