<?php

namespace AwardWallet\Engine\fluege\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

// parsers similar: engine/appintheair/Email/CheckInStatus.php, engine/edreams/Email/BoardingPass.php
class CheckIn extends \TAccountChecker
{
    public $mailFiles = "fluege/it-8760445.eml, fluege/it-8777633.eml";

    protected $subjects = [
        'de' => ['Status Ihres Check-ins'],
    ];

    protected $pdf;
    protected $lang = '';
    protected $langPdf = '';
    protected $dateRelative;

    protected $langDetectors = [
        'de' => ['Buchungsnummer:'],
    ];

    protected $pdfDetectors = [
        'de' => ['Passagier Status'],
        'en' => ['Passenger Status'],
    ];

    protected static $dict = [
        'de' => [],
    ];

    protected static $dictPdf = [
        'de' => [],
        'en' => [
            'Klasse' => 'Class',
            'Sitz'   => 'Seat',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fluege.de') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@fluege.de') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = $this->http->XPath->query('//node()[contains(.,"fluege.de")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"fluege.mycockpit.com") or contains(@href,"//www.fluege.de")]')->length === 0;
        $condition3 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 && $condition3 === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->dateRelative = strtotime($parser->getDate()); //EmailDateHelper::calculateOriginalDate($this, $parser);
        $this->assignLang();

        $htmlPdfFull = '';

        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            foreach ($this->pdfDetectors as $langPdf => $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($htmlPdf, $phrase) !== false) {
                        $htmlPdfFull .= $htmlPdf;
                        $this->langPdf = $langPdf;

                        break 2;
                    }
                }
            }
        }

        if ($htmlPdfFull) {
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdfFull);
        }

        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'CheckIn_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function tPdf($phrase)
    {
        if (!isset(self::$dictPdf[$this->langPdf]) || !isset(self::$dictPdf[$this->langPdf][$phrase])) {
            return $phrase;
        }

        return self::$dictPdf[$this->langPdf][$phrase];
    }

    protected function parseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[normalize-space(.)="Buchungsnummer:"]/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        $passengers = [];

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[./td[4] and ./td[1][.//img] and ./td[3][.//img] and not(.//tr)]');

        foreach ($segments as $segment) {
            $seg = [];

            $flight = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][1][contains(normalize-space(.),'Flug')]", $segment);

            if (preg_match('/Flug\s+([A-Z]{3})\s*(\d+)/', $flight, $matches) || preg_match('/Flug\s+([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
                // parsing Cabin, DepartureTerminal, Seats from PDF-attachments
                if (isset($this->pdf)) {
                    $variants = [
                        $seg['AirlineName'] . $seg['FlightNumber'],
                        $seg['AirlineName'] . ' ' . $seg['FlightNumber'],
                    ];
                    $xpathFragment0 = '//text()[' . $this->eq($variants) . ']';
                    $class = $this->pdf->FindSingleNode($xpathFragment0 . '/preceding::text()[position()<20][normalize-space(.)="' . $this->tPdf('Klasse') . '"]/following::text()[normalize-space(.)][1]', null, true, '/^([\w\s]{4,})$/');

                    if ($class) {
                        $seg['Cabin'] = $class;
                    }
                    $terminalDep = $this->pdf->FindSingleNode($xpathFragment0 . '/following::text()[position()<18][normalize-space(.)="Terminal"]/following::text()[normalize-space(.)][1]', null, true, '/^(\d|[A-Z]+)$/');

                    if ($terminalDep) {
                        $seg['DepartureTerminal'] = $terminalDep;
                    }
                    $seats = $this->pdf->FindNodes($xpathFragment0 . '/following::text()[position()<20][normalize-space(.)="' . $this->tPdf('Sitz') . '"]/following::text()[string-length(normalize-space(.))>1][1]', null, '/^(\d{1,2}[A-Z])$/');
                    $seatValues = array_values(array_filter($seats));

                    if (!empty($seatValues[0])) {
                        $seg['Seats'] = array_unique($seatValues);
                    }
                }
            }

            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[contains(normalize-space(.),'Abflug')]/ancestor::td[1]/descendant::text()[normalize-space()!=''][position()>1]",
                    $segment));

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)\s+(.+)\s+(\d+:\d+(?:(?i)\s*[ap]m)?)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $dateDep = $m[3];
                $timeDep = $m[4];

                if ($dateDep = $this->normalizeDate($dateDep)) {
                    $dateDep = EmailDateHelper::parseDateRelative($dateDep, $this->dateRelative);
                    $seg['DepDate'] = strtotime($timeDep, $dateDep);
                }
            }
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[contains(normalize-space(.),'Ankunft')]/ancestor::td[1]/descendant::text()[normalize-space()!=''][position()>1]",
                    $segment));

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)\s+(.+)\s+(\d+:\d+(?:(?i)\s*[ap]m)?)#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $dateArr = $m[3];
                $timeArr = $m[4];

                if ($dateArr = $this->normalizeDate($dateArr)) {
                    $dateArr = EmailDateHelper::parseDateRelative($dateArr, $this->dateRelative);
                    $seg['ArrDate'] = strtotime($timeArr, $dateArr);
                }
            }

            $passenger = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][1]/td[1]/descendant::text()[normalize-space(.)!=''][last()]",
                $segment);

            if ($passenger) {
                $passengers[] = $passenger;
            }

            $it['TripSegments'][] = $seg;
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        return $this->uniqueTripSegments($it);
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\s+([^,.\d\s]{3,})$/', $string, $matches)) { // 02 Okt
            $day = $matches[1];
            $month = $matches[2];
        }

        if (isset($day,$month)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1];
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month;
        }

        return false;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
