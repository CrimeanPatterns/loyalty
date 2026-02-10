<?php

namespace AwardWallet\Engine\airberlin\Email;

class FlightHtml2016 extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "airberlin/it-4882916.eml, airberlin/it-4934760.eml, it-4934760.eml";

    protected $result = [];

    protected $recordLocators = [];

    private static $detects = [
        'en' => [
            'We recommend that you check, prior to your scheduled departure',
        ],
        'de' => [
            'Bitte vergewissern Sie sich vor Ihrem geplanten Abflug, ob es eventuell',
        ],
    ];

    private $lang = '';

    private $dict = [
        'en' => [
            'flight'     => 'Flight number:',
            'class'      => 'Class:',
            'from'       => 'from:',
            'to'         => 'to:',
            'departure'  => 'departure:',
            'arrival'    => 'arrival:',
            'hour'       => 'hours',
            'ref'        => "Booking ref\. no\.:",
            'passengers' => 'Passengers:',
        ],
        'de' => [
            'flight'     => 'Flugnummer:',
            'class'      => 'Klasse:',
            'from'       => 'von:',
            'to'         => 'nach:',
            'departure'  => 'Abflug:',
            'arrival'    => 'Ankunft:',
            'hour'       => 'Uhr',
            'ref'        => 'Vorgangs Nr.:',
            'passengers' => 'Passagiere:',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $this->result['Kind'] = 'T';
        $this->detectBody($parser);
        $this->parseSegments();

        return [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => [$this->result],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'noreply@airberlin.com') !== false
            && preg_match('/.+?\s+->\s+.+?/', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airberlin.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detects);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detects);
    }

    protected function parseSegments()
    {
        //$this->logger->info("//*[contains(text(), '{$this->t('flight')}')]/ancestor::ul[1]");
        foreach ($this->http->XPath->query("//*[contains(text(), '{$this->t('flight')}')]/ancestor::ul[1]") as $segment) {
            $this->result['TripSegments'][] = $this->parseSegment($segment->nodeValue);

            foreach ($this->http->XPath->query("//*[contains(text(), '{$this->t('passengers')}')]/ul", $segment) as $passenger) {
                $this->result['Passengers'] = $this->innerArray($passenger);
            }
        }
        $this->recordLocators = array_unique($this->recordLocators);

        if (count($this->recordLocators) > 1) {
            $this->logger->info('RecordLocator > 1');

            return;
        }
        $this->result['RecordLocator'] = current($this->recordLocators);

        if (isset($this->result['Passengers'])) {
            $this->result['Passengers'] = array_unique($this->result['Passengers']);
        }
    }

    protected function parseSegment($text)
    {
        $segment = [];

        if (preg_match("/{$this->t('flight')}\s*([A-Z\d]{2})?\s*(\d+)/", $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        if (preg_match("/{$this->t('class')}\s*([A-Z])/", $text, $matches)) {
            $segment['BookingClass'] = $matches[1];
        }

        if (preg_match("/{$this->t('from')}\s*(.+?)\s+{$this->t('to')}\s*(.+?)\s+{$this->t('departure')}/u", $text, $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['ArrName'] = $matches[2];
        }

        if (preg_match("/{$this->t('departure')}(.+?)\s+{$this->t('hour')}\s+{$this->t('arrival')}(.+?){$this->t('hour')}/u", $text, $matches)) {
            $segment += $this->increaseDate($this->date, $matches[1], $matches[2]);
        }

        if (preg_match("/{$this->t('ref')}\s*([A-Z\d]{5,6})\s*{$this->t('passengers')}/", $text, $matches)) {
            $this->recordLocators[] = $matches[1];
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

            return $segment;
        }
    }

    protected function innerArray(\DOMElement $element)
    {
        $array = [];

        foreach ($element->childNodes as $value) {
            $value->nodeValue = trim($value->nodeValue);

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return $array;
    }

    private function t($s)
    {
        if (empty($this->dict[$this->lang]) || empty($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach (self::$detects as $lang => $detect) {
            foreach ($detect as $dt) {
                if (mb_stripos($body, $dt, null, 'UTF8') !== false && stripos($body, 'airberlin') !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function increaseDate($dateLetter, $depTime, $arrTime)
    {
        $depDate = strtotime($this->dateStringToEnglish($depTime), $dateLetter);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 years', $depDate);
        }
        $arrDate = strtotime($this->dateStringToEnglish($arrTime), $depDate);

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }
}
