<?php

namespace AwardWallet\Engine\blane\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

//TODO: if blane reset ignoreTraxo then exclude mta-provider
class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "blane/it-113049766.eml, blane/it-150519579.eml, blane/it-155911952.eml, blane/it-156905248.eml, blane/it-157511867.eml, blane/it-20945262.eml, blane/it-25717908.eml, blane/it-32994509.eml, blane/it-35737650.eml, blane/it-444820959.eml, blane/it-702694082.eml";

    public $reBody = [
        'en'  => [
            ['Vehicle type', 'Number of passenger', 'Category:', 'Date and time'],
            ['Booking number', 'Quote number', 'Booking no'],
        ],
        'de'  => ['Entfernung', 'Buchungsnummer'],
        'fr'  => ['Type de véhicule', 'Type de véhicule'],
    ];
    public $reSubject = [
        'en'  => 'Booking confirmation (Booking number:', 'Booking updated (Booking number:', 'Your journey awaits',
        'de'  => 'Buchungsbestätigung (Buchungsnummer:',
        'fr'  => 'Mise à jour de votre réservation (Numéro de réservation :', 'Confirmation de réservation (Numéro de réservation :',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            //            'Dear '  => '',
            "confNo" => ["Booking number:", "Quote number:", 'Booking no:'],
            //            "Date and time:" => "",
            //            "From:" => "",
            //            "To:" => "",
            //            "Distance:" => "",
            "totalPrice" => ["Total:", "Price:"],
            "cost"       => "Price:",
            //            "Vehicle type:" => "",
            'Passenger:'  => ['Passenger:', 'Guest:'],
            'badServices' => ['By the hour', 'VIP Meet & Greet'],
        ],
        'de' => [
            //            'Dear '  => '',
            "confNo"              => "Buchungsnummer:",
            "Date and time:"      => ["Datum und Zeit:", "Datum und Uhrzeit:"],
            "From:"               => "Von:",
            "To:"                 => "Nach:",
            "Distance:"           => "Entfernung:",
            "totalPrice"          => "Preis:",
            // "cost" => "",
            "Vehicle type:"   => "Fahrzeugtyp:",
            'Passenger:'      => ['Fahrgast:'],
            // 'badServices' => [''],
        ],
        'fr' => [
            'Dear '               => 'Cher',
            "confNo"              => "Numéro de réservation :",
            "Date and time:"      => "Date et heure :",
            "From:"               => "De :",
            "To:"                 => "À :",
            "Distance:"           => "Distance:",
            "totalPrice"          => "Prix :",
            // "cost" => "",
            "Vehicle type:"   => "Type de véhicule :",
            'Passenger:'      => ['Passager:'],
            // 'badServices' => [''],
        ],
    ];
    public static $headersTA = [
        'rolzo' => [
            'from' => ['@rolzo.com'],
        ],
        'savenio' => [
            'from' => ['savenio.com.au'],
        ],
        'mta' => [
            'from' => ['mtatravel.com.au'],
        ],
        'blane' => [
            'from' => ['blacklane.com'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) !== true) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (null !== ($providerCode = $this->getOtaProvider($parser->getCleanFrom()))) {
            if (in_array($providerCode, ['mta'])) {
                $email->ota()->code($providerCode);
            } else {
                $email->setProviderCode($providerCode);
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email, $providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->unitDetectByBody()) {
            return true;
        }
        // many letters with information in html-attachments
        $htmls = $this->getHtmlAttachments($parser);

        foreach ($htmls as $html) {
            $this->http->SetEmailBody($html);

            if ($this->unitDetectByBody()) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headersTA as $arr) {
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
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach (self::$headersTA as $arr) {
                foreach ($arr['from'] as $f) {
                    if (stripos($headers['from'], $f) !== false) {
                        $flag = true;
                    }
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
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
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headersTA);
    }

    private function getOtaProvider($from): ?string
    {
        foreach (self::$headersTA as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return $code;
                }
            }
        }

        if ($this->http->XPath->query('//a[contains(@href,".rolzo.com/") or contains(@href,"//rolzo.com/") or contains(@href,"business.rolzo.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(.,"business.rolzo.com")]')->length > 0
        ) {
            // it-150519579.eml
            return 'rolzo';
        }

        return null;
    }

    private function parseEmail(Email $email, ?string $providerCode): void
    {
        // remove DEL tags
        $nodesToStip = $this->http->XPath->query('//tr[count(*[normalize-space()])=2]/*[normalize-space()][2]/descendant::del[normalize-space()]');

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        if ($this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('confNo'))}] ]")->length > 1
            && $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('From:'))}] ]")->length > 1
        ) {
            $xpathNode = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('confNo'))}] ]";
            $nodes = $this->http->XPath->query($xpathNode . "/ancestor::*[count(.{$xpathNode}) = 1][last()]");
        } else {
            $nodes = [null];
        }

        foreach ($nodes as $root) {
            $r = $email->add()->transfer();

            if ($this->http->XPath->query("//img[contains(@src, 'blacklane') or @alt='Blacklane'] | //text()[contains(.,'Blacklane') or contains(.,'blacklane')]")->length > 0) {
                $r->program()->code('blane');
            }

            $confirmationTitle = $this->http->FindSingleNode(".//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('confNo'))}] ]/*[normalize-space()][1]",
                $root, true, '/^(.+?)[\s:：]*$/u');
            $confirmations = explode(' ', $this->http->FindSingleNode(".//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('confNo'))}] ]/*[normalize-space()][2]", $root));

            foreach ($confirmations as $confirmation) {
                if (strlen($confirmation) > 4) {
                    $r->general()->confirmation($confirmation, $confirmationTitle);
                } else {
                    $r->general()->confirmation(null);
                }
            }

            $s = $r->addSegment();

            $xpathDate = "count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('Date and time:'))}]";
            $node = $this->nextTD($this->t('Date and time:'), $root)
                ?? $this->http->FindSingleNode(".//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('Additional hour:'))}] ]/following::tr[position() < 20][ {$xpathDate} and following::tr[{$xpathDate}] ]/*[normalize-space()][2]", $root);

            if (!empty($date = $this->normalizeDate($node))) {
                $s->departure()->date($date);
            }

            $departure = $this->nextTD($this->t('From:'), $root) ?? $this->nextTD($this->t('Airport:'), $root);
            $departure = preg_replace("/^Welcome\s+(.{3,})$/i", '$1', $departure); // remove garbage

            if (preg_match("#(.+)\s+\(([A-Z]{3})\),#", $departure, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->geoTip('europe')
                ;
            } else {
                $s->departure()->address($departure)->geoTip('europe');
            }
            $arrival = $this->nextTD($this->t('To:'), $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\),#", $arrival, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->geoTip('europe')
                ;
            } elseif ($arrival) {
                $s->arrival()->address($arrival)->geoTip('europe');
            }

            $distance = $this->nextTD($this->t('Distance:'), $root);
            $duration = $this->nextTD($this->t('Duration:'), $root);

            if (preg_match("/^(?<duration>.*?\d.*?)\s*\(\s*(?<distance>.*\d.*?)\s*\)$/", $duration, $m)) {
                // 2 hours (incl. 40 km)
                $duration = $m['duration'];

                if ($distance === null) {
                    $distance = $m['distance'];
                }
            }

            if (empty($arrival)) {
                $arrival = $this->http->FindSingleNode("//text()[normalize-space()='Special requirements:']/ancestor::tr[1]/descendant::td[2]", $root);
                // $this->logger->debug('Special requirements: ' . ($arrival ?? '-'));

                if (preg_match("/back\s*to\s*([A-Z]{3})\s*.+\s+(\d+\s*a?p?m)/", $arrival, $m)) {
                    // it-157511867.eml
                    $s->arrival()
                        ->code($m[1])
                        ->date(strtotime($m[2], $s->getDepDate()));
                }

                if (preg_match("/(?:Drop off at|We would like to visit) .{3,75} (?:and then|for a while then return to)\s+(.{3,75})$/i",
                        $arrival, $m)
                    || preg_match("/Final stop will be\s+(.{3,75}?)\s+for flight/i", $arrival, $m)
                    || preg_match("/(?:drop off at|drop off:)\s*(.{3,75}?)\s*(?:, wait|then wait\s)/i", $arrival, $m)
                    || preg_match("/drop off at\s*(.{3,75})$/i", $arrival, $m) // always last
                    || preg_match("/^(.{3,75}?)\s+Client may need to make a stop along the way/i", $arrival,
                        $m) // always last
                ) {
                    if (preg_match('/^\s*([A-Z]{3})\s*$/', $m[1], $m2)) {
                        $s->arrival()->code($m2[1]);
                    } else {
                        $s->arrival()->name($m[1]);
                    }
                    $s->arrival()->date(strtotime('+ ' . $duration, $s->getDepDate()));
                }
            }

            if (empty($s->getArrDate())) {
                $s->arrival()->noDate();
            }

            $adults = $this->nextTD($this->t('Number of passenger(s):'), $root);

            if (!empty($adults)) {
                $s->extra()
                    ->adults($adults);
            }

            if ($providerCode === 'rolzo'
                && preg_match("/(?:^|[,]\s*){$this->opt($this->t('badServices'))}(?:\s*,|$)/i",
                    $this->nextTD($this->t('Service:'), $root))
                && empty($s->getArrName()) && empty($s->getArrCode()) && empty($s->getArrAddress())
                && (!empty($s->getDepName()) || !empty($s->getDepCode()) || !empty($s->getDepAddress()))
            ) {
                // it-156905248.eml
                $email->removeItinerary($r);
                $email->setIsJunk(true);
            }

            $traveller = $this->nextTD($this->t('Passenger:'), $root);
            $traveller = preg_replace("/^\s*(M\.|Mme\.|Herr|Frau) /", '', $traveller);

            if ($traveller) {
                $r->general()->traveller($traveller, true);
            } else {
                $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNo'))}]/preceding::text()[{$this->starts($this->t('Dear '))}][1]",
                    $root, true, "/^\s*{$this->opt($this->t('Dear '))}\s*(.+),/");

                if ($name) {
                    $r->general()->traveller($name, false);
                }
            }

            $s->extra()->miles($distance, false, true)->duration($duration, false, true);

            $type = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('Vehicle type:'))}] ]/*[normalize-space()][2]/descendant::td[not(.//tr) and normalize-space()][1]", $root)
                ?? $this->nextTD($this->t('Vehicle type:'), $root);
            $s->extra()->type($type, false, true);

            $totalPrice = $this->nextTD($this->t('totalPrice'), $root);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[* ]*$/', $totalPrice, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)[* ]*$/', $totalPrice, $matches)
            ) {
                // $311.21    |    1,075.25 EUR
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'],
                    $currencyCode));

                $cost = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('cost'))}] and following-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($this->t('totalPrice'))}]] ]/*[normalize-space()][2]",
                    $root, true, "/^.*\d.*$/");

                if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'],
                            '/') . ')?[* ]*$/', $cost, $m)
                    ?? preg_match('/^(?:' . preg_quote($matches['currency'],
                            '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)[* ]*$/', $cost, $m)
                ) {
                    $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                }

                $discount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->starts($this->t('Discount'))}] ]/*[normalize-space()][2]",
                    $root, true, "/^.*\d.*$/");

                if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'],
                            '/') . ')?[* ]*$/', $discount, $m)
                    ?? preg_match('/^(?:' . preg_quote($matches['currency'],
                            '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)[* ]*$/', $discount, $m)
                ) {
                    $r->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }
    }

    private function nextTD($field, $root = null, $afterConfNo = true): ?string
    {
        $before = ".//";

        if ($afterConfNo == true) {
            $before = ".//tr/*[{$this->starts($this->t('confNo'))}]/following::";
        }

        foreach ((array) $field as $fName) {
            $fValue = $this->http->FindSingleNode($before . "tr[position() < 20][ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr) and {$this->eq($fName)}] ]/*[normalize-space()][2]", $root);

            if ($fValue !== null) {
                return $fValue;
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        //		$year = date('Y', $this->date);
        $in = [
            //20 Aug 2018 12:35 (12:35 PM)
            '#^(\d+)\s+(\w+)\s+(\d{4})\s+(\d+:\d+)\s+\(\d+.+$#u',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                $this->logger->warning("//*[{$this->contains($reBody[0])}]");
                $this->logger->warning("//*[{$this->contains($reBody[1])}]");

                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

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

    private function unitDetectByBody(): bool
    {
        if ($this->http->XPath->query("//img[contains(@src, 'blacklane') or @alt='Blacklane'] | //text()[contains(.,'Blacklane') or contains(.,'blacklane')]")->length > 0
            || $this->http->XPath->query('//a[contains(@href,".rolzo.com/") or contains(@href,"//rolzo.com/") or contains(@href,"business.rolzo.com")] | //*[contains(.,"business.rolzo.com")]')->length > 0
            || $this->http->XPath->query('//text()[contains(normalize-space(),"Blacklane GmbH")]')->length > 0
        ) {
            if ($this->assignLang()) {
                return true;
            }
        }

        return false;
    }

    private function getHtmlAttachments(\PlancakeEmailParser $parser, $length = 20000): array
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;
            }
        }

        return $result;
    }
}
