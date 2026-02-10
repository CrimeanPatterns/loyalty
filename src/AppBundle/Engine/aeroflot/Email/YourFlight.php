<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Engine\MonthTranslate;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-10640306.eml, aeroflot/it-33162329.eml";

    private $from = '/[.@]aeroflot[.]ru/i';

    private $detects = [
        'en' => 'Order additional services for your flight',
        'ru' => 'Закажите дополнительные услуги для вашего ближайшего путешествия',
    ];

    private $prov = 'Aeroflot';

    private $lang = 'en';

    private static $dictionary = [
        'en' => [
            'Booking reference' => ['Booking reference', 'Booking code'],
        ],
        'ru' => [
            'Booking reference' => 'Код бронирования',
        ],
    ];

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        foreach ($this->detects as $lang => $detect) {
            if (false !== stripos($parser->getHTMLBody(), $detect)) {
                $this->lang = substr($lang, 0, 2);
            }
        }

        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Order additional services for your flight') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'aeroflot')] | //a[contains(@href, 'aeroflot')]")->length === 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect) || $this->http->XPath->query("//*[contains(normalize-space(),\"" . $detect . "\")]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//div[{$this->contains($this->t('Booking reference'))}]/following-sibling::div[1]");

        $ruleTime = "translate(normalize-space(.),'0123456789','dddddddddd--')='dd:dd'";

        $xpath = "//text()[{$ruleTime}]/ancestor::tr[count(./descendant::text()[{$ruleTime}])=2][1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length == 0) {
            $xpath = "//img[contains(@src, 'airplane') and not(.//img)]/ancestor::tr[1]";
            $roots = $this->http->XPath->query($xpath);
        }

        if (0 === $roots->length) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $date = '';

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s*(.+),\s+(?:[^\d\s]+,\s+(.+)|(.+),\s+[^\d\s]+)$/u', $this->http->FindSingleNode('preceding-sibling::tr[normalize-space(.)!=""][1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Aircraft'] = $m[3];
                $date = !empty($m[4]) ? $this->normalizeDate($m[4]) : $this->normalizeDate($m[5]);
            }

            if (preg_match('/(\d{1,2}:\d{2})\s*([A-Z]{3})\s*([\w\s\-\.]+)\s*(?:(\+\d{1}))?/u', $this->http->FindSingleNode('descendant::td[1]', $root), $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);

                if (!empty($m[4])) {
                    $seg['DepDate'] = strtotime($m[4] . ' day', $seg['DepDate']);
                }
                $seg['DepCode'] = $m[2];
                $seg['DepName'] = trim($m[3]);
            }

            if (preg_match('/([A-Z]{3})\s*(\d{1,2}:\d{2})\s*([\w\s\-\.]+)\s*(?:(\+\d{1}))?/u', $this->http->FindSingleNode('descendant::td[last()]', $root), $m)) {
                $seg['ArrCode'] = $m[1];
                $seg['ArrDate'] = strtotime($m[2], $date);

                if (!empty($m[4])) {
                    $seg['ArrDate'] = strtotime($m[4] . ' day', $seg['ArrDate']);
                }
                $seg['ArrName'] = trim($m[3]);
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate(string $str = '')
    {
//        $this->logger->info($str);
        $regExps = [
            '/(\d{1,2})\s+(\w+)\s+(\d{2,4})/u',
        ];

        if ('en' === $this->lang) {
            return strtotime($str);
        }

        foreach ($regExps as $regExp) {
            if (preg_match($regExp, $str, $m)) {
                return strtotime($m[1] . ' ' . MonthTranslate::translate($m[2], $this->lang) . ' ' . $m[3]);
            }
        }

        return $str;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
