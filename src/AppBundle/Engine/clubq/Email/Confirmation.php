<?php

namespace AwardWallet\Engine\clubq\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "clubquarters.com";
    public $reBody = [
        'en' => ['Reservation Confirmed', 'View Hotel'],
    ];
    public $reSubject = [
        '#Club\s+Quarters\s+Hotel.+?Reservation\s+Confirmation:\s+[A-Z\d]+#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Confirmation",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'clubquartershotels.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Confirmation') . "')]/ancestor::td[1]//text()[string-length(normalize-space(.))>1][not(contains(.,'" . $this->t('Confirmation') . "'))]", null, false, "#[A-Z\d]+#");
        $it['GuestNames'] = $this->http->FindNodes("//text()[contains(.,'Guest Name')]/ancestor::td[1]//text()[string-length(normalize-space(.))>1][not(contains(.,'Guest Name'))]");
        $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//text()[contains(.,'Arrival Date')]/ancestor::td[1]", null, false, "#Arrival Date\s*(.+)#"));
        $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//text()[contains(.,'Departure Date')]/ancestor::td[1]", null, false, "#Departure Date\s*(.+)#"));
        $it['Rooms'] = $this->http->FindSingleNode("//text()[contains(.,'# of Rooms')]/ancestor::td[1]", null, false, "/# of Rooms\s*(\d+)/");
        $it['Guests'] = $this->http->FindSingleNode("//text()[contains(.,'# of Guests')]/ancestor::td[1]", null, false, "/# of Guests\s*(\d+)/");
        $it['HotelName'] = $this->http->FindSingleNode("//text()[contains(.,'Your Hotel')]/ancestor::table[1]/following-sibling::table[1]//a[contains(.,'View Hotel')]/ancestor::td[1]/descendant::a[1]/ancestor::p[1]");
        $it['Address'] = $this->http->FindSingleNode("//text()[contains(.,'Your Hotel')]/ancestor::table[1]/following-sibling::table[1]//a[contains(.,'View Hotel')]/ancestor::td[1]/descendant::a[1]/ancestor::p[1]/following-sibling::p[1]");
        $it['Phone'] = $this->http->FindSingleNode("//text()[contains(.,'Your Hotel')]/ancestor::table[1]/following-sibling::table[1]//a[contains(.,'View Hotel')]/ancestor::td[1]/descendant::a[1]/ancestor::p[1]/following-sibling::p[2]");
        $it['Rate'] = $this->http->FindSingleNode("//text()[contains(.,'Average Nightly Rate')]/ancestor::td[1]", null, false, "/Average Nightly Rate\s*(.+?)\s*$/");
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Total Cost')]/ancestor::td[1]", null, false, "#Total Cost\s*\*?\s*(.+)#"));

        if (!empty($tot['Total'])) {
            $it['Total'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $it['RoomType'] = $this->http->FindSingleNode("//text()[contains(.,'Room Type')]/ancestor::td[1]", null, false, "/Room Type\s*(.+?)\s*$/");

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
