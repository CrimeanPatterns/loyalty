<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class MobileBoardingPassHtml2016 extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-11053985.eml, aeroflot/it-4412787.eml, aeroflot/it-4528636.eml";

    public $reFrom = ['@aeroflot.ru', '@aeromexico.com', '@gulfair.com'];
    public $reSubject = [
        "en"=> "Aeroflot Mobile Boarding Pass",
        "es"=> "su pase de abordar para el",
    ];
    public static $reBody = [
        "aeromexico"=> "Aeromexico",
        "aeroflot"  => "Aeroflot",
        "gulfair"   => "gulfair.com",
    ];
    public $reBody2 = [
        "en"=> "Boarding Time:",
        "es"=> "Hora de Abordaje:",
    ];

    public static $dictionary = [
        "en" => [
            "Confirmation Number:"=> "(?:Confirmation Number:|Booking reference:)",
            "Arrival Time:?"      => "(?:Arrival Time:?|Arrival:?)",
        ],
        "es" => [
            "Confirmation Number:"        => "Número de confirmación:",
            "Passenger Name:"             => "Nombre del pasajero:",
            "Airline Code and Flight \#:?"=> "Aerolínea y núm. de vuelo",
            "From:"                       => "Desde:",
            "To:"                         => "Hacia:",
            "Date:"                       => "Fecha:",
            "Departure Time:?"            => "Hora de salida",
            "Arrival Time:?"              => "Hora de llegada:",
            "Class"                       => "NOTTRANSLATED",
        ],
    ];

    public $lang = "en";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (($provider = $this->getProvider($parser)) === false) {
            $this->http->log("provider not detected");

            return null;
        }

        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
        $this->date = strtotime("-20 days", $this->date);

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->dateYear = date('Y', strtotime($parser->getDate()));
        // Link https://en.wikipedia.org/wiki/Non-breaking_space
        $text = str_replace(' ', ' ', html_entity_decode(strip_tags($parser->getHTMLBody())));

        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->getParam($text, "#" . $this->t("Confirmation Number:") . "\s*([A-Z\d]{5,6})#");
        $this->result['Passengers'][] = $this->getParam($text, "#" . $this->t("Passenger Name:") . "\s*(.*)#");
        $this->result['TripSegments'][] = $this->parseSegment($text);

        return [
            'emailType'   => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'providerCode'=> $provider,
            'parsedData'  => [
                'Itineraries' => [$this->result],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        $check = false;

        foreach ($this->reFrom as $from) {
            if (strpos($headers["from"], $from) !== false) {
                $check = true;
            }
        }

        if (!$check) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->getProvider($parser) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $f) {
            if (strpos($from, $f) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$reBody);
    }

    protected function parseSegment($text)
    {
        if (preg_match("#" . $this->t("Airline Code and Flight \#:?") . "\s*([A-Z]{2})?\s*(\d+)#", $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        if (preg_match("#" . $this->t("From:") . "\s*(.*?)\s*\(([A-Z]{3})#", $text, $matches)) {
            $segment['DepCode'] = $matches[2];
            $segment['DepName'] = $matches[1];
        }

        if (preg_match("#" . $this->t("To:") . "\s*(.*?)\s*\(([A-Z]{3})#", $text, $matches)) {
            $segment['ArrCode'] = $matches[2];
            $segment['ArrName'] = $matches[1];
        }

        if (preg_match("#" . $this->t("Date:") . "\s*(\S[^\n]+)#", $text, $matches)) {
            $date = $this->normalizeDate($matches[1]);
        }

        if (isset($date)) {
            $segment['DepDate'] = strtotime($this->getParam($text, "#" . $this->t("Departure Time:?") . "\s*(\d+:\d+)#im"), $date);
            $segment['ArrDate'] = strtotime($this->getParam($text, "#" . $this->t("Arrival Time:?") . "\s*(\d+:\d+)#is"), $date);
        }

        $segment['Cabin'] = $this->getParam($text, "#" . $this->t("Class") . "\s*(\w+)#");

        return $segment;
    }

    protected function getParam($subject, $pattern = null)
    {
        if (preg_match($pattern, $subject, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^(\d+) ([^\s\d]+), (\d{4})$#", //24 February, 2016
            "#^(\d+) ([^\s\d]+)$#", //Mar 16
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 %Y%",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->info($str);
        return EmailDateHelper::parseDateRelative($str, $this->date, true);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $body = $parser->getHTMLBody();

        foreach (self::$reBody as $prov=>$re) {
            if (strpos($body, $re) !== false || strpos($subject, $re) !== false) {
                return $prov;
            }
        }

        return false;
    }
}
