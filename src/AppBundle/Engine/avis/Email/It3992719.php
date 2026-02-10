<?php

namespace AwardWallet\Engine\avis\Email;

// and bcdtravel
class It3992719 extends \TAccountChecker
{
    use \PriceTools;
    public $mailFiles = "avis/it-2167843.eml, avis/it-2167844.eml, avis/it-2168530.eml, avis/it-2177810.eml, avis/it-2261853.eml, avis/it-2263216.eml, avis/it-2353320.eml, avis/it-3130488.eml";

    public $reFrom = "avis@e.avis.com";
    public $reSubject = [
        'en' => ['Reservation Modify'],
    ];
    public $reBody = 'Avis';
    public $reBody2 = [
        "en"=> ["If you have any questions please", "If you have any questions please email", "your vehicle has been reserved"],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it['Kind'] = "L";

        $it['Number'] = $this->getField("Your Confirmation Number:");

        $it['RenterName'] = $this->http->FindSingleNode("//*[contains(text(), 'Thank you ')]", null, false, '/Thank you\s+(.+?),/');

        $carImageUrl = $this->http->FindSingleNode("//*[contains(text(), 'Your Car')]/following::img[1]/@src");

        if (!empty($carImageUrl) && strpos($carImageUrl, 'cid:') === false) {
            $it['CarImageUrl'] = $carImageUrl;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(.), 'your car has been reserved')]")->length > 0) {
            $it['Status'] = 'reserved';
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(.), 'reservation has been canceled')]")->length > 0) {
            $it['Status'] = 'canceled';
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(.), 'reservation has been canceled')]")->length > 0) {
            $it['Cancelled'] = true;
        }

        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->getField("Pick up:")));

        if (empty($it['PickupDatetime'])) {
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space(.)='Pick up:']/ancestor::td[1]", null, true, "#Pick up:\s*(.+)#")));
        }

        $it['PickupLocation'] = implode(", ", $this->http->FindNodes("//text()[normalize-space(.)='Pick Up Location']/ancestor::tr[1]/following-sibling::tr[position()<4]"));

        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->getField("Drop off:")));

        if (empty($it['DropoffDatetime'])) {
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space(.)='Drop off:']/ancestor::td[1]", null, true, "#Drop off:\s*(.+)#")));
        }

        $it['DropoffLocation'] = implode(", ", $this->http->FindNodes("//text()[normalize-space(.)='Drop Off Location']/ancestor::tr[1]/following-sibling::tr[position()<4]"));

        $it['PickupPhone'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Pick Up Location']/ancestor::tr[1]/following-sibling::tr[4]");

        $it['PickupHours'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Pick Up Location']/ancestor::tr[1]/following-sibling::tr[5]");

        $it['DropoffPhone'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Drop Off Location']/ancestor::tr[1]/following-sibling::tr[4]");

        $it['DropoffHours'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Drop Off Location']/ancestor::tr[1]/following-sibling::tr[5]");

        $car = $this->http->FindSingleNode("//img[contains(@src, '/car-rental/') or contains(@alt, '/car-rental/')]/ancestor::tr[1]/following-sibling::tr[1]");

        if (preg_match('/([\w\s]+)\s*-\s*([\w\s]+)/', $car, $matches)) {
            $it['CarType'] = $matches[1];
            $it['CarModel'] = $matches[2];
        } else {
            $it['CarModel'] = $car;
        }

        $total = $this->getField("Estimated Total:");
        $it['TotalCharge'] = $this->isZero(preg_replace('/[^\d.]+/', '', $total));
        $it['Currency'] = preg_replace(['/[\d.,\s\\\\]+/', '/€/',  '/^\$$/'], ['', 'EUR', 'USD'], $total);
        $it['BaseFare'] = $this->isZero(preg_replace('/[^\d.]+/', '', $this->getField('Base Rate:')));

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        if ($this->detect($headers["subject"], $this->reSubject)) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        if ($this->lang = $this->detect($body, $this->reBody2)) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $this->http->FilterHTML = false;
        $itineraries = [];

        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method');

            return;
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'CarModified',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    protected function isZero($string)
    {
        if (is_numeric($string)) {
            return (float) $string;
        }
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param type $haystack
     * @param type $arrayNeedle
     *
     * @return type
     */
    private function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (strpos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } else {
                if (strpos($haystack, $needles) !== false) {
                    return $lang;
                }
            }
        }
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][1]");
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dictionary[$this->lang][$s])) {
            return self::$dictionary[$this->lang][$s];
        }

        return $s;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            // Thu Nov 20, 2014 at 05:30 PM
            "#^\w+\s+(\w+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)$#",
            // Wed Jan 07, 2015 at 0800 hours
            "/^\w+ (\w+) (\d+), (\d{4}) at (\d{4}) hours?$/",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
        ];

        return $this->translateDate('/\d+ (\w+) \d+, .+/', preg_replace($in, $out, trim($str)), $this->lang);
    }

    private function translateDate($pattern, $string, $lang)
    {
        if (preg_match($pattern, $string, $matches)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($matches[1], $lang)) {
                return str_replace($matches[1], $en, $matches[0]);
            } else {
                return $matches[0];
            }
        }
    }
}
