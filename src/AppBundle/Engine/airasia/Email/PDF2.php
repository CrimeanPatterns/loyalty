<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\airasia\Email;

class PDF2 extends \TAccountChecker
{
    public $mailFiles = "airasia/it-4561465.eml";
    public $pdf;
    public $lang = '';
    public static $reBody = [
        'en' => ['Airport Guide', 'Booking no'],
    ];

    public function getNode($str, $root)
    {
        return $this->pdf->FindSingleNode("following-sibling::p[contains(., '" . $str . "')][1]/following-sibling::p[1]", $root);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!empty($pdfs)) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetBody($html);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $body = $this->pdf->Response['body'];

        if (!empty(self::$reBody)) {
            foreach (self::$reBody as $lang => $re) {
                if (stripos($body, $re[0]) !== false && stripos($body, $re[1]) !== false) {
                    $this->lang = $lang;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (self::$reBody as $lang => $strFromBody) {
                if (stripos($text, $strFromBody[0]) !== false && stripos($text, $strFromBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@airasia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@airasia') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("(//p[contains(., 'Booking')]/following-sibling::p[1])[1]");
        $it['Passengers'][] = $this->pdf->FindSingleNode("(//p[contains(., 'Depart')]/preceding-sibling::p[1])[1]");
        $xpath = "//p[normalize-space(.)='Depart']";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $xpath = "//p[contains(normalize-space(.), 'Depart')]";
            $roots = $this->pdf->XPath->query($xpath);
        }

        if ($roots->length === 0) {
            $this->pdf->Log('Segments not found: ' . $xpath, LOG_LEVEL_NORMAL);

            return false;
        }

        foreach ($roots as $root) {
            $seg = [];
            $depName = $this->getNameCode($this->pdf->FindSingleNode('following-sibling::p[1]', $root));

            if ($depName !== null) {
                $seg['DepName'] = $depName['Name'];
                $seg['DepCode'] = $depName['Code'];
            }
            $arrName = $this->getNameCode($this->getNode('Arrive', $root));

            if ($arrName !== null) {
                $seg['ArrName'] = $arrName['Name'];
                $seg['ArrCode'] = $arrName['Code'];
            }
            $flight = $this->getNode('Flight', $root);

            if (preg_match('#(\w{1,2})\s+(\d+)#', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $date = $this->getNode('Date', $root);
            $timedep = $this->pdf->FindSingleNode("following-sibling::p[contains(., 'Boarding time')]/following-sibling::p[1]", $root);

            if (!empty($date)) {
                $seg['DepDate'] = (!empty($timedep)) ? strtotime($date . ' ' . $timedep) : strtotime($date);
                $seg['ArrDate'] = strtotime($date);
            }
            $seg['Seats'] = $this->getNode('Seat', $root);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    /**
     * example: Kuala Lumpur - LCCT (KUL),
     *          Singapore Changi Terminal 1 (SIN)
     *          Kathmandu (KTM).
     *
     * @param $str
     *
     * @return array|null
     */
    private function getNameCode($str)
    {
        $res = [];

        if (preg_match('#(.+)\s+\((\D{3})\)#i', $str, $m)) {
            $res = [
                'Name' => $m[1],
                'Code' => $m[2],
            ];
        }

        return (!empty($res)) ? $res : null;
    }

    private function t($str)
    {
        if (!isset($this->lang) && !isset(self::$reBody[$this->lang][$str])) {
            return $str;
        }

        return self::$reBody[$this->lang][$str];
    }
}
