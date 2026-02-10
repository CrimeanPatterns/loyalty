<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use PlancakeEmailParser;

class MobileBoardingPass extends \TAccountChecker
{
    public $mailFiles = "asia/it-10480614.eml, asia/it-1647644.eml, asia/it-1666481.eml, asia/it-1671452.eml";

    private $detects = [
        'Cathay Pacific Airways',
        'Veuillez accèder à votre carte d’embarquement mobile en cliquant sur le lien suivant',
        'Please access your mobile boarding pass using the following link',
    ];

    private $langDetects = [
        'en' => ['Thank you for checking in online. Your check-in details are as follows'],
        'fr' => ['Merci de vous enregistrer en ligne. Les détails de votre enregistrement sont les suivants'],
    ];

    private static $dict = [
        'en' => [],
        'fr' => [
            'Flight No/date' => 'N° vol/date',
            'Seat No:'       => 'N° Siège:',
            'From:'          => 'Départ:',
            'Departure:'     => 'Heure de Départ:',
            'To:'            => 'Vers:',
            'Arrival:'       => 'heure d',
            'Class:'         => 'Classe:',
            'Name:'          => 'Nom:',
        ],
    ];

    private $lang = '';

    private $from = "#onlinecheckin@cathaypacific\.com#i";

    private $provider = "cathaypacific";

    private $subject = "#Cathay\s+Pacific\s+Mobile\s+Boarding\s+Pass#i";

    private $date;

    public function dateStringToEnglish($date)
    {
        if ('en' !== $this->lang && preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $parser->getHTMLBody() ?? $parser->getPlainBody();

        foreach ($this->langDetects as $lang => $detect) {
            foreach ($detect as $dt) {
                if (stripos($body, $dt) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ?? $parser->getPlainBody();

        if (false === stripos($body, $this->provider)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) && isset($headers['subject'])
               && preg_match($this->subject, $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $it['Passengers'][] = $this->http->FindSingleNode("//tr[starts-with(normalize-space(.), '{$this->t('Name:')}')][1]/td[2]");

        $xpath = "//tr[starts-with(normalize-space(.), '{$this->t('Flight No/date')}') and not(.//tr)]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $dateStr = '';

            if (preg_match("#([A-Z\s]{2})\s*(\d+)(?:.+)?\/(.*)#iu", $this->http->FindSingleNode('descendant::td[2]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $dateStr = $m[3];
            }

            $depArr = [
                'Dep' => $this->getNode($root, $this->t('From:')),
                'Arr' => $this->getNode($root, $this->t('To:')),
            ];

            foreach ($depArr as $key => $value) {
                if (preg_match('/(.*?)\s*\(([A-Z]{3})\)/', $value, $m)) {
                    $seg[$key . 'Name'] = $m[1];
                    $seg[$key . 'Code'] = $m[2];
                } else {
                    $seg[$key . 'Name'] = $value;
                }
            }

            if (empty($seg['DepCode']) && empty($seg['ArrCode']) && !empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $depTime = $this->getNode($root, $this->t('Departure:'));
            $seg['DepDate'] = $this->normalizeDate($dateStr . ' ' . $depTime);

            $arrTime = $this->getNode($root, $this->t('Arrival:'));

            if (preg_match("#^\s*(\d+:\d+)\!(\d+)\s*$#", $arrTime, $m)) {
                $seg['ArrDate'] = strtotime("+" . $m[2] . " day", $this->normalizeDate($dateStr . ' ' . $m[1]));
            } elseif (preg_match("#^\s*(\d+:\d+)\-(\d+)\s*$#", $arrTime, $m)) {
                $seg['ArrDate'] = strtotime("-" . $m[2] . " day", $this->normalizeDate($dateStr . ' ' . $m[1]));
            } else {
                $seg['ArrDate'] = $this->normalizeDate($dateStr . ' ' . $arrTime);
            }

            $seg['Cabin'] = $this->http->FindSingleNode("preceding-sibling::tr[contains(normalize-space(.), '{$this->t('Class:')}')][1]/td[2]", $root);

            $seg['Seats'][] = $this->http->FindSingleNode("preceding-sibling::tr[starts-with(normalize-space(.), '{$this->t('Seat No:')}')][1]/td[2]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        //		$this->logger->info($date);
        $year = date("Y", $this->date);
        $in = [
            '#^(\d{1,2})([A-Z]{3})(\d{2,4})\s+(\d{1,2}:\d{2})$#i',
            "#^(\d+)\s+(\w+),\s+([\w\-]+)\s+(\d+:\d+)$#", // 30 Jul, Thu 01:40
        ];
        $out = [
            '$1 $2 $3, $4',
            "$1 $2 $year, $4",
        ];
        $outWeek = [
            '',
            '$3',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function getNode(\DOMNode $root, string $str, string $re = null)
    {
        return $this->http->FindSingleNode("following-sibling::tr[starts-with(normalize-space(.), '{$str}')][1]/td[2]", $root, true, $re);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
