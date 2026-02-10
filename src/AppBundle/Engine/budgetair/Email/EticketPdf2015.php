<?php

namespace AwardWallet\Engine\budgetair\Email;

class EticketPdf2015 extends \TAccountChecker
{
    public $mailFiles = "budgetair/it-10715601.eml, budgetair/it-2485137.eml, budgetair/it-2586750.eml, budgetair/it-2762947.eml, budgetair/it-2762952.eml, budgetair/it-4609238.eml, budgetair/it-4645721.eml, budgetair/it-4688241.eml, budgetair/it-4688254.eml, budgetair/it-4729514.eml, budgetair/it-5128573.eml, budgetair/it-5582000.eml, budgetair/it-5754705.eml, budgetair/it-5765693.eml, budgetair/it-5813488.eml, budgetair/it-6434129.eml";
    public $lang = 'indefined';
    public $reBody = [
        'en' => ['We would advise you to print this e-ticket', 'Reservation number'],
        'fr' => ['Nous vous recommandons d\'imprimer ce courriel ainsi', 'Numéro de référence'],
        'nl' => ['Wij adviseren je de e-mail uit', 'Referentienummer'],
        'es' => ['Le recomendamos que imprima este correo electr', 'Número de referencia'],
    ];
    public static $dict = [
        'en' => [
            'RL' => '',
        ],
        'fr' => [
            'Reservation number'          => 'Numéro de référence',
            'Issued by'                   => 'Emis par',
            'Date issued'                 => 'Date d\'émission',
            'Passenger name'              => 'Nom du passager:',
            'Flight details'              => 'Données de vol',
            'local and subject to change' => 'Tous les horaires indiqués',
            'Departure'                   => 'Départ',
            'Arrival'                     => 'Arrivée',
            'booking code'                => 'réservation',
            //			'Aircraft' => '',
            'Flight number' => 'Numéro de vol',
            'Class'         => 'Classe',
            //			'Operated by' => 'Compagnie',
        ],
        'nl' => [
            'Reservation number'          => 'Referentienummer',
            'Issued by'                   => 'Uitgegeven door:',
            'Date issued'                 => 'Datum uitgifte:',
            'Passenger name'              => 'Naam passagier:',
            'Flight details'              => 'Vluchtgegevens',
            'local and subject to change' => 'Alle genoemde tijden zijn',
            'Departure'                   => 'Vertrek',
            'Arrival'                     => 'Aankomst',
            'booking code'                => 'boekingscode:',
            //			'Aircraft' => '',
            'Flight number' => 'Vluchtnummer:',
            'Class'         => 'Klasse:',
            //			'Operated' => 'Compagnie',
        ],
        'es' => [
            'Reservation number'          => 'Número de referencia',
            'Issued by'                   => 'Emitido por:',
            'Date issued'                 => 'Fecha de emisión:',
            'Passenger name'              => 'Nombre del',
            'Flight details'              => 'Datos del vuelo',
            'local and subject to change' => 'Todos los horarios son locales',
            'Departure'                   => 'Salida',
            'Arrival'                     => 'Llegada',
            'booking code'                => 'código de reserva:',
            //			'Aircraft' => '',
            'Flight number' => 'Número del vuelo:',
            'Class'         => 'Clase:',
            'Operated by'   => 'Con:',
        ],
    ];

    protected $result = [];
    protected $recordLocator = [];
    protected $recordLocatorNotUnique = [];

