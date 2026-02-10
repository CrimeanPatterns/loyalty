<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\friendchips\Email;

class ToWebCheckIn extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-10202037.eml";

    public $provider = '@news.tuifly.com';

    public $reBody = 'tuifly.com';
    public static $detectBody = [
        'de' => 'ZUM WEB CHECK-IN',
    ];

    public $dict = [
        'de' => [
            //			'Ihre Buchung' => '',
            //			'Flug' => '',
            //			'Von:' => '',
            //			'Nach:' => '',
        ],
    ];

    public $lang = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);
        $its = $this->parseEmail();

        return [
            'emailType'  => 'ToWebCheckIn',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->provider) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->provider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '" . $this->t('Ihre Buchung') . "')]", null, true, "#^\s*" . $this->t('Ihre Buchung') . "\s+([A-Z\d]{5,7})\s*$#");

        $it['Passengers'] = array_unique($this->http->FindNodes("//img[contains(@src, 'icon_person.jpg')]/following::text()[normalize-space()][1]"));

        $xpath = "//img[contains(@src, 'plane_icon.jpg')]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length !== 0) {
            $this->logger->info('Segments found by: ' . $xpath);
        }

        foreach ($segments as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $flight = $this->http->FindSingleNode(".", $root);

            if (preg_match("#" . $this->t('Flug') . "\s*\d+\s+([A-Z\d]{2})\s*-\s*(\d{1,5})\s+([\d\.]+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $date = trim($m[3]);
            }

            $route = $this->http->FindSingleNode("./following::table[normalize-space()][1]//tr[starts-with(normalize-space(), '" . $this->t('Von:') . "')][last()]", $root);

            if (preg_match("#" . $this->t('Von:') . "\s*(.+)\s+([A-Z]{3})\s*(\d{1,2}:\d{2})#", $route, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];

                if (!empty($date)) {
                    $seg['DepDate'] = strtotime($date . ', ' . $m[3]);
                }
            }

            $route = $this->http->FindSingleNode("./following::table[normalize-space()][1]//tr[starts-with(normalize-space(), '" . $this->t('Nach:') . "')][last()]", $root);

            if (preg_match("#" . $this->t('Nach:') . "\s*(.+)\s+([A-Z]{3})\s*(\d{1,2}:\d{2})#", $route, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];

                if (!empty($date)) {
                    $seg['ArrDate'] = strtotime($date . ', ' . $m[3]);
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($str)
    {
        if (!isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detectBody as $lang => $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->reBody) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
