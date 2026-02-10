<?php

namespace AwardWallet\Engine\deem\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class TripFor extends \TAccountChecker
{
    public $mailFiles = "deem/it-1.eml, deem/it-12.eml, deem/it-1703978.eml, deem/it-1935107.eml, deem/it-2205416.eml, deem/it-5.eml, deem/it-6044523.eml";

    public $reBody = [
        'en' => [
            ['Use this when contacting the travel agency', 'would like to share this Itinerary with you'],
            ['Travel Itinerary', 'Trip name:'],
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'segmentsType'        => ['Flight from:', 'Hotel in', 'Car Rental in:'],
            'Agency:'             => 'Agency:',
            'endInfo'             => ['Rules and Restrictions', 'Trip Cost'],
            'Depart:'             => ['Depart:', 'Departure:'],
            'Arrive:'             => ['Arrive:', 'Arrival:'],
            'Reservation number:' => ['Reservation number:', 'Reservation Number:'],
            'Pick-up:'            => ['Pick-up:', 'Pick Up:'],
            'Drop-off:'           => ['Drop-off:', 'Drop Off:'],
        ],
    ];

    private $keywords = [
        'hertz' => [
            'Hertz',
        ],
        'avis' => [
            'Avis',
        ],
        'rentacar' => [
            'Enterprise',
        ],
    ];

    private $code;
    private static $providers = [
        'amextravel' => [   //should bee first (cause powered by deem)
            'from' => ['@OPENbusinesstravel.com', ''],
            'subj' => [
                '/Trip for .+? \- <Trip to .+?>/',
            ],
            'body' => [
                '//img[contains(@src,"openbusinesstravel") or contains(@alt,"OPEN Business Travel")]',
                'American Express',
            ],
            'keyword' => ['American Express'],
        ],
        'deem' => [
            'from' => ['@deem.com'],
            'subj' => [
                '/Trip for .+? \- <Trip to .+?>/',
            ],
            'body' => [
                '//a[contains(@href,".deem.com")]',
                '//a[contains(@href,"ehidirect.com")]',
                '//img[contains(@src,"Deem")]',
                'Deem, Inc',
                '@deem.com',
            ],
            'keyword' => ['Deem'],
        ],
    ];

