<?php

namespace AwardWallet\Engine\airfrance\Email;

class ConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-7599608.eml, airfrance/it-7599861.eml";

    public $reFrom = [
        'gpnet@xmedia.airfrance.fr',
    ];

    public $reBody = [
        "Vous recevrez un mémo voyage par e-mail à l'adresse suivante",
    ];
    public $lang = 'fr';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        $itineraries = $this->parseEmail($body);

        return [
            'emailType'  => 'Confirmation reservation',
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $fm) {
            if (stripos($from, $fm) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reFrom as $fm) {
            if (stripos($headers['from'], $fm) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $reBody) {
            if (stripos($body, $reBody) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function parseEmail($plainText)
    {
        $posVol = strpos($plainText, 'Information vol');
        $posPassager = strpos($plainText, 'Information passager');
        $posTarif = strpos($plainText, 'Information tarif');

        $volInfo = substr($plainText, $posVol, $posPassager - $posVol);
        $passagerInfo = substr($plainText, $posPassager, $posTarif - $posPassager);
        $tarifInfo = substr($plainText, $posTarif);
        $it = ['Kind' => 'T'];

        if (preg_match("#Votre référence de dossier\s*:\s*([A-Z\d]{5,6})#", $plainText, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        // volInfo
        if (preg_match("#Vol aller\D+(\d+)\s+(\D+)\s+(\d{4})#", $volInfo, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            } else {
                $date = $m[1] . ' ' . $m[2] . ' ' . $m[3];
            }
        }
        $it['TripSegments'] = [];

        if (preg_match_all("#([A-Z\d]{2})(\d{1,5})-(\d{2}:\d{2})(?:\s*\+(\d))?\s+([^(]+)\(([A-Z]{3})(?:\s+([^(]+))?\) - (.+)\s+Cabine\s+([^-]+)-(\d{2}:\d{2})(?:\s*\+(\d))?\s+([^(]+)\(([A-Z]{3})(?:\s+([^(]+))?\) - (.+)\s+#", $volInfo, $m)) {
            foreach ($m[0] as $key => $value) {
                $seg = [];
                //AirlineName
                $seg['AirlineName'] = $m[1][$key];
                //FlightNumber
                $seg['FlightNumber'] = $m[2][$key];
                //DepDate
                $seg['DepDate'] = strtotime($date . ' ' . $m[3][$key]);

                if (!empty($m[4][$key])) {
                    $seg['DepDate'] = strtotime("+" . $m[4][$key] . "day", $seg['DepDate']);
                }
                //DepName
                $seg['DepName'] = trim($m[8][$key] . ', ' . $m[5][$key]);
                //DepCode
                $seg['DepCode'] = $m[6][$key];
                //DepartureTerminal
                if (!empty($m[7][$key])) {
                    $seg['DepartureTerminal'] = $m[7][$key];
                }
                //ArrDate
                $seg['ArrDate'] = strtotime($date . ' ' . $m[10][$key]);

                if (!empty($m[11][$key])) {
                    $seg['ArrDate'] = strtotime("+" . $m[11][$key] . "day", $seg['ArrDate']);
                }
                //ArrName
                $seg['ArrName'] = trim($m[15][$key] . ', ' . $m[12][$key]);
                //ArrCode
                $seg['ArrCode'] = $m[13][$key];
                //ArrivalTerminal
                if (!empty($m[14][$key])) {
                    $seg['ArrivalTerminal'] = $m[14][$key];
                }
                //Cabin
                $seg['Cabin'] = $m[9][$key];
                $it['TripSegments'][] = $seg;
            }
        }

        // passagerInfo
        if (preg_match_all("#^\s+\d\S{1,5}\sPassager.*\s+(.+)#m", $passagerInfo, $m)) {
            $it['Passengers'] = $m[1];
        }

        // $tarifInfo
        if (preg_match("#^\s+Montant hors taxes\s*:\s*([\d.]+)\s*Euros#m", $tarifInfo, $m)) {
            $it['BaseFare'] = (float) $m[1];
            $it['Currency'] = 'EUR';
        }

        if (preg_match("#^\s+Taxes\s*:\s*([\d.]+)\s*Euros#m", $tarifInfo, $m)) {
            $it['Tax'] = (float) $m[1];
        }

        if (preg_match("#^\s+Montant total TTC\s*:\s*([\d.]+)\s*Euros#m", $tarifInfo, $m)) {
            $it['TotalCharge'] = (float) $m[1];
        }

        return [$it];
    }
}
