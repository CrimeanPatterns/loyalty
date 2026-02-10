<?php

namespace AwardWallet\Engine\flynas\Email;

class ReminderHtml2016 extends \TAccountChecker
{
    public $mailFiles = "flynas/it-6144769.eml, flynas/it-8608935.eml";
    public $subj;

    public static $dict = [
        'en' => [],
    ];
    protected $lang = '';
    protected $subject = [
        'en' => ['Your flight reminder from flynas'],
    ];
    protected $body = [
        'en' => ['your trip with us.'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], 'no-reply@flynas.com') !== false && $this->detect($headers['subject'], (array) $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'flynas') !== false && $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/[@\.]flynas\./", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $this->subj = $parser->getSubject();

        if ($this->lang = $this->detect($parser->getHTMLBody(), $this->body)) {
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ReminderHtml2016' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param type $haystack
     * @param type $arrayNeedle
     *
     * @return type
     */
    protected function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }
    }

    protected function parseEmail()
    {
        $result = ['Kind' => 'T'];
        //$result['Passengers'][] = $this->http->FindSingleNode("//h1[starts-with(text(), '{$this->t('Hi')} ')]", null, false, "/{$this->t('Hi')} (.+?)!/");
        $text = join("\n", $this->http->FindNodes("//text()[normalize-space()='{$this->t('FLIGHT INFORMATION')}']/ancestor::table[1]//text()[normalize-space()]"));

        $pattern = 'Booking Reference(?:\(s\))?:\s*([A-Z\d]{5,6})\s*(.+?)\s*-\s*(.+?)\s+';
        $pattern .= 'Departs:(.+?\s*\d+:\d+(?:\s*[AP]M)?)\s+Arrives:(.+?\s*\d+:\d+(?:\s*[AP]M)?)';

        if (preg_match("/{$pattern}/i", $text, $matches)) {
            $result['RecordLocator'] = $matches[1];
            $result['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Hi') and contains(., '!')]", null, "#Hi (.+)!#");
            $i['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $i['AirlineName'] = AIRLINE_UNKNOWN;
            $i['ArrCode'] = $i['DepCode'] = TRIP_CODE_UNKNOWN;
            $i['DepName'] = $matches[2];
            $i['ArrName'] = $matches[3];

            // 01 Feb 2017, 19:30 PM - error
            $depDate = str_replace("\n", ', ', trim($matches[4]));

            if (!($i['DepDate'] = strtotime($depDate, false))) {
                $i['DepDate'] = strtotime(preg_replace('/[ap]m/i', '', $depDate), false);
            }

            $arrDate = str_replace("\n", ', ', trim($matches[5]));

            if (!($i['ArrDate'] = strtotime($arrDate, false))) {
                $i['ArrDate'] = strtotime(preg_replace('/[ap]m/i', '', $arrDate), false);
            }

            if (preg_match("#Your\s+flight\s+reminder\s+from\s+flynas\s+" . $result['RecordLocator'] . ":\s*([A-Z]{3})-([A-Z]{3}) ([\w\s]+\d+:\d+)#", $this->subj, $m)) {
                if (strtotime($m[3]) == $i['DepDate']) {
                    $i['DepCode'] = $m[1];
                    $i['ArrCode'] = $m[2];
                }
            }
            $result['TripSegments'][] = $i;
        }

        return [$result];
    }
}
