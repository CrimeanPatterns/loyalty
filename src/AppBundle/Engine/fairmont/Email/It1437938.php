<?php

namespace AwardWallet\Engine\fairmont\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1437938 extends \TAccountCheckerExtended
{
    public $mailFiles = "fairmont/it-1.eml, fairmont/it-1437938.eml, fairmont/it-1673887.eml, fairmont/it-1673888.eml, fairmont/it-1673901.eml, fairmont/it-1673902.eml, fairmont/it-1673947.eml, fairmont/it-1968871.eml, fairmont/it-2196293.eml, fairmont/it-43055595.eml, fairmont/it-62043186.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confirmationNo' => ['Numero de confirmacion'],
            //            'cancellationNo' => '',
            //            'cancelledPhrases' => '',
            'firstName'     => 'Nombre del huesped',
            'lastName'      => 'Apellido del huesped',
            'name'          => ['/Gracias por elegir (.+?)\s*\. Durante su/'],
            'arrivalDate'   => 'Fecha de llegada',
            'departureDate' => 'Fecha de salida',
            //'arrivalTime' => 'Departure Date',
            //'departureTime' => 'Departure Time',
            'guests'                  => 'Numero de adultos',
            'kids'                    => 'Numero de ninos',
            'cancellation'            => 'Descripcion de cancelacion',
            'cancellationDate'        => 'Fecha de cancelacion para evitar cargos',
            'Rate Per Room Per Night' => 'Tarifa por habitacion por noche',
            'Rate Per Room Stop'      => [''],
            'tel'                     => ['Numero sin costo', 'Tel'],
        ],
        'en' => [
            'confirmationNo'   => ['Confirmation Number', 'Confirmation #'],
            'cancellationNo'   => 'Cancellation Number',
            'cancelledPhrases' => 'This is to confirm your cancellation of your visit to',
            'firstName'        => 'First Name',
            'lastName'         => 'Last Name',
            'name'             => [
                //'/Thank you for choosing The (.+?)\s*\. While you are here/',
                '/(Fairmont Mayakoba, Riviera Maya)/', // hard code because at first without ',', and below with
                '/(Fairmont Olympic Hotel, Seattle)/',
                '/(Fairmont Chateau Laurier)/',
                '/(Fairmont Winnipeg)/',
                '/(Fairmont Jasper Park Lodge)/',
                '/(Fairmont Le Manoir Richelieu)/',
                '/Thank you for selecting (Fairmont Century Plaza) as your home/u',
                '/(The Fairmont Washington\, D\.C\. Georgetown)/',
                '/Thank you for choosing (.+?)\s*[\–-] we are/u',
                '/Thank (?i)you for choosing (.+?)\s*[.!] (?:While you are here|We are)/',
                '/Thank you for choosing the (.+?)\s+for your/',
                '/Thank you for choosing (.+?)\s+for your/',
                '/Thank you for choosing ([\w ]+?)[.! ]*$/m',
                '/Thank you for booking your stay at\s*(\D+)\.\s*We/m',
                '/Aloha and thank you for choosing (.+?)\s+for your/',
                '/Upcoming Stay at the (.+?)! Our Guest/',
                '/We look forward to welcoming you to\s+([[:upper:]\d].{2,}?)\./u',
                '/We are looking forward to your arrival to ([\w ]+?)[.!]/',
                '/This is to confirm your cancell?ation of your visit to (.+?)\./',
                '/a magical holiday at (.+)[.]\s*/',
                '/Thank you for choosing (.+?)[,.]\s*we\s*look/i',
                '/Best Regards[ ]*,[ ]*\n+[ ]*(\D+?)[ ]*\n+[ ]*(?:Download|Confirmation)/',
                '/on the Coast[!]\s*(.+)\s*sits proudly/u',
                '/Thank you for choosing\s*(.+)\.\s*As your stay/u',
                '/Greetings from\s*(.+)\!/u',
            ],
            'arrivalDate'             => 'Arrival Date',
            'arrivalTime'             => ['Arrival Time', 'Check-In Time:'],
            'departureDate'           => 'Departure Date',
            'departureTime'           => ['Departure Time', 'Check-Out Time:'],
            'guests'                  => ['Number Of Adults', 'Number of Adults'],
            'kids'                    => ['Number Of Children', 'Number of Children'],
            'cancellation'            => 'Cancellation Policy',
            'cancellationDate'        => ['Cancellation Date to Avoid Penalty', 'Cancel Date To Avoid Fees'],
            'Rate Per Room Per Night' => 'Rate Per Room Per Night',
            'tel'                     => ['Toll Free', 'Tel', 'T:'],
        ],
    ];

    private $subjects = [
        'es' => ['de confirmación para '],
        'en' => ['Confirmation for ', 'Online check-in'],
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $ns = explode('\\', __CLASS__);
        $class = end($ns);
        $email->setProviderCode($this->providerCode);
        $email->setType($class . ucfirst($this->lang));
        $this->parseTextEmail($parser, $email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'fairmont.com') !== false;
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
        return ['fairmont', 'movenpick'];
    }

    protected function htmlToText2(string $string): string
    {
        return $s = str_replace(chr(194) . chr(160), ' ',
            html_entity_decode(
            preg_replace('/\s{10,}/', "\n\n",
                preg_replace('/<[^>]+>/', "\n", $string))));
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function parseTextEmail(PlancakeEmailParser $parser, Email $email): void
    {
        $patterns = [
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // single-line
            'travellerName2' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // multi-line
        ];

        $htmlBody = $parser->getHTMLBody();
        $htmlBody = str_replace(
            ['Â', 'â', 'À', 'à', 'É', 'é', 'È', 'è', 'Ó', 'ó', 'Ñ', 'ñ', 'Ú', 'ú', "\r"],
            ['A', 'a', 'A', 'a', 'E', 'e', 'E', 'e', 'O', 'o', 'N', 'n', 'U', 'u', ''],
        $htmlBody);
        $this->http->SetEmailBody($htmlBody);
        $text = $this->htmlToText2($htmlBody);
//        $this->logger->debug($text);

        $h = $email->add()->hotel();

        if (preg_match("/{$this->opt($this->t('cancelledPhrases'))}/", $text)) {
            $h->general()->cancelled();
        }

        if (preg_match_all("/^[ ]*{$this->opt($this->t('cancellationNo'))}\s*([A-Z\d]+)[ ]*$/m", $text, $m)
            && count($m[1]) === 1
        ) {
            // it-62043186.eml
            $h->general()->cancellationNumber($m[1][0]);
        }

        if (preg_match_all("/^[ ]*({$this->opt($this->t('confirmationNo'))})\s*([A-Z\d]+)[ ]*$/m", $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $h->general()->confirmation($m[2], $m[1]);
            }
        } else {
            $h->general()->noConfirmation();
        }

        $isNameFull = true;
        $traveller = trim($this->re("/{$this->opt($this->t('firstName'))}\s*({$patterns['travellerName']})\n/u", $text) . ' ' .
            $this->re("/{$this->opt($this->t('lastName'))}\s*({$patterns['travellerName']})\n/u", $text)
        );

        if (empty($traveller) && preg_match("/Dear\s+({$patterns['travellerName2']})\s+[,]/u", $text, $m)) {
            $isNameFull = null;
            $traveller = $m[1];
        }

        if (!empty($traveller)) {
            $h->general()->traveller($traveller, $isNameFull);
        }

        $hotelName = null;

        foreach ($this->t('name') as $reName) {
            $hotelName_temp = trim($this->re($reName, $text));

            if (empty($hotelName_temp)) {
                continue;
            }

            if ($hotelName_temp === 'The Empress Hotel') {
                $hotelName_temp = 'Fairmont Empress';
            }

            $hotelNameNoThe_temp = preg_replace('/\bThe\s+/i', '', $hotelName_temp);
            $hotelNameVariants = [$hotelNameNoThe_temp, str_replace(' ', ' ', $hotelNameNoThe_temp), strtoupper($hotelNameNoThe_temp)];
            $xpathHotelName = $this->contains($hotelNameVariants);

            if ($this->http->XPath->query("//text()[{$xpathHotelName}]")->length > 1) {
                $hotelName = $hotelName_temp;

                break;
            }
        }

        if (empty($hotelName)) { // from image alt attribute
            $hotelName_temp = $this->http->FindSingleNode("//tr[following-sibling::tr[normalize-space()] and normalize-space()='']/descendant::img[contains(@src,'myfrhi.com/') and normalize-space(@alt)]/@alt");

            if (!empty($hotelName_temp) && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 0) {
                $hotelName = $hotelName_temp;
            }
        }

        if (empty($hotelName)) { // hard-code variants
            $hotelNames = [
                'Fairmont Royal York', 'Fairmont Chateau Whistler', 'Fairmont Empress',
                'Fairmont Sonoma Mission Inn & Spa', 'The Fairmont Sonoma Mission Inn & Spa',
                'Fairmont San Francisco', 'Fairmont Sanur Beach Bali', 'The Fairmont Chateau Lake Louise',
                'Fairmont Pittsburgh', 'Fairmont El San Juan Hotel', 'The Empress Hotel', 'The Fairmont Vancouver Airport',
                'Fairmont Royal Pavilion', 'Fairmont Waterfront', 'Fairmont Winnipeg', 'Fairmont Copley Plaza', 'Fairmont Hotel Vancouver',
                'Fairmont Banff Springs', 'Fairmont Le Château Frontenac', 'Fairmont Mayakoba, Riviera Maya',
            ];

            $hotelName = $this->re("/(?:{$this->opt($this->t('this email.'))}|-->)[ ]*\n+^[ ]*(?i)({$this->opt($hotelNames)})(?-i)[,. ]*(?:\n+.{2,}){1,4}\s+{$this->opt($this->t('tel'))}/mu", $text);

            if (empty($hotelName)) {
                $hotelNameTemp = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($hotelNames)}]")));

                if (count($hotelNameTemp) == 1) {
                    $hotelName = $hotelNameTemp[0];
                }
            }
        }

        if ($hotelName == 'Fairmont Sonoma Mission Inn and Spa') {
            $hotelName = 'Fairmont Sonoma Mission Inn & Spa';
        }

        $address = null;

        if (empty($hotelName)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Toll Free']/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^(?<hotelName>.+)\n+(?<address>(?:.+\n){2,5}){$this->opt($this->t('Toll Free'))}/", $hotelInfo, $m)) {
                $hotelName = $m['hotelName'];
                $address = $m['address'];
            }
        }

        $this->logger->debug($text);

        if ($hotelName) {
            $address = preg_replace('/,*\n+\s*/', ', ', trim(
                $this->re("/{$this->opt($this->t('arrivalDate'))}.+?\n.*[ ]*{$hotelName}[^\n]*((?:\n+[ ]*[^\n]+){1,7}?)\s+{$this->opt($this->t('tel'))}/su", $text)
                ?? $this->re("/{$this->opt($this->t('arrivalDate'))}.+?\n[ ]*{$hotelName}[,.\s]*((?:\n+[ ]*[^\n]+){1,7}?)\s+{$this->opt($this->t('tel'))}/su", $text)
                ?? $this->re("/Stay connected\n+(.+)\s+[+]\d[\d\s\-]+/", $text)
            ));
        }

        if (empty($address) && $hotelName) {
            $address = preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t('fees'))}\s*{$hotelName}\s*(.+?)\s+{$this->opt($this->t('Toll Free'))}/su", $text));
        }

        if (empty($address) && $hotelName) {
            $address = preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t('cancellationDate'))}\s*[\s\S]+?\n[ ]*{$hotelName}\n((?:.+\n+){1,7})\n\s*{$this->opt($this->t('Toll Free'))}/",
                    $text));
        }

        if (empty($address) && $hotelName) {
            $address = preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t($hotelName))}.+\n+(.+)\n+\s*{$this->opt($this->t('tel'))}/u", $text));
        }

        $hName = $this->re("/^The\s+(.+)/", $hotelName);

        if (empty($address) && $hName) {
            $address = preg_replace('/\s*\n\s*/', ' ', $this->re("/{$this->opt($this->t('fees'))}\s*{$hName}\s*(.+?)\s+{$this->opt($this->t('Toll Free'))}/s", $text));

            if (empty($address)) {
                $address = preg_replace('/\s*\n\s*/', ' ',
                    $this->re("/{$this->opt($this->t('cancellationDate'))}\s*[\s\S]+?\n{$hName}\n\s*((?:.+\n+){1,7})\n\s*{$this->opt($this->t('Toll Free'))}/",
                        $text));
            }

            if (!empty($address)) {
                $hotelName = $hName;
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->address(preg_replace("/\s*,\s*{$this->opt($this->t('Map It'))}$/i", '', $address));

        // Date
        $checkInDate = strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('arrivalDate'))}\s+(.+?)\n/", $text)), false);

        if ($checkInTime = $this->re("/{$this->opt($this->t('arrivalTime'))}\s+([\d:]+(?:\s*[AP]M)?)(\s*hrs)?\n/", $text)) {
            $checkInDate = strtotime($checkInTime, $checkInDate);
        }
        $h->booked()->checkIn($checkInDate);

        $checkOutDate = strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('departureDate'))}\s+(.+?)\s*\n/", $text)), false);

        if ($checkOutTime = $this->re("/{$this->opt($this->t('departureTime'))}\s+([\d:]+(?:\s*[AP]M)?)(\s*hrs)?\s*\n/", $text)) {
            $checkOutDate = strtotime($checkOutTime, $checkOutDate);
        }
        $h->booked()->checkOut($checkOutDate);

        $h->booked()->guests($this->re("/{$this->opt($this->t('guests'))}\s+(\d+)\n/", $text), false, true);
        $h->booked()->kids($this->re("/{$this->opt($this->t('kids'))}\s+(\d+)\n/", $text), false, true);

        $setRate = null;
        $rateTexts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Rate Per Room Per Night'))}]/ancestor::td[1]/following::td[normalize-space()][1]/descendant::text()[normalize-space()]", null, '/^([A-Z]{3}.*\d.*)/'));

        if (count($rateTexts) > 1) {
            $setRate = implode(', ', $rateTexts);
        } elseif (count($rateTexts) === 1) {
            $setRate = array_shift($rateTexts);
        }

        $setType = $this->re("/{$this->opt(['Room Type to Charge', 'Room Type'])}\s+(.+)\n/", $text);

        if ($setRate !== null || $setType !== null) {
            $room = $h->addRoom();

            if ($setRate !== null) {
                $room->setRate($setRate);
            }

            if ($setType !== null) {
                $room->setType($setType);
            }
        }

        $currency = $this->re('/(?:TOTAL AMOUNT OF THE STAY INCLUDING TAX AND FEES|Deposit Amount):?\s*(\D+)\s*/', $text);
        $total = $this->re('/(?:TOTAL AMOUNT OF THE STAY INCLUDING TAX AND FEES|Deposit Amount):?\s*\D+\s*(\d[,.\'\d ]*)/', $text);

        if (!empty($currency) && $total !== null) {
            // USD 159.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($total, $currencyCode));
        }

        $phone = $this->re("/{$this->opt($this->t('arrivalDate'))}[\s\S]+?{$this->opt($this->t('tel'))}\s+([+(\d][-. \d)(]{5,}[\d)])[ ]*(?:E\:\s*)?\n/", $text);

        if (empty($phone)) {
            $phone = $this->re("/{$address}\s*([+]\d[\d\s\-]+)/", $text);
        }
        $h->hotel()->phone($phone);
        $h->hotel()->fax($this->re("/{$this->opt($this->t('arrivalDate'))}[\s\S]+?{$this->opt($this->t('Fax'))}\s+([+(\d][-. \d)(]{5,}[\d)])[ ]*\n/", $text), false, true);

        $cancellationPolicy = $this->re("/{$this->t('cancellation')}\s+(.+?)\n/", $text);
        $h->setCancellation($cancellationPolicy, false, true);
        $this->detectDeadLine($h, $text);
        $dateDeadline = $this->re('/Cancel Date To Avoid Fees\s*(\d{4}[-]\d{1,2}[-]\d{1,2})/', $text);

        if (!empty($dateDeadline)) {
            $h->booked()->deadline(strtotime($dateDeadline));
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $text): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        $cancelDate = $this->re("/{$this->opt($this->t('cancellationDate'))}\s+(.+?)\n/", $text);

        if (isset($cancelDate) && ($cancelDateNormal = $this->normalizeDate($cancelDate))) {
            $h->booked()->deadline2($cancelDateNormal);
        } elseif (preg_match('/^(?<prior>\d{1,3} (?:hours?|days?)) prior to arrival/i', $cancellationText, $m)
            || preg_match('/\b(?<prior>\d{1,3} days?) prior to arrival to avoid/i', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }
    }

    private function assignProvider($headers): bool
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for booking at Mövenpick Hotel")]')->length > 0) {
            $this->providerCode = 'movenpick';

            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true
            || $this->http->XPath->query('//a[contains(@href,"fairmont.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing The Fairmont") or contains(normalize-space(),"Thank you for choosing Fairmont") or contains(normalize-space(),"Gracias por elegir The Fairmont")]')->length > 0
        ) {
            $this->providerCode = 'fairmont';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['arrivalDate']) || empty($phrases['departureDate'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['arrivalDate'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['departureDate'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        $text = trim($text);

        if (preg_match('/^[[:alpha:]]{2,}, (\d{1,2})[\s\-]*([[:alpha:]]{3,}), (\d{4})$/u', $text, $m)) {
            // Saturday, 01 Jun, 2019
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[[:alpha:]]{2,}, (\d{1,2}) de ([[:alpha:]]{3,}) de (\d{4})$/iu', $text, $m)) {
            // martes, 29 de oct de 2013
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
