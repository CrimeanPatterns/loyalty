<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;

class PDF extends \TAccountChecker
{
    public $mailFiles = "egencia/it-5657362.eml, egencia/it-9008287.eml, egencia/it-9304875.eml";

    public $lang = '';

    public $reBody = 'Egencia';

    public $langDetectors = [
        'no' => ['Beskrivelse'],
        'fi' => ['Kuvaus'],
        'en' => ['Detail(s)'],
    ];

    public static $dictionary = [
        'no' => [
            //			'Booking ref' => '',
            'Detail(s)'         => 'Beskrivelse',
            "Traveller's Name " => 'Reisendes navn',
            //			'DERES REF/YOUR REF' => '',
            'E-ticket N°'     => 'e-ticket N°',
            '- Dep:'          => '- Avr:',
            'Due Date :'      => 'Forfallsdato :',
            'Total to refund' => 'Beløp totalt',
        ],
        'fi' => [
            'Booking ref'       => 'Varausnro',
            'Detail(s)'         => 'Kuvaus',
            "Traveller's Name " => 'Matkustajan nimi',
            //			'DERES REF/YOUR REF' => '',
            'E-ticket N°'     => 'Lippu N°',
            '- Dep:'          => '- Lähtö:',
            'Due Date :'      => 'Eräpäivä :',
            'Total to refund' => 'Maksettava summa',
        ],
        'en' => [],
    ];

    private $from = '@customercare.egencia.com';

    private $pdfText = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }

        $pdf = $pdfs[0];

        if (($this->pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        if ($this->assignLang($this->pdfText) === false) {
            return false;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'PDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang($text);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->from) !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $recLoc = $this->cutText($this->t('Booking ref'), $this->t('Detail(s)'), $this->pdfText);

        if (!empty($recLoc) && preg_match('/:\s+\b([A-Z\d]{5,7})\b/', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $textSegments = $this->pdfText;

        $start = strpos($textSegments, $this->t('Booking ref'));

        if ($start !== false) {
            $textSegments = substr($textSegments, $start);
        }

        $end = strpos($textSegments, $this->t("Traveller's Name "));

        if ($end !== false) {
            $textSegments = substr($textSegments, 0, $end);
        }

        $end = strpos($textSegments, $this->t('DERES REF/YOUR REF'));

        if ($end !== false) {
            $textSegments = substr($textSegments, 0, $end);
        }

        $patterns['date'] = '\d{1,2}\/\d{1,2}\/\d{2,4}';
        $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?';

        // KRS/OSL SK222 20/01/17 18:15 20/01/17 19:00
        $patterns['segments'] = '/^[ ]*(?<airportDep>[A-Z]{3})\/(?<airportArr>[A-Z]{3})[ ]+(?<airline>[A-Z\d]{2})(?<flightNumber>\d+)[ ]+(?<dateDep>' . $patterns['date'] . ')[ ]+(?<timeDep>' . $patterns['time'] . ')[ ]+(?<dateArr>' . $patterns['date'] . ')[ ]+(?<timeArr>' . $patterns['time'] . ')$/m';

        preg_match_all($patterns['segments'], $textSegments, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $matches) {
            $seg = [];
            $seg['DepCode'] = $matches['airportDep'];
            $seg['ArrCode'] = $matches['airportArr'];
            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNumber'];

            if ($dateDep = $this->normalizeDate($matches['dateDep'])) {
                $seg['DepDate'] = strtotime($dateDep . ', ' . $matches['timeDep']);
            }

            if ($dateArr = $this->normalizeDate($matches['dateArr'])) {
                $seg['ArrDate'] = strtotime($dateArr . ', ' . $matches['timeArr']);
            }
            $it['TripSegments'][] = $seg;
        }

        // E-ticket N° 1140595444 Class K
        if (preg_match_all('/^[ ]*' . $this->t('E-ticket N°') . '\s+(\d[-\d\/ ]{3,}\d)/m', $textSegments, $eTicketMatches)) {
            $it['TicketNumbers'] = array_unique($eTicketMatches[1]);
        }

        // BRU/KNUT GEORG MR - Dep:20/01/2017
        if (preg_match_all('/^[ ]*(.{4,}) ' . $this->t('- Dep:') . '/m', $textSegments, $passengerMatches)) {
            $it['Passengers'] = array_unique($passengerMatches[1]);
        }

        $textPayments = $this->pdfText;

        $start = strpos($textPayments, $this->t('Due Date :'));

        if ($start !== false) {
            $textPayments = substr($textPayments, $start);
        }

        if (preg_match('/' . $this->t('Total to refund') . '[ ]+(\d[.\d]*)[ ]*([A-Z ]{3,})$/m', $textPayments, $matches)) {
            $it['TotalCharge'] = $matches[1];
            $it['Currency'] = trim($matches[2]);
        }

        return [$it];
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $string, $matches)) { // 20/01/17
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
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

    private function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
