<?php

namespace AwardWallet\Engine\aceh\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "aceh/it-214052696.eml, aceh/it-66395267.eml, aceh/it-67541991.eml, aceh/it-67542179.eml, aceh/it-67542187.eml, aceh/it-67542210.eml";
    private $lang = '';
    private $reFrom = ['@acehotel.com'];
    private $reProvider = ['acehotel.com'];
    private $reSubject = [
        'Status Change from Southwest Airlines',
        'エースホテル京都: 予約確認書 -',
    ];
    private $reBody = [
        'en' => [
            ['Thanks for booking with us', 'Cancellation Policy:'],
            ['Thanks for booking with us', 'TOTAL COST INCLUDING TAX:'],
        ],
        'ja' => [
            ['ご予約ありがとうございますーお目にかかるのを楽しみにしております', '到着日:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'maps'                      => ['/maps/', '.mclinks.contact-client.com/'],
            'Name:'                     => ['Name:', 'NAME:'],
            'Confirmation Number:'      => ['Confirmation Number:', 'CONFIRMATION NUMBER:', 'CONFIRMATION #'],
            'Arrival Date:'             => ['Arrival Date:', 'ARRIVAL DATE:'],
            'Departure Date:'           => ['Departure Date:', 'DEPARTURE DATE:'],
            'Number Of Guests:'         => ['Number Of Guests:', 'NUMBER OF GUESTS:'],
            'Room Type:'                => ['Room Type:', 'ROOM TYPE:'],
            'NIGHTLY RATES:'            => ['DAILY RATE:', 'NIGHTLY RATES:', 'AVERAGE DAILY RATE:'],
            'TOTAL COST OF RESERVATION' => ['TOTAL COST OF RESERVATION', 'TOTAL COST OF RESERVATION EXCLUDING', 'TOTAL COST OF RESERVATION INCLUDING TAX:'],
        ],
        'ja' => [
            'Name:'                => ['お名前:'],
            'Confirmation Number:' => ['コンファメーション番号:'],
            'Arrival Date:'        => ['到着日:'],
            'Departure Date:'      => ['出発日:'],
            'Check-in:'            => ['チェックイン:'],
            'Check-out:'           => ['チェックアウト:'],
            'Number Of Guests:'    => ['ご利用人数:'],
            'Room Type:'           => ['部屋のタイプ:'],
            'Cancellation Policy:' => 'キャンセルポリシー:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseHotel($parser, $email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
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

    private function parseHotel(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        // Your Reservation At Ace Hotel Downtown Los Angeles
        // Your Reservation 133909152 At Ace Hotel New Orleans
        if (preg_match("/Your Reservation at (Ace Hotel [\w\s]{3,})/i", $parser->getHeader('subject'), $m)
            || preg_match("/Your Reservation [\w]+ at (Ace Hotel [\w\s]{3,})/i", $parser->getHeader('subject'), $m)) {
            $h->hotel()->name($m[1]);
        }
        // Ace Hotel London Shoreditch: your reservation confirmation #2284131
        // Ace Hotel Kyoto: Reservation Confirmation - 9146SC015117
        // エースホテル京都: 予約確認書 - 9146SC021458
        if (preg_match("/^(Ace Hotel [\w\s]{3,}):\s+\w+/", $parser->getHeader('subject'), $m)
            || preg_match("/^(エースホテル京都):\s+\w+/u", $parser->getHeader('subject'), $m)) {
            $h->hotel()->name($m[1]);
        }

        $phone = $this->http->FindSingleNode("(//a[{$this->contains($this->t('tel:'), '@href')}])[1]", null, true, '/^[\d.\-\s()]{5,}$/');

        $addressPattern = '/^[[:alpha:]\s.,\-()\d]{10,300}/';
        // it-67542187.eml
        $address = join(', ', $this->http->FindNodes("(//a[{$this->contains($this->t('tel:'), '@href')}])[1]/ancestor::div[1]/preceding-sibling::div//text()[string-length(normalize-space()) > 13]", null, $addressPattern));

        // it-66395267.eml
        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Tel:'))}]/following-sibling::*[1])[1]", null, true, '/^[\d.\-\s()]{5,}$/');
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tel:'))}]/preceding::text()[string-length(normalize-space()) > 13][1]", null, true, $addressPattern);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(//a[{$this->contains($this->t('tel:'), '@href')}]/preceding::text()[string-length(normalize-space()) > 13][1])[1]", null, true, '/^.*?\d+.*$/');
        }

        // it-67542179.eml
        if (empty($address)) {
            $address = $this->http->FindSingleNode("(//a[{$this->contains($this->t('maps'), '@href')}])[1]", null, true, $addressPattern);
        }

        if (empty($phone) && empty($address)) {
            $hotelText = implode("\n", $this->http->FindNodes("//img[contains(@src, 'instagram')]/preceding::text()[contains(normalize-space(), ',')][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
            $hotelText = str_replace("|\n", '', $hotelText);

            if (preg_match("/\n*\s*(?<address>.+)\n*(?<phone>[\d\.]{8,})\n*\s*STAY/u", $hotelText, $match)) {
                $address = $match['address'];
                $phone = $match['phone'];
            }
        }

        if (!empty($h->getHotelName()) && empty($phone) && empty($address)) {
            // Ace Hotel Portland   |  1022 SW Harvey Milk St. Portland OR 97205   |  503.228.2277
            $hotelText = $this->http->FindSingleNode("//img[contains(@src, 'instagram')]/preceding::text()[normalize-space()][1]/ancestor::p[1]");

            if (preg_match("/^\s*{$h->getHotelName()}\s*\|\s*(?<address>[^\|]+)\s*\|\s*(?<phone>[\d\.]{8,})\s*$/u", $hotelText, $match)) {
                $address = $match['address'];
                $phone = $match['phone'];
            }
        }

        $h->hotel()->phone($phone, false, true);
        $h->hotel()->address("{$address}");

        $h->general()->traveller($this->http->FindSingleNode("(//node()[{$this->contains($this->t('Name:'))}]/following::text()[normalize-space()])[1]"));
        $h->general()->confirmation($this->http->FindSingleNode("(//node()[{$this->contains($this->t('Confirmation Number:'))}]/following::text()[normalize-space()])[1]"));

        // checkIn
        $checkIn = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Arrival Date:'))}]/following::text()[normalize-space()])[1]");

        if ($time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in:'))}]", null, false,
            '/:\s*(.+)/')) {
            $checkIn .= ", {$time}";
        }
        $h->booked()->checkIn($this->normalizeDate($checkIn));

        // checkOut
        $checkOut = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Departure Date:'))}]/following::text()[normalize-space()])[1]");

        if ($time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out:'))}]", null, false,
            '/:\s*(.+)/')) {
            $checkOut .= ", {$time}";
        }
        $h->booked()->checkOut($this->normalizeDate($checkOut));

        // guests
        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number Of Guests:'))}]/following::text()[normalize-space()][1]");
        $h->booked()->guests($guests, false, true);

        $r = $h->addRoom();

        $rate = (implode(", ", array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('NIGHTLY RATES:'))}]/following::text()[string-length()>2][1]/ancestor::table[1]/descendant::tr", null, "#^\s*([\d\.\/]+\s*\-*\s*\D*\s*[\d\.\,]+)\s*$#u"))));

        if (empty($rate)) {
            $rate = (implode(", ", array_filter($this->http->FindNodes("(//node()[{$this->contains($this->t('NIGHTLY RATES:'))}]/following::span[normalize-space()])[1]/descendant::text()[normalize-space()]", null, "#^\s*([\d\.\/]+\s*\-*\s*\D*\s*[\d\.\,]+)\s*$#u"))));
        }

        if (!empty($rate)) {
            $r->setRate($rate);
        }

        $r->setType($this->http->FindSingleNode("(//node()[{$this->contains($this->t('Room Type:'))}]/following::text()[normalize-space()])[1]"));

        $cost = $this->http->FindSingleNode("//text()[{$this->contains($this->t('TOTAL COST OF RESERVATION'))}]/ancestor::*[1]/following::text()[normalize-space()][1]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $cost),
            $matches)) {
            $h->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->cost($this->normalizeAmount($matches['amount']));
        }
        $cancellation = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Cancellation Policy:'))} and string-length(.) > 50])[1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("(//node()[{$this->contains($this->t('Cancellation Policy:'))}]/following::text()[normalize-space()])[1]");
        }
        $h->setCancellation($cancellation, false, true);
        $this->detectDeadLine($h);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
        // you can cancel or modify your booking free of charge by 3PM, 24 hours prior to your arrival
        if (preg_match('/you can cancel or modify your booking free of charge by (?<hours>\d+[AP]M), (?<prior>\d+ hours?) prior to your arrival/',
                $cancellationText, $m)
            // Cancel by 3:00pm JST, 2 days prior to arrival, to avoid being charged first night's room and tax.
            || preg_match('/Cancel by (?<hours>[\d:]+[AP]M) [A-Z]{3}, (?<prior>\d+ days?) prior to arrival, to avoid being charged first night/i',
                $cancellationText, $m)
            // Please let us know by 3pm (15:00) CST, 72 hours prior to the day of arrival if you need to cancel
            || preg_match('/Please let us know by (?<hours>[\d:]+[AP]M) .+?, (?<prior>\d+ hours?) prior to the day of arrival if you need to cancel/i',
                $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hours']);
        }
        // 到着日2日前の現地時間午後3時まではキャンセル料はかかりません。
        // 午後3時ま => 15:00PM
        if (preg_match('/到着日(?<prior>\d+)日前の現地時間午後(?<hours>[\d:]+)時まではキャンセル料はかかりません。/ui',
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['prior'] . 'days', $m['hours'] . 'PM');
        }

        if (preg_match('/Please let us know by (?<time>\d+\s*a?p?m) PST (?<prior>\w+\s*days?) before the check in date if you need to cancel/ui', $cancellationText, $m)) {
            $m['prior'] = str_replace(['one', 'two'], ['1', '2'], $m['prior']);

            $h->booked()->deadlineRelative($m['prior'], $m['time']);
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug($str);
        $in = [
            // 2020年8月4日, 15:00
            '/^(\d{4})年(\d+)月(\d+)日, (\d+:\d+)$/u',
        ];
        $out = [
            "$2/$3/$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }
}
