<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Engine\MonthTranslate;

class PlainText extends \TAccountChecker
{
    public $mailFiles = "ana/it-10375932.eml, ana/it-10470780.eml, ana/it-57058028.eml, ana/it-7909967.eml";

    public static $dictionary = [
        'en' => [
            "The flight of"  => "The flight of",
            "findCutSection" => [
                ['The new flight schedule is as follows', 'The flight departed'],
                ['The schedule after change is as follows', 'Please confirm the latest information on your flight here'],
                ['We are sending this message to', 'Please do not reply to this email address'],
            ],
            //			"DEP" => "",
            //			"ARR" => "",
        ],
        'de' => [
            "The flight of"  => "Der Flug von",
            "findCutSection" => [
                ['Wir senden diese Nachricht ', 'Bitte antworten Sie nicht auf diese'],
            ],
            "DEP" => "Abflug:",
            "ARR" => "Ankunft:",
        ],
    ];

    private $lang = 'en';

    private $detects = [
        'en' => 'Thank you so much for flying with ANA',
        'de' => 'Vielen Dank, dass Sie mit ANA fliegen',
    ];

    private $subjects = [
        '[From ANA] Information regarding departure time changes',
        '[Von ANA] Informationen zum Flug (Ankunft)',
    ];

    private $from = '/[@.]ana[.cojp]{3,6}/';

    private $year = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        foreach ($this->detects as $lang => $detect) {
            if (stripos($text, $detect) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->year = date('Y', strtotime($parser->getDate()));
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($text)],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!preg_match($this->from, $headers['from'])) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (stripos($body, 'ANA/ALL NIPPON AIRWAYS') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        if (preg_match('/' . $this->t('The flight of') . '[ ]*(.+)/', $text, $m)) {
            $it['Passengers'][] = trim($m[1]);
        }

        $segment = null;

        if (is_array($this->t("findCutSection"))) {
            foreach ($this->t("findCutSection") as $values) {
                if (empty($values[0]) || empty($values[1])) {
                    $this->logger->info("wrong translate 'findCutSection'");

                    return null;
                }
                $segment = $this->findCutSection($text, $values[0], $values[1]);

                if (!empty($segment)) {
                    break;
                }
            }
        }

        if (empty($segment)) {
            $this->logger->info("empty \$segment");

            return null;
        }

        if (preg_match('/(?<al>[A-Z\d]{2})\s*(?<fn>\d+)\s+(?<from>.+)\s*-\s*(?<to>.+)\s+' . $this->t('DEP') . '\s+(?<ddate>.+?)(\s*\(\s*Terminal\s*(?<term>.+)\s*\))?\s+' . $this->t('ARR') . '\s+(?<adate>.+)/i', $segment, $m)) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['AirlineName'] = $m['al'];
            $seg['FlightNumber'] = $m['fn'];
            $seg['DepName'] = trim($m['from']);
            $seg['ArrName'] = trim($m['to']);
            $seg['DepDate'] = $this->normalizeDate($m['ddate']);
            $seg['ArrDate'] = $this->normalizeDate($m['adate']);

            if (!empty($m['term'])) {
                $seg['DepartureTerminal'] = $m['term'];
            }

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/^\s*[^\d\s]+,\s*(\w+)\.\s+(\d+).\s+(\d+:\d+)\b.*/u', //FRI., DEC. 22, 17:15.
            '/^\s*(\d+:\d+), [^\d\s]+,\s*(\d+)\s*([^\d\s]+)\b.*/u', //20:45, Mo, 13 Nov
        ];
        $out = [
            "$2 $1 {$this->year}, $3",
            "$2 $3 {$this->year}, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
    private function findCutSection($input, $searchStart, $searchFinish = null)
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

        return $inputResult;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
