<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\airasia\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "airasia/it-2056507.eml, airasia/it-2096839.eml, airasia/it-2096849.eml, airasia/it-4550045.eml";
    public $pdf;
    public $lang = '';
    public static $reBody = [
        'en' => 'Booking Number',
    ];

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

        if (!empty(self::$reBody)) {
            foreach (self::$reBody as $lang => $re) {
                if (stripos($this->pdf->Response['body'], $re) !== false) {
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
                if (stripos($text, $strFromBody) !== false) {
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
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(., 'Booking Number')]/following-sibling::p[3]");
        $passengers = $this->pdf->FindNodes("//p[contains(., 'Guest Details')]/following-sibling::p[contains(., 'MS') or contains(., 'MR')]", null, '#\d+\.\s+(.+)#');

        if (empty($passengers)) {
            $passengers = $this->pdf->FindNodes("//p[contains(., 'Name')]/following-sibling::p[1]", null, '#[:]*\s+(.+)#');
        }
        $it['Passengers'] = $passengers;
        $total = $this->pdf->FindSingleNode("//p[contains(., 'Total Amount')]/following-sibling::p[1]");

        if (preg_match('#([\d.]*)\s*(\w+)#', $total, $m)) {
            $it['TotalCharge'] = str_replace('.', '.', $m[1]);
            $it['Currency'] = $m[2];
        }
        $xpath = "//p[contains(., 'Departing')]/following-sibling::p/b[string-length(text())<7 and string-length(text())>3 and not(contains(., 'Guest'))]/ancestor::p[contains(@class, 'ft12')]"; //class-ft12, it's bad practice
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->pdf->Log('Segments not found: ' . $xpath, LOG_LEVEL_NORMAL);

            return null;
        }

        foreach ($roots as $root) {
            $seg = [];

            if (preg_match('#(\D{1,2})\s*(\d+)#', $this->pdf->FindSingleNode('.', $root), $math)) {
                $seg['AirlineName'] = $math[1];
                $seg['FlightNumber'] = $math[2];
            }

            $seg['Cabin'] = $this->getNode(1, $root);

            $depName = $this->getNameCodeAfterAplyRegExp($this->getNode(2, $root)); //3

            if ($depName === null) {
                $depName = $this->getNameCodeAfterAplyRegExp($this->getNode(3, $root));
            }

            if (count($depName) === 2) {
                $seg['DepName'] = $depName['Name'];
                $seg['DepCode'] = $depName['Code'];
            }

            $depDate = $this->normalizeDate($this->getNode(3, $root));

            if (empty($depDate)) {
                $depDate = $this->normalizeDate($this->getNode(4, $root));
            }
            $seg['DepDate'] = $depDate;

            $arrName = $this->getNameCodeAfterAplyRegExp($this->getNode(4, $root));

            if ($arrName === null) {
                $arrName = $this->getNameCodeAfterAplyRegExp($this->getNode(5, $root));
            }

            if (count($arrName) === 2) {
                $seg['ArrName'] = $arrName['Name'];
                $seg['ArrCode'] = $arrName['Code'];
            }

            $arrDate = $this->normalizeDate($this->getNode(5, $root));

            if (empty($arrDate)) {
                $arrDate = $this->normalizeDate($this->getNode(6, $root));
            }
            $seg['ArrDate'] = $arrDate;

            $seg['Seats'] = implode(', ', $this->pdf->FindNodes("//p[contains(., 'ASR')][last()]/following-sibling::p[string-length(text())=3]"));

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    /**
     * example: Sydney (SYD)Kingsford Smith Terminal 1.
     *
     * @param $str
     *
     * @return array|null
     */
    private function getNameCodeAfterAplyRegExp($str)
    {
        $res = [];

        if (preg_match('#(.+)\s*\((\D{3})\)#', $str, $mathec)) {
            $res = [
                'Name' => $mathec[1],
                'Code' => $mathec[2],
            ];
        }

        return (!empty($res)) ? $res : null;
    }

    /**
     * example: Sun 01 Dec 2013, 2020 hrs ( 8:20PM).
     *
     * @param $strDate
     *
     * @return mixed
     */
    private function normalizeDate($strDate)
    {
        $in = [
            '#(\d{2})\s+(\w+)\s+(\d+).*\(\s*(\d+:\d+\D+)\)#',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];

        return strtotime(preg_replace($in, $out, $strDate));
    }

    private function getNode($p, $root)
    {
        return $this->pdf->FindSingleNode("following-sibling::p[{$p}]", $root);
    }

    private function t($str)
    {
        if (!isset($this->lang) && !isset(self::$reBody[$this->lang][$str])) {
            return $str;
        }

        return self::$reBody[$this->lang][$str];
    }
}
