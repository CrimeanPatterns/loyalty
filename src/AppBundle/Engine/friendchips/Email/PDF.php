<?php

namespace AwardWallet\Engine\friendchips\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-10471887.eml, friendchips/it-13035295.eml, friendchips/it-5903775.eml, friendchips/it-6088521.eml, friendchips/it-6145936.eml, friendchips/it-6890370.eml, friendchips/it-6935720.eml, friendchips/it-7007865.eml, friendchips/it-7011410.eml, friendchips/it-7359588.eml, friendchips/it-8377403.eml";

    private $reSubject = [
        'fr' => ['Confirmation TUI fly Services pour la réservation'],
        'nl' => ['Bevestiging TUI fly Services van boeking met nummer', 'Bevestiging TUIfly Services'],
        'en' => ['Confirmation TUI fly Services for booking', 'Confirmation TUIfly Services for booking'],
    ];

    private $langDetectors = [
        'fr' => ['INFORMATIONS DE VOL'],
        'nl' => ['VLUCHTINFORMATIE'],
        'en' => ['FLIGHT INFORMATION'],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    private $lang = '';

    private static $dict = [
        'fr' => [],
        'nl' => [
            'Numéro de réservation'    => 'Reserveringsnummer',
            'Date de réservation'      => 'Reserveringsdatum',
            'PASSAGERS'                => 'PASSAGIERS',
            'INFORMATIONS DE VOL'      => 'VLUCHTINFORMATIE',
            'siège'                    => 'stoel',
            'Vol'                      => 'Vlucht',
            'INFORMATIONS DE PAIEMENT' => 'BETAALINFORMATIE',
        ],
        'en' => [
            'Numéro de réservation'    => 'Reservation number',
            'Date de réservation'      => 'Date of reservation',
            'PASSAGERS'                => 'PASSENGERS',
            'INFORMATIONS DE VOL'      => 'FLIGHT INFORMATION',
            'siège'                    => 'seat',
            'Vol'                      => 'Flight',
            'INFORMATIONS DE PAIEMENT' => 'AYMENT INFORMATION',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdfs)), \PDF::MODE_COMPLEX);

        $NBSP = chr(194) . chr(160);
        $htmlPdf = str_replace($NBSP, ' ', html_entity_decode($htmlPdf));

        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($htmlPdf);

        if ($this->assignLang($htmlPdf) === false) {
            return false;
        }

        return [
            'emailType'  => 'ConfirmationPdf' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tuifly\.com/i', $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'reply@www.tuifly.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
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

    protected function assignLang($text)
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

    protected function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $textPdf = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        if (stripos($textPdf, 'TUIfly Services') === false && stripos($textPdf, 'TUI fly Services') === false && strpos($textPdf, 'TUI Belgium') === false && strpos($textPdf, 'TUI Nederland') === false) {
            return false;
        }

        return $this->assignLang($textPdf);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(normalize-space(),'" . $this->t('Numéro de réservation') . "')]", null, true, "#[^:]+:\s*(\w+)#");

        $it['ReservationDate'] = strtotime($this->pdf->FindSingleNode("//p[contains(normalize-space(),'" . $this->t('Date de réservation') . "')]", null, true, "#:\s*(\d{2}\.\d{2}\.\d{4})#"));
        $psngRoot = "//p[contains(normalize-space(),'" . $this->t('PASSAGERS') . "')]";
        $i = 1;
        $s = $this->pdf->FindSingleNode($psngRoot . "/following::p[" . $i . "]");
        $seats = [];
        $k = 0;
        $seatstitle = false; // false - No info on which flight belong this places

        while ($s != $this->t('INFORMATIONS DE VOL') && $i < 20) {
            if (preg_match("#" . $this->t('Vol') . " ([a-z]{2})\s*(\d{2,5})#i", $s, $m)) {
                $seats[0][] = $m[1] . $m[2];
                $seatstitle = true;
            }

            if (preg_match_all("#\d+\.\s*([a-z\-\s]+)#i", $s, $m)) {
                foreach ($m[1] as $key => $value) {
                    $it['Passengers'][] = $value;
                    $k++;
                }
            }

            if (preg_match("#" . $this->t('siège') . "[^:]+:\s*([\w]+)#i", $s, $m)) {
                $seats[$k][] = $m[1];
            }
            $i++;
            $s = $this->pdf->FindSingleNode($psngRoot . "/following::p[" . $i . "]");
        }

        $seat = [];

        if (!isset($seats[0])) {
            $count = 0;

            foreach ($seats as $key => $value) {
                if (count($value) > $count) {
                    $count = count($value);
                }
            }
            $a = [];

            for ($i = 0; $i < $count; $i++) {
                $a[0][$i] = $i;
            }
            $seats = array_merge($a, $seats);
        }

        if (!empty($seats) and count($seats[0])) {
            foreach ($seats[0] as $key => $value) {
                $ar = array_column($seats, $key);
                $seat[array_shift($ar)] = $ar;
            }
        }
        unset($seats);

        $xpath = "//p/b[text()='" . $this->t('Vol') . "'] | //p[contains(text(),'--------')]";
        $flights = $this->pdf->XPath->query($xpath);
        $segment = [];

        foreach ($flights as $sn => $root) {
            $seg = [];
            $i = 1;
            $str = '';
            $s = $this->pdf->FindSingleNode("./following::p[" . $i . "]", $root);

            while (strpos($s, "TUI fly SERVICES") === false && strpos($s, $this->t('INFORMATIONS DE PAIEMENT')) === false && strpos($s, "-----") === false && strpos($s, $this->t('Vol')) === false && $i < 15) {
                $str = $str . "\n" . $s;
                $i++;
                $s = $this->pdf->FindSingleNode("./following::p[" . $i . "]", $root);
            }

            if (preg_match('#([\w][^\(]+)\s+\(([A-Z]{3})\)\s+\D\s+([^(]+)\s+\(([A-Z]{3})\)\s*(\d{2}\.\d{2}\.\d{4})#ui', $str, $m)) {
                $seg['DepName'] = trim(str_replace("\n", ' ', $m[1]));
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = trim(str_replace("\n", ' ', $m[3]));
                $seg['ArrCode'] = $m[4];
                $date = $m[5];
            }

            if (preg_match('#(\d{2}\s*:\s*\d{2})(\D*(\d{2}\s*:\s*\d{2}))*#', $str, $m)) {
                $m[1] = str_replace(' ', '', $m[1]);
                $seg['DepDate'] = strtotime($date . ' ' . $m[1]);

                if (isset($m[3])) {
                    $m[3] = str_replace(' ', '', $m[3]);
                    $seg['ArrDate'] = strtotime($date . ' ' . $m[3]);
                } else {
                    $seg['ArrDate'] = MISSING_DATE;
                }
            }

            if (preg_match('#([A-Z]{2})\s*(\d{1,5})#', $str, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $i++;

                if ($seatstitle) {
                    if (isset($seat[$m[1] . $m[2]])) {
                        $seg['Seats'] = $seat[$m[1] . $m[2]];
                    }
                } elseif (isset($seatFlifgt[$m[1] . $m[2]])) {
                    $seg['Seats'] = $seatFlifgt[$m[1] . $m[2]];
                } else {
                    $seg['Seats'] = array_shift($seat);
                    $seatFlifgt[$m[1] . $m[2]] = $seg['Seats']; // if the same flight number
                }
            }

            if (!empty($seg)) {
                $left = $this->pdf->FindSingleNode("./ancestor-or-self::p[1]/@style", $root, true, "#left:(\d+)#");
                $top = $this->pdf->FindSingleNode("./ancestor-or-self::p[1]/@style", $root, true, "#top:(\d+)#");
                $segment[$left . str_pad($top, 5, '0', STR_PAD_LEFT)] = $seg;
            }
        }
        ksort($segment);
        $it['TripSegments'] = array_values($segment);

        return [$it];
    }

    private function t($s)
    {
        return isset(self::$dict[$this->lang]) && isset(self::$dict[$this->lang][$s]) ? self::$dict[$this->lang][$s] : $s;
    }
}