    private $pax;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $NBSP = chr(194) . chr(160);
            $body = str_replace($NBSP, ' ', html_entity_decode($body));
        }

        if (preg_match("/<(.+?)>.+?<\/\\1>/s", $body) === 0) {
            $text = $body;
        } else {
            $text = text($this->http->Response['body']);
        }

        $this->parsePlainEmail($email, $text);

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        foreach ($this->reBody as $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
            ) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; // flights | hotels | rentals
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    private function parsePlainEmail(Email $email, $text)
    {
        // fix broke
        $text = str_replace(" USDÂ ", " USD ", $text);

        $info = $this->re("/(.+?){$this->opt($this->t('endInfo'))}/s", $text);

        if (empty($info)) {
            $this->logger->debug('other format');

            return false;
        }

        $this->pax = $this->re("/{$this->t('Traveler:')}[ ]*(.+)/", $info);
        $recLoc = $this->re("/{$this->t('Use this when contacting the travel agency')}[^\n]*\n[> ]*{$this->t('Record locator:')}[ ]*(.+)/",
            $info);
        $tripID = $this->re("/\n[> ]*{$this->t('Trip ID:')}[ ]*(.+)/", $info);
        $agency = $this->re("/\n[> ]*{$this->t('Trip ID:')}[ ]*.+\n[> ]*{$this->t('Agency:')}[ ]*(.+)/", $info);

        $email->ota()->confirmation($tripID, trim($this->t('Trip ID:'), ':') . ' ' . $agency);

        if (!empty($recLoc)) {
            $email->ota()->confirmation($recLoc, trim($this->t('Record locator:'), ':'), true);
        }

        $flights = $hotels = $rentals = [];
        $reservations = $this->splitter("/({$this->opt($this->t('segmentsType'))})/", $info);

        foreach ($reservations as $reservation) {
            if (preg_match("/^[ ]*{$this->opt($this->t('Agency:'))}.+/m", $reservation)
                && !preg_match("/^[ ]*{$this->opt($this->t('Depart:'))}/m", $reservation)
                && !preg_match("/^[ ]*{$this->opt($this->t('Check-In:'))}/m", $reservation)
                && !preg_match("/^[ ]*{$this->opt($this->t('Pick-up:'))}/m", $reservation)
            ) {
                continue;
            }

            if (preg_match("/^{$this->opt($this->t('Flight from:'))}/", $reservation)) {
                $segments = $this->splitter("/((?:\n\n|^)(?:.*\n){2,4}\n*[ ]*{$this->opt($this->t('Depart:'))})/",
                    "CtrlStr\n\n\n" . $reservation);

                if (empty($segments)) {
                    $this->logger->alert('check format flights');
                    $email->add()->flight(); // for broke result;

                    continue;
                }
                $flights = array_merge($flights, $segments);
            } elseif (preg_match("/^{$this->opt($this->t('Hotel in'))}/", $reservation)) {
                $hotels[] = $reservation;
            } elseif (preg_match("/^{$this->opt($this->t('Car Rental in:'))}/", $reservation)) {
                $rentals[] = $reservation;
            }
        }

        if (!empty($flights)) {
            $this->parseFlights($email, $flights);
        }

        if (!empty($hotels)) {
            $this->parseHotels($email, $hotels);
        }

        if (!empty($rentals)) {
            $this->parseRentals($email, $rentals);
        }

        if (null !== ($r = $this->getFlightIfOne($email))) {
            $sum = $this->re("/{$this->t('Total Transportation')}[ ]*(.+)/", $text);
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->total($sum['total'])
                ->currency($sum['currency']);
        }

        $sum = $this->re("/{$this->t('Estimated total cost for this traveler')}[:\s]*(.+)/", $text);
        $sum = $this->getTotalCurrency($sum);
        $email->price()
            ->total($sum['total'])
            ->currency($sum['currency']);

        return true;
    }

    private function getFlightIfOne(Email $email): ?Flight
    {
        $flights = [];

        foreach ($email->getItineraries() as $i => $it) {
            if ($it->getType() === 'flight') {
                $flights[] = $i;
            }
        }

        if (count($flights) == 1) {
            /** @var Flight $r */
            $r = $email->getItineraries()[array_shift($flights)];

            return $r;
        }

        return null;
    }

    private function parseFlights(Email $email, array $flights)
    {
        $airs = [];

        foreach ($flights as $flight) {
            $rl = $this->re("/{$this->opt($this->t('Reservation number:'))}[ ]*([A-Z\d]{5,6})\n/", $flight);

            if (empty($rl)) {
                $airs[CONFNO_UNKNOWN][] = $flight;
            } else {
                $airs[$rl][] = $flight;
            }
        }

        foreach ($airs as $rl => $segments) {
            $r = $email->add()->flight();

            if ($rl === CONFNO_UNKNOWN) {
                $r->general()->noConfirmation();
            } else {
                $r->general()->confirmation($rl);
            }
            $r->general()->traveller($this->pax, true);

            foreach ($segments as $segment) {
                $segment = preg_replace("/\n[> ]+/", "\n", $segment);

                $s = $r->addSegment();
                $s->extra()
                    ->status($this->re("/{$this->t('Status:')}[ ]*(.+)/", $segment))
                    ->aircraft($this->re("/{$this->t('Plane type:')}[ ]*(.+)/", $segment))
                    ->miles($this->re("/{$this->t('Distance:')}[ ]*(.+)/", $segment), false, true)
                    ->meal($this->re("/{$this->t('Meal Service:')}[ ]*(.+)/", $segment), false, true)
                    ->duration($this->re("/{$this->t('Estimated Time:')}[ ]*([\d+hm ]+)/", $segment))
                    ->cabin($this->re("/{$this->t('Class:')}[ ]*(.+)/", $segment));

                if (preg_match("/{$this->opt($this->t('Non-stop'))}/", $segment)) {
                    $s->extra()->stops(0);
                }

                $seat = $this->re("/{$this->t('Seat:')}[^\n]*(\d+[A-z])\n/i", $segment);

                if (!empty($seat)) {
                    $s->extra()->seat($seat);
                }

                $node = $this->re("/^[ ]*([^\n]+?\d+)\s*$/m", $segment);

                if (preg_match("/.*\b([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\#?(\d+)$/", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                } elseif (preg_match("/^(.+)\s+(\d+)$/", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                if (preg_match("/{$this->opt($this->t('Depart:'))}\s*(.+?)\n[ ]*{$this->opt($this->t('Arrive:'))}\s*(.+?)(?:{$this->t('Departure terminal:')}|{$this->t('Arrival terminal:')}|{$this->opt($this->t('Non-stop'))}|{$this->opt($this->t('Flight time:'))})/si",
                    $segment, $m)) {
                    $depInfo = array_values(array_filter(array_map("trim", explode("\n", $m[1]))));
                    $arrInfo = array_values(array_filter(array_map("trim", explode("\n", $m[2]))));

                    if (count($depInfo) === 1 && count($arrInfo) === 1) {
                        $date = $this->normalizeDate($this->re("/(.+)\s*\n[ ]*{$this->opt($this->t('Depart:'))}/",
                            $segment));

                        if (preg_match("/^(\d+:\d+(?:\s*[ap]m)?)\s+(.+)\s+\(([A-Z]{3})\)$/i", $depInfo[0], $m)) {
                            $s->departure()
                                ->date(strtotime($m[1], $date))
                                ->name($m[2])
                                ->code($m[3]);
                        }

                        if (preg_match("/^(\d+:\d+(?:\s*[ap]m)?)\s+(?:\(\s*([\+\-]\s*\d+)\s+days?\s*\))?\s*(.+)\s+\(([A-Z]{3})\)$/i",
                            $arrInfo[0], $m)) {
                            $s->arrival()
                                ->date(strtotime($m[1], $date))
                                ->name($m[3])
                                ->code($m[4]);

                            if (isset($m[2]) && !empty($m[2])) {
                                $s->arrival()->name(strtotime($m[2] . ' days', $s->getArrDate()));
                            }
                        }
                    } elseif (count($depInfo) === 2 && count($arrInfo) === 2) {
                        $date = $this->normalizeDate($depInfo[1]);

                        if (preg_match("/^(.+)\s+\(([A-Z]{3})\)$/i", $depInfo[0], $m)) {
                            $s->departure()
                                ->date($date)
                                ->name($m[1])
                                ->code($m[2]);
                        }
                        $date = $this->normalizeDate($arrInfo[1]);

                        if (preg_match("/^(.+)\s+\(([A-Z]{3})\)$/i", $arrInfo[0], $m)) {
                            $s->arrival()
                                ->date($date)
                                ->name($m[1])
                                ->code($m[2]);
                        }
                    }
                }

                $s->departure()
                    ->terminal(trim(str_ireplace('terminal', '',
                        $this->re("/{$this->t('Departure terminal:')}[ ]*(.+)/i", $segment))), true);

                $s->arrival()
                    ->terminal(trim(str_ireplace('terminal', '',
                        $this->re("/{$this->t('Arrival terminal:')}[ ]*(.+)/i", $segment))), true);

                $operator = trim($this->re("/{$this->t('Operated by')}[ ]+(.+)/", $segment), "() ");
                $operator = $this->re("/(.+?)(?:\bDBA\b|$)/", $operator);

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }
            }
        }
    }

    private function parseHotels(Email $email, array $hotels)
    {
        foreach ($hotels as $hotel) {
            $hotel = preg_replace("/\n[> ]+/", "\n", $hotel);

            $r = $email->add()->hotel();

            if (preg_match("/Membership/", $hotel)) {
                return;
            }

            if (preg_match("/^[ ]*({$this->opt($this->t('Reservation number:'))})[ ]*([-\dA-Z]{5,})[ ]*(?:\(|$)/m", $hotel, $m)) {
                $r->general()->confirmation($m[2], rtrim($m[1], ': '));
            }

            $r->general()
                ->status($this->re("/{$this->t('Status:')}[ ]*(.+)/", $hotel))
                ->traveller($this->pax, true);

            if (preg_match("/{$this->t('Hotel in')}[ ]+(.+)\s+(.+)/", $hotel, $m)) {
                $r->hotel()
                    ->address($m[1])
                    ->name($m[2]);
            }

            $r->hotel()
                ->phone($this->re("/{$this->opt($this->t('Phone:'))}[ ]*(.+)/", $hotel))
                ->fax($this->re("/{$this->opt($this->t('Fax:'))}[ ]*(.+)/", $hotel));

            $r->booked()
                ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('Check-In:'))}[ ]*(.+)/", $hotel)))
                ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('Check-Out:'))}[ ]*(.+)/", $hotel)))
                ->guests($this->re("/{$this->opt($this->t('Number of Guests:'))}[ ]*(\d+)/", $hotel))
                ->rooms($this->re("/{$this->opt($this->t('Number of Rooms:'))}[ ]*(\d+)/", $hotel));

            $room = $r->addRoom();
            $room
                ->setType($this->re("/{$this->opt($this->t('Room Type:'))}[ ]*(.+)/", $hotel))
                ->setDescription($this->re("/{$this->opt($this->t('Room Details:'))}[ ]*(.+)\s+(?:{$this->t('Number of Guests:')})/",
                    $hotel))
                ->setRate($this->re("/{$this->opt($this->t('Rate Details:'))}[ ]*(.+)/", $hotel))
                ->setRateType($this->re("/{$this->opt($this->t('Rate Description:'))}[ ]*(.+)\s+(?:{$this->t('Cancellation Policy:')}|{$this->t('Hotel Special Request:')})/",
                    $hotel));

            $sum = $this->re("/{$this->opt($this->t('Taxes:'))}[ ]*(.+)/", $hotel);
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->tax($sum['total'])
                ->currency($sum['currency']);

            $cancellation = $this->re("/{$this->t('Cancellation Policy:')}\s*(.+)\s+{$this->t('Hotel Special Request:')}/s",
                $hotel);

            if (!empty($cancellation)) {
                $r->general()->cancellation($cancellation);
            }

            $this->detectDeadLine($r);

            $sum = $this->re("/{$this->opt($this->t('Total Cost:'))}[ ]*(.+)/", $hotel);
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->total($sum['total'])
                ->currency($sum['currency']);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancel (?<priorAmount>\d{1,3}) (?<priorCurrency>days?|hours?) prior to arrival to avoid penalty\./i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['priorAmount'] . ' ' . $m['priorCurrency']);

            return;
        }

        return;
    }

    private function parseRentals(Email $email, array $rentals)
    {
        foreach ($rentals as $rental) {
            $rental = preg_replace("/\n[> ]+/", "\n", $rental);

            $r = $email->add()->rental();

            if (preg_match("/^[ ]*({$this->opt($this->t('Reservation number:'))})[ ]*([-\dA-Z]{5,})[ ]*(?:\(|$)/m", $rental, $m)) {
                $r->general()->confirmation($m[2], rtrim($m[1], ': '));
            }

            $r->general()
                ->status($this->re("/{$this->t('Status:')}[ ]*(.+)/", $rental))
                ->traveller($this->pax, true);

            if (preg_match("/{$this->t('Car Rental in:')}[ ]+(.+)\s+(.+)/", $rental, $m)) {
//                $region = $m[1];
                $m[2] = trim($m[2]);
                $r->extra()->company($m[2]);

                if (!empty($code = $this->getProviderByKeyword($m[2]))) {
                    $r->program()->code($code);
                }

                if (preg_match("/{$this->t('Membership:')}\s*{$m[2]}[ \-]*(\w[\w\-]+)/i", $rental, $v)) {
                    $r->program()
                        ->account($v[1], preg_match("/XXXX\w+/", $v[1]) > 0);
                }
            }

            $regExp = "/"
                . "{$this->opt($this->t('Pick-up:'))}[ ]*(.+)\n([\s\S]+?)\s+{$this->opt($this->t('Hours of operation:'))}[ ]*(.+)\s+"
                . "{$this->opt($this->t('Drop-off:'))}[ ]*(.+)\n([\s\S]+?)\s+{$this->opt($this->t('Hours of operation:'))}[ ]*(.+)\s+"
                . "(.+)"
                . "/";

            if (preg_match($regExp, $rental, $m)) {
                if (preg_match("/{$this->t('Address:')}\s*([\s\S]+)\s+{$this->t('Phone:')}\s+([\d\-\+\(\) ]+)$/", $m[2],
                    $v)) {
                    $r->pickup()
                        ->location($v[1])
                        ->phone($v[2]);
                } else {
                    $r->pickup()->location($m[2]);
                }
                $r->pickup()
                    ->date($this->normalizeDate($m[1]))
                    ->openingHours($m[3]);

                if (preg_match("/{$this->t('Address:')}\s*([\s\S]+)\s+{$this->t('Phone:')}\s+([\d\-\+\(\) ]+)$/", $m[5],
                    $v)) {
                    $r->dropoff()
                        ->location($v[1])
                        ->phone($v[2]);
                } else {
                    $r->dropoff()->location($m[5]);
                }
                $r->dropoff()
                    ->date($this->normalizeDate($m[4]))
                    ->openingHours($m[6]);
                $r->car()->type($m[7]);
            }

            $sum = $this->re("/{$this->opt($this->t('Approximate price including taxes:'))}[ ]*(.+)/", $rental);
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->total($sum['total'])
                ->currency($sum['currency']);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Tue 17 Dec, 2019 at 9:00 AM
            '/^\s*\w+,?\s+(\d+)\s+(\w+)\,\s+(\d{4})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$/ui',
            //Mon Dec 16, 2019 at 9:42 AM
            '/^\s*\w+,?\s+(\w+)\s+(\d+)\,\s+(\d{4})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$/ui',
            //Tuesday, December 31, 2019
            '/^\s*\w+,\s+(\w+)\s+(\d+)\,\s+(\d{4})\s*$/iu',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
            '$2 $1 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['segmentsType'], $words['Agency:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['segmentsType'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Agency:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "₹", "USD $"], ["EUR", "GBP", "INR", "USD"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['total' => $tot, 'currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
