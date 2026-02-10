<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Engine\MonthTranslate;

class DeltaEBoardingPass extends \TAccountChecker
{
    public $mailFiles = "delta/it-2245988.eml, delta/it-8202658.eml";
    public $lang = "en";

    protected $year = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@delta.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'check-in_noreply@delta.com') !== false
            || stripos($headers['subject'], 'Delta E-Boarding Pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getPlainBody(), '//ebp.delta.com/mobiqa/') !== false || stripos($parser->getPlainBody(), 'mobile.lufthansa.com/mbp/') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            return [];
        }

        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        $textBody = $parser->getPlainBody();

        $bp = [];

        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $it['TripSegments'] = [];

        $seg = [];

        if (preg_match('/^\s*([A-Z\d]{2})\s*(\d+)[,\s]+([A-Z]{3})\s+(?:TO|to|To)\s+([A-Z]{3})[,\s]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)[,\s]+(.+)/m', $textBody, $m)) {
            $seg['AirlineName'] = $m[1];
            $bp['FlightNumber'] = $seg['FlightNumber'] = $m[2];
            $bp['DepCode'] = $seg['DepCode'] = $m[3];
            $seg['ArrCode'] = $m[4];
            $bp['DepDate'] = $seg['DepDate'] = strtotime($this->normalizeDate($m[6]) . ', ' . $m[5]);
            $seg['ArrDate'] = MISSING_DATE;
        } elseif (preg_match('/([A-Z\d]{2})(\d+), ([A-Z]{3})\-([A-Z]{3}), (\d{1,2})([A-Z]{3})(\d{2,4}), Boarding (\d{1,2}:\d{2}), Gate \w+, Sitz ([A-Z\d]{1,4})/', $textBody, $m)) {
            $seg['AirlineName'] = $m[1];
            $bp['FlightNumber'] = $seg['FlightNumber'] = $m[2];
            $bp['DepCode'] = $seg['DepCode'] = $m[3];
            $seg['ArrCode'] = $m[4];
            $bp['DepDate'] = $seg['DepDate'] = strtotime($this->normalizeDate($m[5] . ' ' . $m[6] . ' ' . $m[7]) . ', ' . $m[8]);
            $seg['Seats'][] = $m[9];
            $seg['ArrDate'] = MISSING_DATE;
        }
        $it['TripSegments'][] = $seg;

        if (preg_match('/^\s*(?<url>https?:\/\/ebp\.delta\.com\/mobiqa(\/wap)?[-\w\/]+)$/mi', $textBody, $m)) {
            $bp['BoardingPassURL'] = $m['url'];
        } elseif (preg_match('/Bordkarte (?<url>https\:\/\/mobile\.lufthansa\.com\/mbp\/.+)/', $textBody, $m)) {
            $bp['BoardingPassURL'] = $m['url'];
        }

        return [
            'parsedData' => [
                'Itineraries'  => [$it],
                'BoardingPass' => [$bp],
            ],
            'emailType' => 'BoardingPass',
        ];
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})([^,.\d]{3,})(\d{2,4})?/', $string, $matches)) { // 09DEC
            $day = $matches[1];
            $month = $matches[2];

            if (!empty($matches[3])) {
                $year = $matches[3];
            } else {
                $year = $this->year;
            }
        } elseif (preg_match('/^[^,.\d]{2,}[,\s]+([^,.\d]{3,})\s+(\d{1,2})[,\s]+(\d{4})/', $string, $matches)) { // Thu, Aug 24, 2017
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }
}
