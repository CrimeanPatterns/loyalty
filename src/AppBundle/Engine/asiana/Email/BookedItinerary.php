<?php

namespace AwardWallet\Engine\asiana\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookedItinerary extends \TAccountChecker
{
    use ProxyList;

    public $mailFiles = "asiana/it-35477640.eml, asiana/it-47113223.eml, asiana/it-67831725.eml, asiana/it-67874220.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Departure'                   => 'Departure',
            'Arrival'                     => 'Arrival',
            'SpentAwards'                 => 'Total used mileage details',
            'CancelledStatus'             => 'entire itinerary has been cancelled,',
        ],
        'de' => [
            'Departure'                             => 'Abflug',
            'Arrival'                               => 'Ankunft',
            'Booking No.'                           => 'Buchungsnummer',
            'Booking Date'                          => 'Buchungsdatum',
            'Ticket No.'                            => 'Ticketnummer',
            'Adult'                                 => 'Erwachsener',
            'Total paid amount (payment completed)' => 'Bezahlter Gesamtbetrag (Zahlung abgeschlossen)',
            // Hard Code AirlineName
            'Your Ticket has been Issued' => 'Das Flugticket wurde gekauft.',
            'Asiana Airlines Home'        => 'Asiana Airlines Hauptseite',
        ],
        'ko' => [
            'Departure'                             => '출발',
            'Arrival'                               => '도착',
            'Booking No.'                           => '예약번호',
            'Booking Date'                          => '예약일자',
            'Ticket No.'                            => '항공권 번호',
            'Adult'                                 => '성인',
            'Total paid amount (payment completed)' => ['총 지불금액 (결제완료)', '변경 후 항공권 금액'],
            // Hard Code AirlineName
            'Your Ticket has been Issued' => ['항공권 구매가 완료되었습니다.', '예약이 정상적으로 변경되었습니다.'],
            'Asiana Airlines Home'        => '아시아나항공 메인',
            'SpentAwards'                 => '총 마일리지 공제 내역',
            'CancelledStatus'             => '여정이 모두 취소되었으며, 정상적으로 환불 신청을 완료하였습니다',
            'Total refund'                => '환불총액',
            'miles'                       => '마일',
        ],
    ];

    private $subjects = [
        'en' => ['Your Ticket has been Issued', 'Your Ticket has been Refunded.'],
        'de' => ['Ihr Flugticket wurde ausgestellt.'],
        'ko' => ['항공권 발급이 완료 되었습니다', '항공권 변경이 완료 되었습니다'],
    ];

    private $detectors = [
        'en' => ['Booked Itinerary', 'entire itinerary has been cancelled'],
        'de' => ['Reiseroute der Buchung'],
        'ko' => ['예약 여정', '변경 여정', '여정이 모두 취소되었으며, 정상적으로 환불 신청을 완료하였습니다'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyasiana.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Asiana Airlines') === false) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//flyasiana.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"ticket purchased on Asiana Airlines") or contains(normalize-space(),"Asiana Airlines Co. All Right Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseUrl($email);

        if (count($email->getItineraries()) == 0) {
            $this->parseFlight($email);
        }

        $email->setType('BookedItinerary' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseUrl(Email $email)
    {
        $urls = $this->http->FindNodes("//a[{$this->eq($this->t('E-ticket/Itinerary'))}]/@href");

        foreach ($urls as $i => $url) {
            $http1 = clone $this->http;
            $headers = [
                // 'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
            ];
            // $http1->SetProxy($this->proxyDOP());
            $http1->GetURL($url, $headers);
            // $http1->SetEmailBody(preg_replace('/^([\s\S]{500,})<!DOCTYPE[\s\S]+?<\/html>/', '$1', $http1->Response['body']));
            $xpath = "//text()[{$this->eq($this->t('Operated by'))}]/ancestor::tr[1]/ancestor::*[1]";
            $nodes = $http1->XPath->query($xpath);

            if ($nodes->length == 0) {
                return false;
            }

            if ($i == 0) {
                $f = $email->add()->flight();

                // General
                $conf = $http1->FindSingleNode("//text()[{$this->eq($this->t('From'))}]/preceding::text()[{$this->contains($this->t('Reservation No.'))}][1]/following::text()[normalize-space()][1]");

                if (empty($conf)) {
                    $conf = $http1->FindSingleNode("//text()[{$this->eq($this->t('Operated by'))}]/preceding::text()[{$this->contains($this->t('Reservation No.'))}][1]/following::text()[normalize-space()][1]");
                }

                if (preg_match("#^\s*(?<c1>[A-Z\d\-]{5,})(?:\s*\((?<c2>[A-Z\d]{5,})\))?$#", $conf, $m)
                    || preg_match("#^\s*(?<c1>[A-Z\d\-]{5,})(?:\s*\/(?<c2>[A-Z\d]{5,}))?$#", $conf, $m)
                ) {
                    // 0316-0250/5O9OKF
                    $f->general()
                        ->confirmation($m['c1']);

                    if (!empty($m['c2'])) {
                        $f->general()
                            ->confirmation($m['c2']);
                    }
                }
                $f->general()
                    ->travellers(preg_replace(["/ (MR|MRS|MISS|MSTR|MS)$/", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"], ['', '$2 $1'],
                        $http1->FindNodes("//text()[{$this->contains($this->t('Passenger Name'))} or{$this->contains('승객성명')}]/ancestor::*[self::td or self::th][1]/following-sibling::td[normalize-space(.)!=''][1][contains(.,'/')]")), true);

                $f->issued()
                    ->tickets($http1->FindNodes("//text()[{$this->contains($this->t('Ticket Number'))}]/ancestor::*[self::td or self::th][1]/following-sibling::td[normalize-space(.)!=''][1]"), false);

                $accounts = $http1->FindNodes("//text()[{$this->contains($this->t('Frequent Flyer No.'))}]/ancestor::*[self::td or self::th][1]/following-sibling::td[normalize-space(.)!=''][1]");

                if (!empty($accounts)) {
                    $f->program()
                        ->accounts($accounts, false);
                }

                $this->parsePrice($f);

                foreach ($nodes as $root) {
                    $s = $f->addSegment();

                    $sXpath = "descendant::tr[not(.//tr)][not(.//text()[normalize-space()][{$this->eq($this->t('From'))}])][1]";

                    // Airline
                    $node = $http1->FindSingleNode($sXpath . "/*[3]", $root);

                    if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*$/", $node, $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2]);
                    }

                    $date = $http1->FindSingleNode($sXpath . "/*[5]", $root);

                    // Departure
                    $node = implode("\n", $http1->FindNodes($sXpath . "/*[1]//text()[normalize-space()]", $root));

                    if (preg_match("/^\s*(?<name>.+?)\s*(?:Terminal\s*(?<terminal>.+))?\s*$/si", $node, $m)) {
                        $s->departure()
                            ->noCode()
                            ->name(trim(preg_replace("/\s+/", ' ', $m['name'])));

                        if (isset($m['terminal']) && !empty($m['terminal'])) {
                            $s->departure()
                                ->terminal($m['terminal']);
                        }
                    }
                    $time = $http1->FindSingleNode($sXpath . "/*[6]", $root);

                    if (!empty($date) && !empty($time)) {
                        $s->departure()
                            ->date($this->normalizeDate($date . ', ' . $time));
                    }

                    // Arrival
                    $node = implode("\n", $http1->FindNodes($sXpath . "/*[2]//text()[normalize-space()]", $root));

                    if (preg_match("/^\s*(?<name>.+?)\s*(?:Terminal\s*(?<terminal>.+))?\s*$/si", $node, $m)) {
                        $s->arrival()
                            ->noCode()
                            ->name(trim(preg_replace("/\s+/", ' ', $m['name'])));

                        if (isset($m['terminal']) && !empty($m['terminal'])) {
                            $s->arrival()
                                ->terminal($m['terminal']);
                        }
                    }
                    $time = $http1->FindSingleNode($sXpath . "/*[7]", $root);

                    if (!empty($date) && !empty($time)) {
                        if (preg_match("/^\s*(\d{1,2}:\d{2})\s*([-+]\d)?\s*$/", $time, $m)) {
                            $s->arrival()
                                ->date($this->normalizeDate($date . ', ' . $m[1]));

                            if (!empty($m[2]) && !empty($s->getArrDate())) {
                                $s->arrival()
                                    ->date(strtotime($m[2] . ' day', $s->getArrDate()));
                            }
                        } else {
                            $s->arrival()
                                ->date($this->normalizeDate($date . ', ' . $time));
                        }
                    }

                    $s->extra()
                        ->bookingCode($http1->FindSingleNode($sXpath . "/*[4]", $root))
                        ->duration($http1->FindSingleNode($sXpath . "/*[8]", $root))
                    ;
                    $seat = $http1->FindSingleNode($sXpath . "/*[10]", $root, true, "/^\s*(\d{1,3}[A-Z])\s*$/");

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            } elseif (isset($f)) {
                foreach ($f->getSegments() as $s) {
                    $seat = $http1->FindSingleNode("//tr[not(.//tr)][td[3][normalize-space()='{$s->getAirlineName()}{$s->getFlightNumber()}']]/td[10]",
                        null, true, "/^\s*(\d{1,3}[A-Z])\s*$/");

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }
        }
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking No.'))}]/ancestor::tr[1]/*[normalize-space()][2]", null, true, '/(?:^|\/\s*)([A-Z\d]{5,})$/');

        if ($confirmationNumber) {
            $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking No.'))}]");
            $f->general()->confirmation($confirmationNumber, rtrim($confirmationNumberTitle, ' :'));
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('CancelledStatus'))}]"))) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        }

        $reservationDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Date'))}]/ancestor::tr[1]/*[normalize-space()][2]");

        if (!empty($reservationDate)) {
            $f->general()->date2($reservationDate);
        }

        $passengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query("//tr[ *[1][normalize-space()] and *[2][ descendant::text()[{$this->eq($this->t('Ticket No.'))} or {$this->eq($this->t('E-ticket/Itinerary'))}] ] ]");

        foreach ($passengerRows as $passengerRow) {
            $passenger = $this->http->FindSingleNode('*[1]', $passengerRow, true, "/(?:{$this->opt($this->t('Adult'))}|,)\s*([[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]])$/iu");

            if ($passenger) {
                $passengers[] = $passenger;
            }
            $ticketNumber = $this->http->FindSingleNode("*[2]/descendant::text()[{$this->eq($this->t('Ticket No.'))}]/following::text()[normalize-space()][1]", $passengerRow, true, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }

        if (count($ticketNumbers)) {
            $f->setTicketNumbers(array_unique($ticketNumbers), false);
        }

        $segments = $this->http->XPath->query("//tr[ *[1][ descendant::text()[{$this->eq($this->t('Arrival'))}] ] and *[2][string-length(normalize-space())>2] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            /*
                New York / John F Kennedy(JFK)
                2019.04.17 (Wed) 01:10
            */
            $pattern = "/"
                . "(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)"
                . "\s*(?<dateTime>.{6,})"
                . "/";

            $departure = $this->http->FindSingleNode("preceding-sibling::*[ *[1][ descendant::text()[{$this->eq($this->t('Departure'))}] ] ][1]/*[2]", $segment);

            if (preg_match($pattern, $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['dateTime']))
                ;
            }

            $arrival = $this->http->FindSingleNode('*[2]', $segment);

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['dateTime']))
                ;
            }

            $transit = $this->http->FindSingleNode("following-sibling::*[ *[1][ descendant::text()[{$this->eq($this->t('Transit'))}] ] ][1]/*[2]", $segment);

            if (preg_match("/^{$this->opt($this->t('Non-stop'))}$/i", $transit)) {
                $s->extra()->stops(0);
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your Ticket has been Issued'))}]")->length > 0
                && $this->http->XPath->query("//a[{$this->contains($this->t('Asiana Airlines Home'))}]")->length > 0) {
                $s->airline()->name('OZ');
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                $s->airline()->noNumber();
            }
        }

        $this->parsePrice($f);
    }

    private function parsePrice(Flight $f)
    {
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total paid amount (payment completed)'))}]/ancestor::tr[1]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $payment, $m)) {
            // KRW 8,859,800
            $currency = preg_match("/^[A-Z]{3}$/", $m['currency']) ? $m['currency'] : null;
            $f->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['amount'], $currency))
            ;
        }

        $spentAwards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SpentAwards'))}]/ancestor::tr[1]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if ($spentAwards !== null) {
            $f->price()->spentAwards($spentAwards);
        }

        //collection price for cancelled
        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total refund'))}]/ancestor::tr[1]/descendant::td[1]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s+(?<total>\d[.\'\d]*)\s+(?<spentAwards>\d[,\'\d]*\s+{$this->opt($this->t('miles'))})/", $priceText, $m)) {
            $currency = preg_match("/^[A-Z]{3}$/", $m['currency']) ? $m['currency'] : null;
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($m['currency'])
                ->spentAwards($m['spentAwards']);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Departure']) || empty($phrases['Arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Departure'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Arrival'])}]")->length > 0
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function normalizeDate($text)
    {
        // $this->logger->debug('$text = '.print_r( $text,true));
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 2019.03.31 (Sun) 14:55
            '/^(\d{4})\.(\d{1,2})\.(\d{1,2})[)( [:alpha:]]+(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$/u',
            // 02FEB24 (FRI)     20:25
            '/^\s*(\d{1,2})([A-Z]+)(\d{2})\s*\([A-Z]+\)\s*,\s*(\d{1,2}:\d{2})\s*$/u',
            // 14FEB2024(WED), 20:55
            '/^\s*(\d{1,2})([A-Z]+)(\d{4})\s*\([A-Z]+\)\s*,\s*(\d{1,2}:\d{2})\s*$/u',
        ];
        $out = [
            '$2/$3/$1 $4',
            '$1 $2 20$3, $4',
            '$1 $2 $3, $4',
        ];
        // $this->logger->debug('$text = '.print_r( $text,true));

        $text = preg_replace($in, $out, $text);

        return strtotime($text);
    }
}
