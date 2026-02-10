<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\edreams\Email;

class PDF3 extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4249856.eml";
    public $reBody = [
        'fr' => ['Passager', 'Aéroport'],
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public static $dict = [
        'fr' => [
            'segmentsTrip' => [
                'Date', 'Aéroport', 'Départ',
            ],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('reservation.*pdf');

        if (empty($pdfs)) {
            return null;
        }

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetBody($html);
                }
            }
        }
        $body = $this->pdf->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $its = $this->parseEmail($parser);

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@edreams.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@edreams.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//*[contains(text(), 'Numéro de référence eDreams')]", null, true, "#:\s+(\d+)#");
        $year = date('Y', strtotime($parser->getDate()));
        $it['Passengers'] = $this->pdf->FindNodes("//text()[contains(normalize-space(.), 'Bagages')]/following::*[normalize-space(.)!=''][position() = 2 or position() = 10]");
        $xpath = '//*[contains(text(), "' . $this->t('segmentsTrip')[2] . '")]';
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->pdf->Log("segments not found: {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $i => $root) {
            $seg = [];
            $seg['DepDate'] = $this->normalizeDate($this->pdf->FindSingleNode("following::*[normalize-space(.)!=''][1]", $root), $year);
            $dep = $this->processDataAboutFlight(implode(', ', $this->pdf->FindNodes("following::*[normalize-space(.)!=''][position() = 2 or position() = 3 or position() = 4]", $root)));

            if (count($dep) >= 2) {
                $seg['DepName'] = $dep['Name'];
                $seg['DepCode'] = $dep['Code'];
                $seg['DepartureTerminal'] = $dep['Terminal'];
            }
            $a = array_diff($this->pdf->FindNodes("following::*[normalize-space(.)!=''][position() = 5 or position() = 6]", $root, "#(\d+)#"), [null]);
            $seg['FlightNumber'] = array_shift($a);

            if (!empty($seg['FlightNumber'])) {
                $seg['AirlineName'] = $this->pdf->FindSingleNode("(./following-sibling::*[contains(normalize-space(.), '" . $seg['FlightNumber'] . "')])[1]", $root, true, "#\b([A-Z\d]{2})\s*" . $seg['FlightNumber'] . "#");
            }
            $seg['ArrDate'] = $this->normalizeDate($this->pdf->FindSingleNode("(./following-sibling::*[contains(normalize-space(.), 'Arrivée')])[1]/following::*[normalize-space(.)!=''][1]", $root), $year);
            $arr = $this->processDataAboutFlight(implode(', ', $this->pdf->FindNodes("(./following-sibling::*[contains(normalize-space(.), 'Arrivée')])[1]/following::*[normalize-space(.)!=''][position() != 1 and position() < 6]", $root)));

            if (count($arr) >= 2) {
                $seg['ArrName'] = $arr['Name'];
                $seg['ArrCode'] = $arr['Code'];
                $seg['ArrivalTerminal'] = $arr['Terminal'];
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    /**
     * example: Delhi (Inde) ­ Indira Gandhi International Airport, (DEL), Terminal 3, Type d’avion ­ 788 Classe ­ Economique
     *          Bangkok (Thaïlande) ­ Suvarnabhumi Airport, (BKK), Air India Limited.
     *
     * @param $str
     *
     * @return array
     */
    private function processDataAboutFlight($str)
    {
        if (preg_match("#(.+) \((\w{3})\), ([Terminal]* ([\w\d]{1,2}))*#", $str, $m)) {
            return [
                'Name'     => $m[1],
                'Code'     => $m[2],
                'Terminal' => (isset($m[4])) ? $m[4] : null,
            ];
        }

        return $str;
    }

    /**
     * example: 22:00 dim., 17 juil.
     *
     * @param $str
     * @param $year
     *
     * @return int
     */
    private function normalizeDate($str, $year)
    {
        $in = [
            '#(\d+:\d+)\s+.+(\d{2})\s+(\w+)#',
        ];
        $out = [
            "$3 $2 {$year} $1",
        ];

        return strtotime(en(preg_replace($in, $out, $str)));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
