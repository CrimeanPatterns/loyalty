<?php

namespace AwardWallet\Engine\carnival\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourCruise extends \TAccountChecker
{
    public $mailFiles = "carnival/it-697444380.eml, carnival/it-698999795.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => 'BOOKING #',
            'nameStart'  => ['Thanks for booking your'],
            'nameEnd'    => ['itinerary'],
            'total'      => ['Total', 'TOTAL'],
            'baseFare'   => ['Cruise Fare', 'Cruise Rate'],
        ],
    ];

    private $subjects = [
        'en' => ['Travel Advisor Copy:'],
    ];

    private $cruises = [];

    private $xpath = [
        'segments' => "descendant::*/tr[1][count(*)=5 and count(*[normalize-space()='' and descendant::img])=5]/following-sibling::tr[normalize-space()]",
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]carnivalcruiselineemail\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('subject', $headers) && stripos($headers['subject'], 'Your Carnival Cruise Booking') !== false) {
            return true;
        }

        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".carnivalcruiselineemail.com/") or contains(@href,"view.carnivalcruiselineemail.com") or contains(@href,"click.carnivalcruiselineemail.com")]')->length === 0
            && $this->http->XPath->query('//text()[normalize-space()="Carnival Cruise Line:"]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
        $email->setType('YourCruise' . ucfirst($this->lang));

        $cruiseRoots = $this->http->XPath->query("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::*[ following-sibling::*[{$this->xpath['segments']}] ][1]/..");

        if ($cruiseRoots->length > 1) {
            foreach ($cruiseRoots as $cRoot) {
                $this->parseCruise($email, $cRoot);
            }
        } else {
            $this->parseCruise($email);
        }

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

    private function parseCruise(Email $email, ?\DOMNode $cRoot = null): void
    {
        $patterns = [
            'date'          => '(?:\b[[:alpha:]]+\/\d{1,2}\/\d{4}\b|\b\d{1,2}\/[[:alpha:]]+\/\d{4}\b)', // Apr/7/2025    |    7/Apr/2025
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-,.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang    |    Boudreau, Alvin
            'ffNumber'      => '[-A-Z\d]{5,40}', // 9018692752
        ];

        $cruise = $email->add()->cruise();

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNumber'))}]", $cRoot);

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{4,30})(?:\s*\||$)/", $confirmation, $m)) {
            $cruise->general()->confirmation($m[2], $m[1]);
        }

        $description = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('nameStart'))}]/ancestor::tr[1]", $cRoot, true, "/^{$this->opt($this->t('nameStart'))}\s+(.{2,40}?)\s+{$this->opt($this->t('nameEnd'))}/");
        $room = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Room #'))}]", $cRoot, true, "/^{$this->opt($this->t('Room #'))}[:#\s]*(.+)$/");
        $cruise->details()->description($description, false, true)->room($room, false, true);

        /*
            segments
        */

        $segments = $this->findSegments($cRoot);

        foreach ($segments as $root) {
            $port = $this->http->FindSingleNode("*[3]", $root);
            $timeArr = $this->http->FindSingleNode("*[4]", $root, true, "/^{$patterns['time']}/");
            $timeDep = $this->http->FindSingleNode("*[5]", $root, true, "/^{$patterns['time']}/");

            if (empty($timeArr) && empty($timeDep)
                && (preg_match("/(?:{$this->opt($this->t('Day At Sea'))}|{$this->opt($this->t('Cross International Dateline'))})/i", $port) || preg_match("/^Cruise\b/i", $port))
            ) {
                continue;
            }

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("*[2]", $root, true, "/^{$patterns['date']}$/u")));

            $s = $cruise->addSegment();

            $s->setName($port);

            if ($date && $timeArr) {
                $s->setAshore(strtotime($timeArr, $date));
            }

            if ($date && $timeDep) {
                $s->setAboard(strtotime($timeDep, $date));
            }
        }

        /*
            travellers
        */

        $travellers = $accounts = [];

        $travellersRows = $this->http->XPath->query("descendant::*/tr[1][count(*)=3 and count(*[normalize-space()='' and descendant::img])=3]/following-sibling::tr[normalize-space() and count(*)=4]", $cRoot);

        foreach ($travellersRows as $tRow) {
            $travellerName = $this->http->FindSingleNode("*[1]", $tRow, true, "/^{$patterns['travellerName']}$/u");

            if ($travellerName) {
                $travellerName = preg_replace("/^([^,]+?)(?:\s*,\s*)+([^,]+)$/", '$2 $1', $travellerName);
            }

            $travellers[] = $travellerName;

            $account = null;
            $accountTexts = $this->http->FindNodes("*[position()>1][normalize-space()='' and descendant::img][1]/following-sibling::*[1]/descendant::text()[normalize-space()]", $tRow);

            foreach ($accountTexts as $accText) {
                if (preg_match("/^{$patterns['ffNumber']}$/", $accText) && preg_match("/\d/", $accText)) {
                    $account = $accText;

                    break;
                }
            }

            if ($account === null) {
                // it-698999795.eml
                $vifpNumberVal = $this->http->FindSingleNode("*[2]", $tRow);

                if (preg_match("/^{$patterns['ffNumber']}$/", $vifpNumberVal) && preg_match("/\d/", $vifpNumberVal)) {
                    $account = $vifpNumberVal;
                }
            }

            if ($account !== null && !in_array($account, $accounts)) {
                $cruise->program()->account($account, false, $travellerName);
                $accounts[] = $account;
            }
        }

        if (count($travellers) > 0) {
            $cruise->general()->travellers(array_unique($travellers), true);
        }

        /*
            price
        */

        $xpathTotalPrice = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('total'))}]";
        $totalPrice = $this->http->FindSingleNode("descendant::tr[{$xpathTotalPrice}]/*[normalize-space()][2]", $cRoot, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})?$/u', $totalPrice, $matches)) {
            // $2,446.84 CAD
            if (empty($matches['currencyCode'])) {
                $matches['currencyCode'] = '';
            }

            $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $cruise->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $chargesRows = $this->http->XPath->query("descendant::tr[{$xpathTotalPrice}]/following-sibling::tr[normalize-space()]", $cRoot);
            $chargesStarted = false;

            foreach ($chargesRows as $chaRow) {
                $chargeName = $this->http->FindSingleNode("*[1]", $chaRow, true, '/^(.+?)[\s:：]*$/u');
                $chargeAmount = $this->http->FindSingleNode("*[2]", $chaRow, false, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match("/^(?:{$this->opt($this->t('Bonus! Onboard Credit'))}|{$this->opt($this->t('Payments & Credits'))}|{$this->opt($this->t('Payment Schedule'))})$/i", $chargeName)) {
                    break;
                }

                if ($chargeAmount === null) {
                    continue;
                }

                if (preg_match("/^{$this->opt($this->t('Cruise Charges'))}$/i", $chargeName)) {
                    $chargesStarted = true;

                    continue;
                }

                if ($chargesStarted && preg_match("/^{$this->opt($this->t('baseFare'))}$/i", $chargeName)) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $chargeAmount, $m)) {
                        $cruise->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                    }

                    continue;
                }

                if ($chargesStarted) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $chargeAmount, $m)) {
                        $cruise->price()->fee($chargeName, PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }

        /*
            filtering duplicates
        */

        $it = serialize($cruise->toArray());

        if (in_array($it, $this->cruises)) {
            $email->removeItinerary($cruise);
        } else {
            $this->cruises[] = $it;
        }
    }

    private function findSegments(?\DOMNode $root = null): \DOMNodeList
    {
        return $this->http->XPath->query($this->xpath['segments'], $root);
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Apr/7/2025
            '/^([[:alpha:]]+)\/(\d{1,2})\/(\d{4})$/u',
            // 7/Apr/2025
            '/^(\d{1,2})\/([[:alpha:]]+)\/(\d{4})$/u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $text);
    }
}
