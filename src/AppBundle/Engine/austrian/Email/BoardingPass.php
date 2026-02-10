<?php

namespace AwardWallet\Engine\austrian\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "austrian/it-11154061.eml";

    public $reFrom = "AustrianInternet@austrian.com";
    public $reBody = [
        'en' => ['Digital boarding pass', 'Departure airport'],
        'de' => ['Mobile Bordkarte', 'Abflughafen'],
    ];
    public $reSubject = [
        '#[A-Z\d]{2}\s*\d+\/\d+\s+\w{3}\s+\d+#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
        'de' => [
            'Ticket number'     => 'Ticketnummer',
            'Frequent flyer'    => 'Frequent flyer',
            'Date'              => 'Datum',
            'Flight'            => 'Flug',
            'Class'             => 'Klasse',
            'Seat number'       => 'Sitznummer',
            'Departure airport' => 'Abflughafen',
            'Arrival airport'   => 'Ankunftsflughafen',
            'Departure time'    => 'Abflugzeit',
            'Status'            => 'Status',
        ],
    ];
    private $bp;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();
        $fileName = '';
        $search = $parser->searchAttachmentByName('barcode_.+\.png');

        if (count($search) > 0) {
            $name = $parser->getAttachmentHeader($search[0], 'Content-Type');

            if ($name && preg_match('/name="(barcode_.+\.png)"/', $name, $m) > 0) {
                $fileName = $m[1];
            }
        }

        $its = $this->parseEmail($fileName);
        $result['Itineraries'] = $its;

        if (isset($this->bp) && count($this->bp) > 0) {
            $result['BoardingPass'] = [$this->bp];
        }
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => $result,
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'austrian.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function nextField($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($this->t($field))}]/ancestor::td[1]/following-sibling::td[1]");
    }

    private function parseEmail($fileName)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'][] = $this->nextField('Name');
        $it['TicketNumbers'][] = trim($this->re("#([\d\- ]+)#", $this->nextField('Ticket number')));
        $it['AccountNumbers'][] = trim($this->re("#([A-Z\d\- ]+)#", $this->nextField('Frequent flyer')));

        $seg = [];
        $date = strtotime($this->nextField('Date'));
        $node = $this->nextField('Flight');

        if (preg_match("#((?i-)[A-Z\d]{2})\s*(\d+)\s*(?:{$this->opt($this->t('operated by'))}\s+(.+)|\([A-Z\d]{2}\s*\d{1,5}\)|$)#i", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];

            if (isset($m[3]) && !empty($m[3])) {
                $seg['Operator'] = $m[3];
            }
        }
        $seg['Cabin'] = $this->nextField('Class');
        $seg['Seats'] = $this->nextField('Seat number');
        $seg['DepCode'] = $this->nextField('Departure airport');
        $seg['ArrCode'] = $this->nextField('Arrival airport');
        $seg['DepDate'] = strtotime($this->nextField('Departure time'), $date);
        $seg['ArrDate'] = MISSING_DATE;
        $it['Status'] = $this->nextField('Status');

        $it['TripSegments'][] = $seg;

        if (!empty($fileName) && isset($seg['FlightNumber'])) {
            $this->bp = [
                'FlightNumber'       => $seg['FlightNumber'],
                'DepCode'            => $seg['DepCode'],
                'DepDate'            => $seg['DepDate'],
                'RecordLocator'      => $it['RecordLocator'],
                'Passengers'         => $it['Passengers'],
                'BoardingPassURL'    => null,
                'AttachmentFileName' => $fileName,
            ];
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
