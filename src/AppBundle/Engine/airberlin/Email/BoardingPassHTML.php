<?php

namespace AwardWallet\Engine\airberlin\Email;

class BoardingPassHTML extends \TAccountChecker
{
    public $mailFiles = "airberlin/it-1902984.eml, airberlin/it-7071871.eml, airberlin/it-7132525.eml, airberlin/it-7271282.eml, airberlin/it-7714592.eml, airberlin/it-7822433.eml, airberlin/it-7915965.eml";

    public $reFrom = "airberlin.com";
    public $reBody = [
        'en' => ['airberlin.com', 'In order to save your boarding pass'],
        'de' => ['airberlin.com', 'Um die Bordkarte auf'],
        'it' => ['airberlin.com', 'Per salvare la tua carta d\'imbarco'],
        'es' => ['airberlin.com', 'Para guardar la tarjeta de embarque'],
        'sv' => ['airberlin.com', 'För att spara boardingkortet'],
        'nl' => ['airberlin.com', 'Om de instapkaart op uw'],
        'fr' => ['airberlin.com', 'Pour enregistrer votre carte d\'embarquement'],
    ];
    public $reSubject = [
        'Your Boarding Pass',
        'Ihre Bordkarte',
        'La/le vostra/e carta/e d’imbarco',
        'Su tarjeta de embarque',
        'Ditt/dina boardingkort',
        'Uw instapkaart(en)',
        'Votre/Vos carte(s) d\'embarquement',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
        'it' => [
            "Booking Reference:"=> "N. di prenotazione check-in::",
            "Seat:"             => "Posto:",
        ],
        'es' => [
            "Booking Reference:"=> "N° / código de reserva:",
            "Seat:"             => "Asiento:",
        ],
        'de' => [
            "Booking Reference:"=> "Buchungsnummer:",
            "Departure Time:"   => "Abflugzeit:",
            "Booking Class:"    => "Buchungsklasse:",
            "Ticket Number:"    => "Ticketnummer:",
            "Flight Number:"    => "Flugnummer:",
            "Seat:"             => "Sitzplatz:",
        ],
        'sv' => [
            "Booking Reference:"=> "Bokningsreferens:",
            "Seat:"             => "Sittplats:",
        ],
        'nl' => [
            "Booking Reference:"=> "Check-in-boekingsnummer:",
            "Seat:"             => "Zitplaats:",
        ],
        'fr' => [
            "Booking Reference:"=> "N° de réservation enregistrement ::",
            "Seat:"             => "Siège:",
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $filename = '';
        $search = $parser->searchAttachmentByName('boardingpass.gif');

        if (isset($search[0])) {
            $search = $search[0];
            $name = $parser->getAttachmentHeader($search, 'Content-Type');

            if (!$name || !preg_match('/name="?(?<name>boardingpass.gif)"?/', $name, $m)) {
                $this->http->Log('invalid filename');
            } else {
                $filename = $m['name'];
            }
        }
        $arr = $this->parseEmail($filename);

        return [
            'parsedData' => ['Itineraries' => $arr['its'], 'BoardingPass' => $arr['bps']],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'airberlin.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail($filename)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->getField($this->t('Booking Reference:'), "#[A-Z\d]+#");
        $it['Passengers'][] = $this->getField($this->t('Name:'));
        $it['AccountNumbers'][] = $this->getField($this->t('FQTV:'));
        $it['TicketNumbers'][] = $this->getField($this->t('Ticket Number:'));

        $seg = [];
        $seg['FlightNumber'] = $this->getField($this->t('Flight Number:'), "#^\s*[A-Z\d]{2}\s*(\d+)#");
        $seg['AirlineName'] = $this->getField($this->t('Flight Number:'), "#^\s*([A-Z\d]{2})\s*\d+#");
        $seg['Seats'] = $this->getField($this->t('Seat:'));
        $seg['Cabin'] = $this->getField($this->t('Cabin Class:'));
        $seg['BookingClass'] = $this->getField($this->t('Booking Class:'));
        $seg['DepCode'] = $this->getField($this->t('Route:'), "#^([A-Z]{3})#");
        $seg['ArrCode'] = $this->getField($this->t('Route:'), "#\-([A-Z]{3})$#");
        $seg['DepDate'] = strtotime($this->dateStringToEnglish($this->getField($this->t('Departure Time:'))));
        $seg['ArrDate'] = MISSING_DATE;
        $it['TripSegments'][] = $seg;

        $result = [];
        $result["FlightNumber"] = $seg["FlightNumber"];
        $result["DepCode"] = $seg['DepCode'];
        $result["DepDate"] = $seg["DepDate"];
        $result["RecordLocator"] = $it['RecordLocator'];

        $result['AttachmentFileName'] = $filename;
        $result['BoardingPassURL'] = $this->http->FindSingleNode("//a[contains(@href,'m.airberlin.com')]/@href");

        return ['its'=>[$it], 'bps'=>[$result]];
    }

    private function getField($str, $re = "#.+#")
    {
        return $this->http->FindSingleNode("//td[normalize-space(.)='{$str}']/following-sibling::td[1]", null, true, $re);
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),\"" . $reBody[0] . "\")]")->length > 0
                 && $this->http->XPath->query("//*[contains(normalize-space(.),\"" . $reBody[1] . "\")]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
