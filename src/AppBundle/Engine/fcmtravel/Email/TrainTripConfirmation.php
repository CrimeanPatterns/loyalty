<?php

namespace AwardWallet\Engine\fcmtravel\Email;

class TrainTripConfirmation extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-49945721.eml, fcmtravel/it-5728302.eml, fcmtravel/it-5728314.eml";

    public static $dictionary = [
        "en" => [
            "numberConfirmation" => ["Ticket Collection Reference", "mTicket Confirmation Order"],
            "typeTicket"         => ["Corporate Traveller", "Off-Peak Single", "Anytime Single", "Off-Peak Return", "Off-Peak Day Single", "Anytime Day Single", "Anytime Return"],
            "prov"               => ["fcm.travel"],
        ],
    ];

    public $lang = "en";

    // Standard Methods
    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'donotreply@uk.fcm.travel') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@uk.fcm.travel') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//node()[{$this->contains($this->t('numberConfirmation'))}]")->length > 0
            && $this->http->XPath->query("//node()[{$this->contains($this->t('typeTicket'))}]")->length > 0
            && $this->http->XPath->query("//node()[{$this->contains($this->t('prov'))}]")->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textBody = empty($parser->getPlainBody()) ? text($parser->getHTMLBody()) : $parser->getPlainBody();
        $it = $this->ParseEmail($textBody);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TrainTripConfirmation',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function ParseSegments($text)
    {
        if (preg_match('/Date\s+of\s+travel:\s+(\d{1,2}\s+[^\d\s]{3}\s+\d{4})/i', $text, $matches)) {
            $date = $matches[1];
        }
        $segments = [];

        if (preg_match_all('/(\d+:\d+.+?\n[ ]*Service originates.+?\n[ ]*\d+:\d+)/is', $text, $segment, PREG_SET_ORDER)) {
            foreach ($segment as $value) {
                $seg = [];
                $timeDep = $this->re('/(\d+[:]\d+)/', $value[1]);
                $seg['DepName'] = trim($this->re('/\d+:\d+\s+(.+\b)\s/', $value[1]));
                $seats = trim($this->re('/Reserved[:]\s(.+)[)]/', $value[1]));

                if (!empty($seats)) {
                    $seg['Seats'] = $seats;
                }
                $timeArr = $this->re('/[.]\s+(\d+[:]\d+)/', $value[1]);
                $nameArr = trim($this->re("/{$timeArr}\s+\d+[:]\d+\s+(.+\b)\s/", $text));

                if (empty($nameArr)) {
                    $nameArr = trim($this->re("/{$timeArr}\s+(.+\b)\s/", $text));
                }
                $seg['ArrName'] = $nameArr;

                if ($date && $timeDep && $timeArr) {
                    $seg['DepDate'] = strtotime($date . ' ' . $timeDep);
                    $seg['ArrDate'] = strtotime($date . ' ' . $timeArr);
                }
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $segments[] = $seg;
            }
        }

        return $segments;
    }

    protected function ParseEmail($textBody)
    {
        $start = strpos($textBody, 'Dear ');
        $end = stripos($textBody, 'Best regards,');

        if ($start !== false && $end !== false && $start < $end) {
            $text = substr($textBody, $start, $end - $start);
        } else {
            return null; // It means the wrong format
        }

        $it = [];
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        if (preg_match('/Order\s+Date:\s+(\d{1,2}\s+[^\d\s]+\s+\d{4}(?:\s+\d{2}:\d{2}|))\s*$/im', $text, $matches)) {
            $it['ReservationDate'] = strtotime($matches[1]);
        }

        if (preg_match('/Order\s+Item\s+Cost:\s+([^\d\s]+)([.\d]+)\s*$/im', $text, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $matches[2];
        }

        if (preg_match('/Ticket\s+Collection\s+Reference:\s+([A-Z\d]+)\s*$/im', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        } elseif (preg_match('/Order\sRef[:]\s+(\d{8})/im', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        // Passengers
        if (preg_match('/No\.\s+of\s+passengers:\s+(\d+)\s*$/im', $text, $matches)) {
            $passengersCount = $matches[1];
        }

        if (preg_match_all('/\s*(\b.+)\s+\(Adult\)\s*$/im', $text, $matches)) {
            $passengers = $matches[1];
        }

        if ($passengersCount >= count($passengers)) {
            $it['Passengers'] = $passengers;
        }

        // TripSegments
        if (preg_match_all('/\s*(?:OUTBOUND|RETURN)\s*\n\s*(\b.+?\b\s*\n)\s*\n\s*\n/is', $text, $matches)) {
            $it['TripSegments'] = [];

            foreach ($matches[1] as $textFlight) {
                $it['TripSegments'] = array_merge($it['TripSegments'], $this->ParseSegments($textFlight));
            }
        }

        return $it;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