    private $headers = [
        'budgetair' => [
            'from' => ['noreply@budgetair'],
            'subj' => [
                'E-Ticket for',
                'Billet électronique pour',
                'E-Ticket voor ',
                'Billete electrónico para ',
            ],
        ],
        'klm' => [
            'from' => ['@airfrance-klm.com'],
            'subj' => [
                "Flying Blue: Acknowledgement of receipt of your request",
            ],
        ],
    ];
    private $code;
    public static function getEmailProviders()
    {
        return ['budgetair', 'klm'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*?.pdf');
        //$pdf = $parser->searchAttachmentByName('E-?ticket.*?.pdf');

        if (empty($pdf)) {
            $this->http->Log('No PDF file!');

            return false;
        }

        $NBSP = chr(194) . chr(160);
        $pdfText = str_replace($NBSP, ' ', \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf))));

        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($pdfText, $phrase) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $text = $this->findCutSection($pdfText, null, $this->t('local and subject to change'));

        $this->result['Kind'] = 'T';

        //if (preg_match("#\s+(.+?)Reservation number(.+?)(?:\s+Date of birth:\s+\d+\/\d+\/\d+\s*|$)#is", $text, $m))
        //	$this->result['Passengers'][] = trim($m[1]) . trim($m[2]);
        //else
        $this->result['Passengers'][] = trim(str_replace(':', '', $this->findCutSection($pdfText, $this->t('Passenger name'), $this->t('Reservation number'))));

        if (preg_match("#{$this->t('Date issued')}:?\s*(\d+\/\d+\/\d+)#", $text, $m)) {
            $this->result['ReservationDate'] = strtotime($this->normalizeDate(trim($m[1])));
        }

        $this->parseSegments($this->findCutSection($text, $this->t('Flight details'), null));
        $this->recordLocatorNotUnique = $this->recordLocator;
        $this->recordLocator = array_unique($this->recordLocator);

        if (count($this->recordLocator) === 0) {
            $node = trim($this->findCutSection($pdfText, $this->t('Reservation number'), $this->t('Issued by')));

            if (preg_match("#Budgetair.+?:\s*([A-Z\d]+)\s#is", $node, $m)) {
                $this->result['RecordLocator'] = $m[1];
            }
            $this->result = [$this->result];
        } elseif (count($this->recordLocator) > 1) {
            $this->http->Log('The need for Itinerary split detected. ' . count($this->recordLocator) . ' unique RecordLocators', LOG_LEVEL_NORMAL);
            $newIts = [];

            foreach ($this->recordLocator as $i => $rl) {
                $tmpIt = $this->result;
                $tmpIt['RecordLocator'] = $rl;

                foreach ($tmpIt['TripSegments'] as $j => $ts) {
                    if ($rl !== $this->recordLocatorNotUnique[$j]) {
                        unset($tmpIt['TripSegments'][$j]);
                    }
                }
                $newIts[] = $tmpIt;
            }
            $this->result = $newIts;
        } else {
            $this->result['RecordLocator'] = current($this->recordLocator);
            $this->result = [$this->result];
        }

        $result = [
            'parsedData' => ['Itineraries' => $this->result],
            'emailType'  => 'EticketPdf2015_' . $this->lang,
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

    public function normalizeDate($str)
    {
        $str = str_replace('/', '-', $str);

        return $str;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'BudgetAir.') === false) {
                continue;
            }

            foreach ($this->reBody as $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($textPdf, $phrase) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseSegments($pdfText)
    {
        foreach (preg_split("/{$this->t('Flight details')}\s+/", $pdfText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            if (stripos($value, $this->t('Arrival')) !== false && mb_strlen($value) > 300) {
                $this->result['TripSegments'][] = $this->parseSegment($value);
            }
        }
    }

    protected function parseSegment($text)
    {
        $segment = [];

        if (preg_match("/(?:{$this->t('booking code')}):?\s+([A-Z\d]{5,6})/", $text, $matches)) {
            $this->recordLocator[] = $matches[1];
        }

        if (preg_match("/{$this->t('Aircraft')}\s+(.*?)\s{2,}/", $text, $matches)) {
            $segment['Aircraft'] = $matches[1];
        }

        if (preg_match("/{$this->t('Flight number')}:?.*?([A-Z\d+]{2})\s*(\d{2,4})/", $text, $matches)) {
            $segment['FlightNumber'] = $matches[2];
            $segment['AirlineName'] = $matches[1];
        }

        if (preg_match("#(\d+\/\d+\/\d{4}).+?\(([A-Z]{3})\).+?"
                . "(\d+\/\d+\/\d{4}).+?\(([A-Z]{3})\)#s", $text, $matches)) {
            $segment['DepCode'] = $matches[2];

            if (preg_match("/{$this->t('Departure')}:?\s+(\d+:\d+)/", $text, $matches2)) {
                $segment['DepDate'] = strtotime($this->normalizeDate($matches[1] . ' ' . $matches2[1]));
            }

            $segment['ArrCode'] = $matches[4];

            if (preg_match("/{$this->t('Arrival')}:?\s+(\d+:\d+)/", $text, $matches2)) {
                $segment['ArrDate'] = strtotime($this->normalizeDate($matches[3] . ' ' . $matches2[1]));
            }
        }

        if (preg_match('#Class:\s+(?<BookingClass>\S)#s', $text, $matches)) {
            $segment['BookingClass'] = $matches['BookingClass'];
        }

        if (preg_match('/Duration(.*)/', $text, $matches)) {
            $segment['Duration'] = trim($matches[1]);
        }

        if (preg_match("/{$this->t('Class')}:?\s+([A-Z])/", $text, $matches)) {
            $segment['BookingClass'] = $matches[1];
        }
        //if (preg_match("/{$this->t('Operated by')}\s+(.+)/i", $text, $matches)) {
        //	$segment['Operator'] = trim($matches[1]);
        //}

        return $segment;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'budgetair') {
                return null;
            } else {
                return $this->code;
            }
        }

        return null;
    }
}
