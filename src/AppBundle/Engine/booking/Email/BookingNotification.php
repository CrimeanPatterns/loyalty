<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\booking\Email;

class BookingNotification extends \TAccountChecker
{
    public $mailFiles = "booking/it-8181983.eml";

    private $detects = [
        'en' => [
            'Click here to view reservation',
        ],
    ];

    private $reFrom = ['.booking.com', '@booking.com'];

    private $lang = '';

    private static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $body = preg_replace('/\s+/', ' ', $body);

        if ($this->http->XPath->query("//a[contains(@href,'live.ipms247.com')]")->length > 0) {
            foreach ($this->detects as $lang => $detects) {
                foreach ($detects as $detect) {
                    if (stripos($body, $detect) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && $this->striposAll($headers['from'], $this->reFrom) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];
        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//span[contains(., 'Reservation No')]", null, true, '/:\s*(\w+)/');
        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'New Booking Notification')]/following-sibling::td[1]");
        // 2ChainName
        // CheckInDate
        $it['CheckInDate'] = $this->normalizeDate($this->getNode('Arrival'));
        // CheckOutDate
        $it['CheckOutDate'] = $this->normalizeDate($this->getNode('Departure'));
        // Address
        $it['Address'] = trim(str_replace('.', '', $this->getNode('City')) . ', ' . $this->getNode('Country'));
        // DetailedAddress
        // Phone
        $it['Phone'] = $this->getNode('Phone');
        // Fax
        // GuestNames
        $it['GuestNames'][] = $this->getNode('Guest');
        // Guests
        $it['Guests'] = (int) $this->getNode('Adult');
        // Kids
        $it['Kids'] = (int) $this->getNode('Child');
        // Rooms
        $it['Rooms'] = $this->getNode('No. of Rooms');
        // Rate
        // RateType
        $it['RateType'] = $this->getNode('Rate Type');
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->getNode('Room Type');
        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $total = $this->getNode('Total');

        if (preg_match('/(\D+)\s*([\d\.]+)/', $total, $m)) {
            $it['Currency'] = str_replace('€', 'EUR', $m[1]);
            $it['Total'] = (float) $m[2];
        }
        // Currency
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d+)\/(\d+)\/(\d{2,4})/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("(//td[contains(., '" . $str . "') and not(.//td) and not(contains(., 'Information'))]/following-sibling::td[1])[1]");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
