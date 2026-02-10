<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-100822772.eml, fseasons/it-101502400.eml, fseasons/it-101905896.eml, fseasons/it-113231769.eml, fseasons/it-139121219.eml, fseasons/it-377937715.eml, fseasons/it-387467393.eml, fseasons/it-512956201-fr.eml, fseasons/it-65294517.eml, fseasons/it-67625849.eml";
    public $subjects = [
        '/. Booking Confirmation#/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'fr' => [
            'dear'         => ['Cher(e)'],
            'confNumber'   => ['votre réservation est'],
            'service'      => ['Soin:', 'Soin :'],
            'date'         => ['Date:', 'Date :'],
            'time'         => ['Heure:', 'Heure :'],
            'phonePhrases' => ['veuillez nous contacter en appelant le'],
            'price'        => ['Tarif:', 'Tarif :'],
        ],
        'en' => [
            'dear'       => ['Dear', 'Greetings', 'Aloha', 'Hi'],
            'confNumber' => ['Confirmation Number:', 'Confirmation Number :', 'spa appointment is', 'spa booking is'],
            'service'    => ['Service:', 'Service :', 'Service/', 'Service /', 'Experience:', 'Experience :', 'Treatment:', 'Treatment :', 'Wellness experience:'],
            'date'       => ['Date:', 'Date :', 'Date/', 'Date /'],
            'time'       => ['Time:', 'Time :', 'Time/', 'Time /', 'Hour:'],
            // 'phonePhrases' => [''],
            'price'      => ['Price:', 'Price :', 'Cost:', 'Cost :', 'Cost/', 'Cost /'],
        ],
        'es' => [
            'dear'         => ['Hola'],
            'confNumber'   => ['El número de confirmación para su próxima reserva de spa es'],
            'service'      => ['Servicio:'],
            'date'         => ['Fecha:'],
            'time'         => ['Hora:'],
            //'phonePhrases' => [''],
            'price'        => ['Costo:'],
        ],
    ];

    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]spa\.fourseasons\.com$/i', $from) > 0
            || preg_match('/.*spa.*@fourseasons\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());
        $this->assignLang();

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’\/[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('dear'))}]", null, "/{$this->opt($this->t('dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        if (!$confirmation && preg_match("/{$this->opt($this->t('Booking Confirmation'))}[#\s]*([-A-Z\d]{5,})\s*(?:[,.:;!?]|$)/i", $parser->getSubject(), $m)) {
            $confirmation = $m[1];
        }

        $roots = $this->http->XPath->query("//tr/*[normalize-space()][1][not(.//tr) and {$this->starts($this->t('service'))}]");

        if ($roots->length === 0) {
            $roots = $this->http->XPath->query("//td[not(.//tr) and following-sibling::*[normalize-space()] and {$this->starts($this->t('service'))}]");
        }

        if ($roots->length === 0) {
            $roots = $this->http->XPath->query("//text()[{$this->starts($this->t('service'))}]/ancestor::tr[1]");
        }

        foreach ($roots as $root) {
            $e = $email->add()->event();

            if ($confirmation) {
                $e->general()
                    ->confirmation($confirmation);
            } else {
                $e->general()
                    ->noConfirmation();
            }

            if ($traveller !== null) {
                $e->general()->traveller(preg_replace('/^(?:Miss|Mrs|Mr|Ms|Mme|Mr\/Mrs\.\s*)[.\s]+(.{2,})$/i', '$1', $traveller));
            }

            $dateVal = $this->http->FindSingleNode("following::text()[{$this->contains($this->t('date'))}][1]/following::text()[normalize-space()][1]", $root)
            ?? $this->http->FindSingleNode("preceding::text()[{$this->contains($this->t('date'))}][1]/following::text()[normalize-space()][1]", $root);

            $date = strtotime($this->normalizeDate($dateVal));

            $time = $this->http->FindSingleNode("following::text()[{$this->contains($this->t('time'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^{$patterns['time']}/")
                ?? $this->http->FindSingleNode("preceding::text()[{$this->contains($this->t('time'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");

            $e->setName($this->http->FindSingleNode("following-sibling::*[normalize-space()][1]", $root))
                ->setEventType(\AwardWallet\Schema\Parser\Common\Event::TYPE_EVENT)
                ->setStartDate((!empty($date) && !empty($time)) ? strtotime($time, $date) : null);

            $duration = $this->http->FindSingleNode("following::text()[starts-with(normalize-space(),'Duration')][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($duration)) {
                $e->setEndDate(strtotime($duration, $e->getStartDate()));
            } else {
                $e->setNoEndDate(true);
            }

            $address = $this->http->FindSingleNode("following::text()[normalize-space()][contains(.,'|') or contains(.,'Tel')]/ancestor::tr[1]", $root, true, "/^(.+\|.+\|.+|.+,.+,.+|.+\sTel\s.+)$/")
                ?? $this->http->FindSingleNode("descendant::text()[normalize-space()][last()]/ancestor::td[contains(@style,'center')]", null, true, "/^.{10,150}\s+(?:[-\d\/]{6,}|\S+\S+)$/")
                ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'sommes ravis de vous accueillir prochainement au')]/following::text()[normalize-space()][1]", null, true, "/^.{3,70}?[.!?\s]*$/") // it-512956201-fr.eml
            ;

            $address = preg_replace("/PRIVACY POLICY \| TERMS & CONDITIONS © Jumeirah 2021/", "", $address);

            if (preg_match("/^(?<address>.{3,}?)(?:\s*[\|,]\s*|\s+Tel[:\s]+)(?<phone>{$patterns['phone']})(?:\s*\||$)/", $address, $m)) {
                // 4100 Wailea Alanui | Wailea, HI Tel +1 808 875 2229 | Email keaspareception@fairmont.com
                $e->setAddress($m['address'])->setPhone($m['phone']);
            } elseif (preg_match("/^(?<address>.{3,}?)\s+(?<phone>{$patterns['phone']})\s*$/", $address, $m)) {
                // 98 San Jacinto Boulevard , Austin , Texas 78701 5126858160
                $e->setAddress($m['address'])->setPhone($m['phone']);
            } elseif (preg_match("/^(?<address>.{3,}?)\s+(?:\S+@\S+)\s*$/", $address, $m)) {
                // 27 Barclay Street | New York, NY spa.nydowntown@fourseasons.com
                $e->setAddress($m['address']);
            } elseif (preg_match("/(?<address>.+\|\D+\|\s*\d+\s*)\|\s*(?<phone>\d{10,})\s*https\:/", $address, $m)) {
                // Jumeirah Beach Road, Umm Suqeim 3 | Dubai, | 74147 | 97143017365 https://www.jumeirah.com/en/rejuvenate/dubai/burj-al-arab-jumeirah/talise-spa
                $e->setAddress($m['address'])->setPhone($m['phone']);
            } else {
                $e->setAddress($address);
            }

            if (empty($e->getPhone())) {
                // it-512956201-fr.eml
                $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('phonePhrases'))}]", null, true, "/{$this->opt($this->t('phonePhrases'))}\s+({$patterns['phone']})/");
                $e->setPhone($phone, false, true);
            }

            $totalPrice = $this->http->FindSingleNode("./following::tr[ *[normalize-space()][2] ][position()<6][ *[normalize-space()][1][{$this->contains($this->t('price'))}] ]/*[normalize-space()][2]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+?)?[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
                // $ 165.00    |    165.00
                if (empty($matches['currency'])) {
                    $matches['currency'] = null;
                }
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $e->price()->currency($matches['currency'], false, true)
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return ['auberge', 'fairmont', 'fseasons', 'marriott', 'jumeirah', 'belmond', 'shangrila', 'aplus', 'rwvegas', 'langham'];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@aubergeresorts.com') !== false) {
            $this->providerCode = 'auberge';

            return true;
        }

        if (stripos($headers['from'], '@fairmont.com') !== false
            || $this->http->XPath->query('//text()[contains(normalize-space(),"Fairmont")]')->length > 0
        ) {
            $this->providerCode = 'fairmont';

            return true;
        }

        if (stripos($headers['from'], '@spa.fourseasons.com') !== false
            || stripos($headers['from'], '@fourseasons.com') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(),'Four Seasons')]")->length > 0
        ) {
            $this->providerCode = 'fseasons';

            return true;
        }

        if (stripos($headers['from'], 'spa@marriott.com') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(),'The Ritz-Carlton')]")->length > 0
        ) {
            $this->providerCode = 'marriott';

            return true;
        }

        if (stripos($headers['from'], '@jumeirah.com') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(),'Jumeirah Beach Road')]")->length > 0
        ) {
            $this->providerCode = 'jumeirah';

            return true;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Belmond')]")->length > 0) {
            $this->providerCode = 'belmond';

            return true;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Shangri-La')]")->length > 0) {
            $this->providerCode = 'belmond';

            return true;
        }

        if (stripos($headers['from'], '@banyantree.com') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(),'Banyan Tree Mayakoba')]")->length > 0
        ) {
            $this->providerCode = 'aplus';

            return true;
        }

        if (stripos($headers['from'], '@rwlasvegas.com') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(),'3000 Las Vegas Boulevard South')]")->length > 0
        ) {
            $this->providerCode = 'rwvegas';

            return true;
        }

        if (stripos($headers['from'], '@langhamhotels.com') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(),'Langham')]")->length > 0
        ) {
            $this->providerCode = 'aplus';

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
            if (!is_string($lang) || empty($phrases['service']) || empty($phrases['date']) || empty($phrases['time'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($phrases['service'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($phrases['date'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($phrases['time'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        $this->logger->debug($text);

        if (preg_match('/^[-[:alpha:]]+[,.\s]+([[:alpha:]]+)[.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Tuesday, July 06, 2021
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]+[,.\s]+(\d{1,2})[.\s]+([[:alpha:]]+)[.\s]+(\d{4})$/u', $text, $m)) {
            // dimanche 17 septembre 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\w+\,\s*(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})$/u', $text, $m)) {
            // martes, 8 de febrero de 2022
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
}
