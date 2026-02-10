<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Engine\MonthTranslate;

class RouteReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-7245713.eml, aeroflot/it-7380753.eml, aeroflot/it-7390547.eml, aeroflot/it-7453978.eml";

    protected $reSubjects = [
        'ru' => ['/^\s*АЭРОФЛОТ.+билет/i'],
    ];

    protected $langDetectors = [
        'ru' => ['Номер брони:', 'ИНФОРМАЦИЯ О ПЕРЕВОЗКЕ'],
    ];

    protected $lang = '';

    protected static $dict = [
        'ru' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubjects as $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'АЭРОФЛОТ-РОССИЙСКИЕ АВИАЛИНИИ') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);
            $this->assignLang($textPdf);

            if ($it = $this->parsePdf($textPdf)) {
                return [
                    'parsedData' => [
                        'Itineraries' => [$it],
                    ],
                    'emailType' => 'RouteReceiptPDF_' . $this->lang,
                ];
            }
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function sliceText($textSource = '', $textStart = '', $textEnd = '')
    {
        if (empty($textSource) || empty($textStart)) {
            return false;
        }
        $start = strpos($textSource, $textStart);

        if (empty($textEnd)) {
            return substr($textSource, $start);
        }
        $end = strpos($textSource, $textEnd);

        if ($start === false || $end === false) {
            return false;
        }

        return substr($textSource, $start, $end - $start);
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $string, $matches)) { // 19.11.2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 19/11/2015
            $day = $matches[1];
            $month = $matches[2];
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

    protected function parseRoute(&$seg, $route)
    {
        // Москва (Аэропорт Шереметьево), TERMINAL D - DOMESTIC/INTL - Санкт-Петербург (Аэропорт Пулково), PULKOVO 1
        $pattern = '([^(]{2,}) \(([^)]{2,})\)(?:, ([^)(]+))?';
        preg_match('/' . $pattern . ' - ' . $pattern . '/', $route, $matches);
        $seg['DepName'] = $matches[1] . ', ' . $matches[2];

        if (!empty($matches[3])) {
            $seg['DepartureTerminal'] = $matches[3];
        }
        $seg['ArrName'] = $matches[4] . ', ' . $matches[5];

        if (!empty($matches[6])) {
            $seg['ArrivalTerminal'] = $matches[6];
        }
    }

    protected function parsePdf($textPdf)
    {
        $text = $this->sliceText($textPdf, 'Номер брони:', 'ВАЖНАЯ ИНФОРМАЦИЯ ПАССАЖИР');

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/^\s*Номер брони:\s*([A-Z\d]{5,})\s*$/m', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        $passengerText = $this->sliceText($text, 'Фамилия, имя', 'ИНФОРМАЦИЯ О ПЕРЕВОЗКЕ');

        if (preg_match_all('/^\s*([A-Z]+, [A-Z ]+?)  .+  ([-\d]+)?\s*$/m', $passengerText, $passengerMatches)) {
            $it['Passengers'] = $passengerMatches[1];

            if (!empty($passengerMatches[2])) {
                $it['TicketNumbers'] = $passengerMatches[2];
            }
        }

        $it['TripSegments'] = [];
        $segmentsText = $this->sliceText($text, 'ИНФОРМАЦИЯ О ПЕРЕВОЗКЕ', 'ЖНАЯ ИНФОРМАЦИЯ');
        $segmentsRows = explode("\n", $segmentsText);
        $route = '';

        foreach ($segmentsRows as $segmentsRow) {
            if (preg_match('/^\s*([A-Z\d]{2}) (\d+)[ ]+(.+?) [ ]{2,}(\d{1,2}:\d{2}) (\d{1,2}[.\/]\d{1,2}[.\/]\d{4})[ ]+(\d{1,2}:\d{1,2}) (\d{1,2}[.\/]\d{1,2}[.\/]\d{4})[ ]+(.+)/', $segmentsRow, $matches)) {
                if (isset($seg) && count($seg)) {
                    $this->parseRoute($seg, $route);
                    $it['TripSegments'][] = $seg;
                }
                $seg = [];
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
                $route = $matches[3];
                $seg['DepDate'] = strtotime($this->normalizeDate($matches[5]) . ', ' . $matches[4]);
                $seg['ArrDate'] = strtotime($this->normalizeDate($matches[7]) . ', ' . $matches[6]);

                if (preg_match('/^\s*([.\d]+)[ ]+(\d+)/', $matches[8], $m)) {
                    $seg['Duration'] = $m[1];
                    $seg['TraveledMiles'] = $m[2];
                }
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            } elseif (preg_match('/^[ ]{3,}(.+)/', $segmentsRow, $matches)) {
                $route .= ' ' . $matches[1];
            }
        }

        if (empty($route)) {
            return null;
        }

        if (count($seg)) {
            $this->parseRoute($seg, $route);
            $it['TripSegments'][] = $seg;
        }

        $paymentText = $this->sliceText($text, 'Платёжный документ');

        if (preg_match('/   ([.\d]+) ([.\w ]+)/u', $paymentText, $matches)) {
            $it['TotalCharge'] = $matches[1];
            $it['Currency'] = $matches[2];
        }

        return $it;
    }

    protected function assignLang($textPdf)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($textPdf, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
