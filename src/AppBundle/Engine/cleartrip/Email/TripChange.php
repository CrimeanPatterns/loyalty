<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class TripChange extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-8463675.eml, cleartrip/it-10096668.eml";

    protected $lang = '';

    protected $detectSubject = [
        'flight rescheduled by the airline',
        'Change in',
    ];
    protected $langDetectors = [
        'en' => ['Departure time:'],
    ];

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cleartrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false
            && stripos($headers['subject'], 'Trip ID') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Dear Cleartripper") or contains(normalize-space(.),"Cleartrip account") or contains(.,"www.cleartrip.com") or contains(.,"@cleartrip.com")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $it = $this->parseEmail($parser);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TripChange_' . $this->lang,
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail(\PlancakeEmailParser $parser)
    {
        $bodyTexts = $this->http->FindNodes('//body/descendant::text()[not(./ancestor::*[name()="style" or name()="script"])]');
        $bodyTextValues = array_values(array_filter(array_map('trim', $bodyTexts)));

        if (empty($bodyTextValues[0])) {
            return false;
        }

        $textBody = implode(' ', $bodyTextValues);
        //		echo '___'.$textBody;

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/your[ ]*trip[ ]*ID[ ]*(\d{5,})[ ]*for/i', $textBody, $matches)) {
            $it['TripNumber'] = $matches[1];
        }

        if (preg_match('/ PNR[ ]*([A-Z\d]{5,})[ ]*\|/', $textBody, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        $it['TripSegments'] = [];
        $seg = [];

        $segText = $this->re("/New Schedule([\s\S]+)/i", $textBody);
        // QR-572 | DOH–BLR | Departure time: 1940, Arrival time: 0230+1/ on 12OCT
        // Air India AI- 543 | HYD-DEL | Departure time: 1005, Arrival time: 12:30 on 07 Jul
        if (preg_match('/ (?<airline>[A-Z\d]{2})[- ]*(?<flightNimber>\d+)[ ]*\|[ ]*(?<depCode>[A-Z]{3})[ ]*\W[ ]*(?<arrCode>[A-Z]{3})[ ]*\|[ ]*Departure time[ ]*:[ ]*(?<timeDep>\d{2}:?\d{2})[ ]*,[ ]*Arrival time[ ]*:[ ]*(?<timeArr>\d{2}:?\d{2})[ ]*(?:\+[ ]*\d{1,2})?\/?[ ]*on[ ]*(?<date>\d{1,2}[ ]*[^,.\d\s]{3,})/u', $segText, $matches)) {
            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNimber'];
            $seg['DepCode'] = $matches['depCode'];
            $seg['ArrCode'] = $matches['arrCode'];
            $dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);
            $date = EmailDateHelper::parseDateRelative($matches['date'], $dateRelative);

            if ($date) {
                $seg['DepDate'] = strtotime($matches['timeDep'], $date);
                $seg['ArrDate'] = strtotime($matches['timeArr'], $date);
            }
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
