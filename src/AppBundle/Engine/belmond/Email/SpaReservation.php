<?php

namespace AwardWallet\Engine\belmond\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class SpaReservation extends \TAccountChecker
{
    public $mailFiles = "belmond/it-137888985.eml, belmond/it-365091204.eml, belmond/it-65294516.eml, belmond/it-99459539.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation #:', 'Confirmation # :', 'confirmation number for your upcoming spa booking is', 'confirmation number'],
        ],
    ];

    private $subjects = [
        'en' => ['Itinerary for Reservation', 'Booking Confirmation #', 'Booking Confirmation#'],
        'es' => ['Confirmación de Reserva#'],
    ];

    private $detectors = [
        'en' => ['Reservation Itinerary', 'Service:', 'Your Spa reservation details are noted below:', 'We are reminding you', 'The confirmation number for your upcoming spa booking is'],
        'es' => ['Servicio:'],
    ];
    private $providerCode = '';

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
        'phone'         => '[+(\d][-. \d)(]{5,}[\d)]',
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]\.?',
    ];

    // hard-code hotels & address
    private static $supportedHotels = [
        [
            'name'    => ['The Spa at Belmond Charleston Place'],
            'address' => ['205 Meeting Street | Charleston, South Carolina | 29401'],
        ],
        [
            'name'    => ['Four Seasons Hotel Baltimore Spa'],
            'address' => ['200 International Drive | Baltimore, Maryland | 21202'],
        ],
        [
            'name'    => ['The Ritz-Carlton Reynolds, Lake Oconee Spa'],
            'address' => ['1 Lake Oconee Trail | Greensboro, Georgia | 30642'],
        ],
        [
            'name'    => ['The Ritz-Carlton Lake Tahoe Highlands Spa'],
            'address' => ['13031 Ritz-Carlton Highlands Ct. | Truckee, California | 96161'],
        ],
        [
            'name'    => ['The Ritz-Carlton Spa, Grande Lakes Orlando'],
            'address' => ['4012 Central Florida Parkway | Orlando, Florida | 32837'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@belmond.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['from'], '@spa.fourseasons.com') === false
            && stripos($headers['subject'], 'The Spa at Belmond Charleston Place') === false
            && stripos($headers['subject'], 'The Spa at The Four Seasons Palm Beach') === false
            && stripos($headers['subject'], 'Four Seasons Hotel Baltimore Spa') === false
            && stripos($headers['subject'], 'The Spa at Four Seasons Resort') === false
            && stripos($headers['subject'], 'The Ritz-Carlton') === false
        ) {
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
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        // eventType1
        $xpath = "//tr[not(.//tr) and (count(*)=3 or count(*)=2) and *[1][contains(translate(., '0123456789', '##########'), '#:##')]]";
        $itineraries = $this->http->XPath->query($xpath);

        if ($itineraries->length > 0) {
            $address = implode(', ', $this->http->FindNodes('//*[translate(normalize-space(@id),"x_","")="divLocationName"]/descendant::text()[normalize-space()]'));

            $location = 'The Spa at Belmond Charleston Place 205 Meeting Street Charleston, South Carolina 29401';

            if (empty($address) && $this->http->XPath->query("//*[{$this->eq($location)}]")->length > 0) {
                $address = $location;
            }

            $reservationNo = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Reservation #'))}]");

            if (preg_match("/({$this->opt($this->t('Reservation #'))})[:\s]+([A-Z\d]{5,})$/", $reservationNo, $m)) {
                $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
            }

            $totalPrice = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Reservation Total'))}]", null, true, "/{$this->opt($this->t('Reservation Total'))}[:\s]+(.+)$/");

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
                // $290.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $email->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        if (empty($address)) {
            if ($this->providerCode === 'fseasons') {
                $lastLine = $this->http->FindSingleNode("(//text()[normalize-space()])[last()]/ancestor::tr[1]");

                if (preg_match("/^(?<address>.{3,}?)(?:\s*[\|,]\s*|\s+(?:Spa )?Tel[:\s]+)(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:\s*\||$)/", $lastLine, $m)) {
                    // 10100 Dream Tree Blvd | Lake Buena Vista, Florida | 32836 Spa Tel: 1(407) 313-6970 | Email: resortreservations.orlando@fourseasons.com | Web: www.fourseasons.com/Orlando/spa
                    $address = $m['address'];
                    $phone = $m['phone'];
                } elseif (preg_match("/^(?<address>.{3,}?)\s+\|\s+0\s*$/", $lastLine, $m)) {
                    // 10100 Dream Tree Blvd | Lake Buena Vista, Florida | 32836 Spa Tel: 1(407) 313-6970 | Email: resortreservations.orlando@fourseasons.com | Web: www.fourseasons.com/Orlando/spa
                    $address = $m['address'];
                }
            }
        }

        foreach ($itineraries as $root) {
            $this->parseEvent1($email, $root);
        }

        // eventType2
        if ($itineraries->length === 0) {
            $this->parseEvent2($email);
        }

        if (!empty($address)) {
            foreach ($email->getItineraries() as $e) {
                /** @var Event $e */
                if (isset($address) && empty($e->getAddress())) {
                    $e->place()->address($address);
                }

                if (isset($phone) && empty($e->getPhone())) {
                    $e->place()->phone($phone);
                }
            }
        }

        $email->setProviderCode($this->providerCode);
        $email->setType('SpaReservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2; // eventType1, eventType2
    }

    public static function getEmailProviders()
    {
        return ['belmond', 'fseasons', 'goldpassport', 'fairmont', 'auberge', 'shangrila', 'harrah', 'marriott', 'cosmohotels'];
    }

    private function parseEvent1(Email $email, \DOMNode $root): Event
    {
        // it-65294516.eml
        $e = $email->add()->event();
        $e->place()->type(Event::TYPE_EVENT);

        $traveller = $this->http->FindSingleNode("(preceding::tr[ count(*)=2 and *[1][normalize-space()] and *[2][{$this->starts($this->t('confNumber'))}] ]/preceding::tr[not(.//tr) and normalize-space()][not(contains(., ':'))][1])[last()]", $root, true, "/^{$this->patterns['travellerName']}$/u");
        $e->general()->traveller($this->normalizeTraveller($traveller));

        $hXpath = "preceding::tr[not(.//tr)and normalize-space() and count(*)=2 and *[1][not(contains(translate(., '0123456789', '##########'), '#:##'))]][1]";
        $date = $this->http->FindSingleNode($hXpath . '/*[1]', $root, true, '/^.{6,}$/');

        $confirmation = $this->http->FindSingleNode($hXpath . '/*[2]', $root);

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]+([A-Z\d]{5,})$/", $confirmation, $m)) {
            $e->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $time = $this->http->FindSingleNode('*[1]', $root, true, "/^{$this->patterns['time']}$/");

        if ($date && $time) {
            $e->booked()->start2($date . ' ' . $time);
        }

        $serviceText = implode(' ', $this->http->FindNodes('*[2]/descendant::text()[normalize-space()]', $root));

        if (preg_match("/^(.{2,}?)\s*{$this->opt($this->t('Duration'))}[:\s]+(\d+\s*min).*$/", $serviceText, $m)) {
            /*
                Couples Mother-To-Be Massage 50 Minutes
                Duration: 50 min
            */
            $e->place()->name($m[1]);

            if (!empty($e->getStartDate())) {
                $e->booked()->end(strtotime($m[2], $e->getStartDate()));
            }
        } elseif (preg_match("/^[^:]+$/", $serviceText)) {
            /*
                Couples Mother-To-Be Massage 50 Minutes
                Duration: 50 min
            */
            $e->place()->name($serviceText);

            if (!empty($e->getStartDate())) {
//                $e->booked()->end(strtotime($m[2], $e->getStartDate()));
                $e->booked()->noEnd();
            }
        }

//        $totalPrice = $this->http->FindSingleNode("ancestor::table[1]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Guest Total'))}[:\s]+(.+)$/");
        $totalPrice = $this->http->FindSingleNode('*[3]', $root);

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $145.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $e->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));
        }

        if (count($email->getItineraries()) > 1) {
            $keys = array_keys($email->getItineraries());
            /** @var Event $el */
            $el = $email->getItineraries()[$keys[count($keys) - 2]];

            if ($el) {
                if ($e->getStartDate() === $el->getStartDate() && $e->getTravellers() === $el->getTravellers()) {
                    $el->place()->name($el->getName() . ', ' . $e->getName());

                    if ($el->getPrice()) {
                        if ($e->getPrice()) {
                            $el->price()
                                ->total($el->getPrice()->getTotal() + $e->getPrice()->getTotal());
                        }
                    }
                    $email->removeItinerary($e);
                }
            }
        }

        return $e;
    }

    private function parseEvent2(Email $email): void
    {
        // it-65294517.eml

        $e = $email->add()->event();

        $e->place()->type(Event::TYPE_EVENT);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Thank you for choosing']/preceding::text()[normalize-space()][1]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\,$/");
        }

        if (!empty($traveller)) {
            $e->general()->traveller($this->normalizeTraveller($traveller));
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('confNumber'))}\s*([-A-Z\d]{5,})(?:\s*\.|\s+and\s+|\s+y los\s+|$)/u");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('confNumber'))}\s*([-A-Z\d]{5,})(?:\s*\.|\s+and\s+|\s+y los\s+|$)/u");
        }
        $e->general()->confirmation($confirmation);

        $service = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Service:'))}] ]/*[normalize-space()][2]");

        if (empty($service)) {
            $service = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your reservation details are noted below:')]/following::text()[normalize-space()][1]");
        }
        $e->place()->name($service);

        $date = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ]/*[normalize-space()][2]", null, true, '/^.{6,}$/');

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your reservation details are noted below:')]/following::text()[normalize-space()][2]", null, true, "/^(.+\d{4})/");
        }

        $time = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Time:'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['time']}$/");

        if (empty($time)) {
            $time = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your reservation details are noted below:')]/following::text()[normalize-space()][2]", null, true, "/\s([\d\:]+\s*A?P?M)$/");
        }

        if ($date && $time) {
            $e->booked()
                ->start($this->normalizeDate($date . ', ' . $time))
                ->noEnd();
        }

        $totalPrice = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cost:'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $ 145.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $e->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]");
        $e->general()->cancellation($cancellation, false, true);

        foreach (self::$supportedHotels as $hotel) {
            if ($this->http->XPath->query("//*[{$this->contains($hotel['name'])}]")->length > 0
                && ($addressValue = $this->http->FindSingleNode("//text()[{$this->starts($hotel['address'])}]"))
            ) {
                if (preg_match("/^(.{3,}?)[\s|]+({$this->patterns['phone']})$/", $addressValue, $m)) {
                    $e->place()
                        ->address($hotel['name'][0] . ', ' . $m[1])
                        ->phone($m[2]);
                } else {
                    $e->place()->address($hotel['name'][0] . ', ' . $addressValue);
                }
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if ($this->detectEmailFromProvider($headers['from']) === true
            || $this->http->XPath->query('//node()[contains(normalize-space(),"The Spa at Belmond Charleston Place")]')->length > 0
        ) {
            $this->providerCode = 'belmond';

            return true;
        }

        if (stripos($headers['from'], '@spa.fourseasons.com') !== false
            || $this->http->XPath->query('//node()[' . $this->contains(["Four Seasons Hotel", 'Four Seasons Resort']) . ']')->length > 0
        ) {
            $this->providerCode = 'fseasons';

            return true;
        }

        if (stripos($headers['from'], '@hyatt.com') !== false
            || $this->http->XPath->query('//node()[' . $this->contains(['Hyatt Ziva & Zilara', '@hyatt.com', 'Windflower Spa']) . ']')->length > 0
        ) {
            $this->providerCode = 'goldpassport';

            return true;
        }

        if (stripos($headers['from'], '@fairmont.com') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Fairmont Pacific Rim")] | //img[normalize-space(@alt)="Logo" and contains(@src,"Fairmont")]')->length > 0
        ) {
            $this->providerCode = 'fairmont';

            return true;
        }

        if (stripos($headers['from'], '@shangri-la.com') !== false
            /*|| $this->http->XPath->query('//*[contains(normalize-space(),"Fairmont Pacific Rim")] | //img[normalize-space(@alt)="Logo" and contains(@src,"Fairmont")]')->length > 0*/
        ) {
            $this->providerCode = 'auberge';

            return true;
        }

        if (stripos($headers['from'], '@aubergeresorts.com') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"The Spa at Shangri-La Hotel ")]')->length > 0
        ) {
            $this->providerCode = 'shangrila';

            return true;
        }

        if (stripos($headers['from'], '@caesars.com') !== false
            || $this->http->XPath->query('//*[' . $this->contains(['@caesars.com']) . ']')->length > 0
        ) {
            $this->providerCode = 'harrah';

            return true;
        }

        if (stripos($headers['from'], '@ritzcarlton.com') !== false
            || $this->http->XPath->query('//*[' . $this->contains(['The Ritz-Carlton']) . ']')->length > 0
        ) {
            $this->providerCode = 'marriott';

            return true;
        }

        if (stripos($headers['from'], '@cosmopolitanlasvegas.com') !== false
            || $this->http->XPath->query('//*[' . $this->contains(['@cosmopolitanlasvegas.com', 'The Cosmopolitan of Las Vegas']) . ']')->length > 0
        ) {
            $this->providerCode = 'cosmohotels';

            return true;
        }

        if (stripos($headers['from'], '@langhamhotels.com') !== false
            || $this->http->XPath->query('//*[' . $this->contains(['Langham']) . ']')->length > 0
        ) {
            $this->providerCode = 'cosmohotels';

            return true;
        }

        return false;
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0) {
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

    private function normalizeTraveller(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        return preg_replace("/^(?:Mrs|Mr|Ms)[.\s]+(.{2,})$/i", '$1', $name);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // martes, 8 de febrero de 2022, 6:00 PM
            "/^\s*[^\d\s]*,\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})[\s\,]+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui", //29 Nov 2018
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
