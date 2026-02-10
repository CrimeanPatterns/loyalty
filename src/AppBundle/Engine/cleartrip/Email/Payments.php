<?php

namespace AwardWallet\Engine\cleartrip\Email;

class Payments extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "cleartrip/it-4354616.eml";
    public $reBody = [
        'en' => ['Complete', 'payment'],
    ];
    public $pdf;
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record locator'        => 'yourtripid',
            'Passenger'             => 'travellers',
            'Your booking details'  => 'Your booking details',
            'Date of booking'       => 'Invoice Date',
            'Ticket'                => 'Tax. Amount',
            'Total cost'            => 'Total (Inclusive of all taxes)',
            'Please click the link' => 'Please click the link',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Payments',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('/Cleartrip[\w\s]*Private\s+Limited/i', $this->http->Response['body'])
            && $this->http->XPath->query('//a[contains(@href,"//www.cleartrip.com")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@cleartrip.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cleartrip.com') !== false;
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
        $NBSP = chr(194) . chr(160);

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Please click the link') . "')]/..//*[contains(translate(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),' " . $NBSP . "',''),'" . $this->t('Record locator') . "')]/strong", null, true, "#(\d+)#");

        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'), '" . $this->t('Passenger') . "')]/../following::tr[1]"));

        $tmpCostCurr_ = $this->http->FindSingleNode("//*[contains(text(),'Payment details')]/../following::tr[1]//span[contains(text(),'" . $this->t('Total cost') . "')]/preceding::span");

        if (preg_match('#(.+)\s+([0-9,\.]+)#', $tmpCostCurr_, $m)) {
            $it['TotalCharge'] = cost((strpos($m[2], '.') !== false) ? $m[2] : $m[2] . '.0');
            $it['Currency'] = currency($m[1]);
        }
        $segs = [];
        ////table//td[contains(text(),'Your booking details')]/../following-sibling::*
        $SUBNODE = "//table//td[contains(text(),'" . $this->t('Your booking details') . "')]/../following-sibling::*";
        $flightNum = $this->http->FindNodes($SUBNODE . "/td//td[@style='font-size:12px;color:#444444;vertical-align:top;line-height:13px; padding-bottom:15px;']", null, '#.+\s.+?\-\d+#');
        $dateFly = $this->http->FindNodes($SUBNODE . "//table//td[contains(@style,'color:#000000;')]//small[contains(@style,'color: #999;')]/preceding::small[contains(@style,'font-size: 11px') and not(contains(@style,'color: #999;'))]", null, '#\d{1,2}\s[A-Z]{3}\s\d{4}#i');
        $timeFly = $this->http->FindNodes($SUBNODE . "//table//td[contains(@style,'font-size:14px;') and contains(@style,'color:#000000;')]/strong", null, "#[0-2]\d\:[0-5]\d#");
        $airports = $this->http->FindNodes($SUBNODE . "//table//td[contains(@style,'color:#000000;')]//small[contains(@style,'color: #999;')]");
        $airCodes = $this->http->FindNodes($SUBNODE . "//table//td[contains(@style,'font-size:14px;') and contains(@style,'color:#000000;')]/text()[normalize-space(.)!='']", null, '#[A-Z]{3}#');

        for ($i = 0; $i < count($flightNum); $i++) {
            $this->http->Log($flightNum[$i], LOG_LEVEL_ERROR);

            if (preg_match('#.+\s(.+?)\-(\d+)#', $flightNum[$i], $m)) {
                $segs[$i]['FlightNumber'] = trim($m[2]);
                $segs[$i]['AirlineName'] = trim($m[1]);
            }

            $m = explode(',', $airports[$i * 2]);
            $segs[$i]['DepName'] = trim($m[1]);

            $m = explode(',', $airports[$i * 2 + 1]);
            $segs[$i]['ArrName'] = trim($m[1]);

            $segs[$i]['DepCode'] = trim($airCodes[$i * 2]);
            $segs[$i]['ArrCode'] = trim($airCodes[$i * 2 + 1]);
            $segs[$i]['DepDate'] = strtotime($this->normalizeDate($dateFly[$i * 2] . '  ' . $timeFly[$i * 2]));
            $segs[$i]['ArrDate'] = strtotime($this->normalizeDate($dateFly[$i * 2 + 1] . '  ' . $timeFly[$i * 2 + 1]));
        }

        for ($i = 0; $i < count($flightNum); $i++) {
            $it['TripSegments'][$i] = $segs[$i];
        }

        return [$it];
    }

    /**
     * example: 27-Sep-13, 27.09.13.
     *
     * @param $date
     *
     * @return mixed
     */
    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
