<?php

namespace AwardWallet\Engine\budgetair\Email;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "budgetair/it-4692452.eml, budgetair/it-4693796.eml, budgetair/it-4693798.eml, budgetair/it-4728124.eml, budgetair/it-4728125.eml, budgetair/it-4728126.eml, budgetair/it-4728944.eml, budgetair/it-5197241.eml";
    public $lang = 'en';

    public static $dict = [
        'en' => [
            'Reservation code' => 'Reservation number',
        ],
        'nl' => [
            'Reservation code'                => 'Referentienummer',
            'Total'                           => 'Totaal',
            'Airline Failure Service'         => '(?:Boekingskosten|Factuurnummer|Totaal)',
            'Invoice date'                    => 'Te betalen voor',
            'Airline/flight number'           => 'Maatschappij/vluchtnummer',
            'Flight times and flight numbers' => 'Vluchttijden en -nummers zijn',
            'Return'                          => 'Terugreis',
            'Departure'                       => 'Vertrek',
            'Arrival'                         => 'Aankomst',
            'Payments processed up and'       => 'Betalingen verwerkt t',
        ],
    ];
    protected $pdf;
    protected $result = [];
    protected $recordLocator = [];
    protected $recordLocatorNotUnique = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('\d+\_\d+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('No Pdf file.', LOG_LEVEL_ERROR);

            return false;
        }
        $NBSP = chr(194) . chr(160);
        $pdfText = str_replace($NBSP, ' ', \PDF::convertToText($parser->getAttachmentBody($pdf[0])));

        if (!$this->getPDFs($parser)) {
            return null;
        }
        $this->AssignLang();

        $this->result['Kind'] = 'T';

        $pass = trim($this->findСutSection($pdfText, $this->t('Total'), $this->t('Airline Failure Service')));

        if (preg_match("#" . $this->t('Total') . "(.+)" . $this->t('Airline Failure Service') . "#s", $pdfText, $pass)) {
            //			var_dump($pass);
            //De heer Rui Franco 21/05/1986 180,00 108,58 € 288,58
            if (preg_match_all("#\s*(.+?)\s+\d{1,2}\/\d{1,2}\/\d{4}\s+[\d\.\,]+\s+[\d\.\,]+\s+\S{1,3}\s+[\d\.\,]+\s*(?:\d+x\s+.+?\d+\s+kg\.\s+[\d\,\.]+)?#s", $pass[1], $m, PREG_PATTERN_ORDER)) {
                if (is_array($m[1])) {
                    $this->result['Passengers'] = $m[1];
                } else {
                    $this->result['Passengers'][] = $m[1];
                }
            }
        }
        $this->result['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(.,'" . $this->t('Reservation code') . "')]/text()", null, false, "#(\w+)#");

        if (($rd = $this->pdf->FindSingleNode("//p[.='" . $this->t('Invoice date') . "']/following-sibling::p[1]", null, true, "#(\d{1,2}\/\d{1,2}\/\d{4})#"))) {
            $this->result['ReservationDate'] = strtotime(str_replace('/', '-', $rd));
        }

        if (($tmp = $this->pdf->FindSingleNode("//p[contains(.,'" . $this->t('Payments processed up and') . "')]/preceding-sibling::p[1]", null, true, "#([\d\.\,]+)#"))) {
            $this->result['TotalCharge'] = cost($tmp);
        }

        if (($tmp = $this->pdf->FindSingleNode("//p[contains(.,'" . $this->t('Payments processed up and') . "')]/preceding-sibling::p[2]", null, true, "#(\S{1,3})#"))) {
            $this->result['Currency'] = currency($tmp);
        }

        $this->parseSegments($this->findСutSection($pdfText, $this->t('Airline/flight number'), $this->t('Flight times and flight numbers')));

        return [
            'emailType'  => 'InvoicePdf',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'BudgetAir') !== false
            && isset($headers['subject']) && (stripos($headers['subject'], 'Factuur') !== false || stripos($headers['subject'], 'invoice') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (!$this->getPDFs($parser)) {
            return false;
        }

        $this->AssignLang();

        return $this->pdf->FindPreg('#' . $this->t('Reservation code') . '#i');
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'BudgetAir') !== false;
    }

    protected function parseSegments($pdfText)
    {
        foreach (preg_split('/' . $this->t('Departure') . '\s+/', $pdfText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            if (stripos($value, $this->t('Arrival')) !== false && mb_strlen($value) > 100) {
                $this->result['TripSegments'][] = $this->parseSegment($value);
            }
        }
    }

    protected function parseSegment($text)
    {
        $segment = [];

        if (preg_match('#(?<DepDate>\d{1,2}\/\d{1,2}\/\d{4})\s+(?<DepName>.+?)\s*+(?<DepTime>\d+\:\d+)\s+'
                . '(?<Operator>.+?)\n(?<DepName2>.*?)\s*' . $this->t('Arrival') . '#', $text, $matches)) {
            $segment['DepName'] = trim($matches['DepName']);

            if (isset($matches['DepName2']) && !empty($matches['DepName2'])) {
                $segment['DepName'] .= ' ' . trim($matches['DepName2']);
            }
            $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            $segment['DepDate'] = strtotime(str_replace('/', '-', $matches['DepDate']) . ' ' . $matches['DepTime']);
            $segment['Operator'] = $matches['Operator'];
        }

        // Arrival             21/03/2016       Tel Aviv Ben Gurion Intl. Apt.03:05      TK792
        if (preg_match('#' . $this->t('Arrival') . '\s+(?<ArrDate>\d+/\d+/\d{4})\s+(?<ArrName>.+?)\s*'
                . '(?<ArrTime>\d+:\d+)\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#', $text, $matches)) {
            $segment['ArrName'] = trim($matches['ArrName']);

            if (isset($matches['ArrName2']) && !empty($matches['ArrName2'])) {
                $segment['ArrName'] .= ' ' . trim($matches['ArrName2']);
            }
            $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $segment['ArrDate'] = strtotime(str_replace('/', '-', $matches['ArrDate']) . ' ' . $matches['ArrTime']);
            $segment['FlightNumber'] = $matches['FlightNumber'];
            $segment['AirlineName'] = $matches['AirlineName'];
        }

        return $segment;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getPDFs(\PlancakeEmailParser $parser)
    {
        if (isset($this->pdf)) {
            return true;
        }
        $pdfs = $parser->searchAttachmentByName('\w+\_\d+\.pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html_ = '';

            foreach ($pdfs as $pdf) {
                if (($html_ .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $ht = html_entity_decode($html_);
            $html_ = str_replace($NBSP, ' ', $ht);
            $this->pdf->SetBody($html_);

            return true;
        } else {
            return null;
        }
    }

    private function AssignLang()
    {
        if (isset($this->pdf)) {
            foreach (self::$dict as $lang => $re) {
                if ($this->pdf->FindPreg('#' . $re['Reservation code'] . '#i')) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
