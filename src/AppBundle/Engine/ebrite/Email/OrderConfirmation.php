<?php

namespace AwardWallet\Engine\ebrite\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "ebrite/it-54233270.eml, ebrite/it-54766889.eml, ebrite/it-62198769.eml, ebrite/it-67231770.eml, ebrite/it-92895761-notParsed.eml, ebrite/it-93021836.eml, ebrite/it-93075578.eml, ebrite/it-93443655.eml";

    public $reSubject = [
        // en
        'Order Confirmation for',
        'Your Tickets for',
        'Registration Confirmation for',
        //it
        'I tuoi biglietti per',
        'Conferma della registrazione per',
        // es
        'Tus entradas para el evento',
    ];

    public $lang = '';
    public static $dictionary = [
        "it" => [
            'Order Summary' => 'Riepilogo ordine',
            'Order #'       => 'Ordine #',
            'Order total:'  => ['Totale ordine:', 'Order total:'],
            'Ticket'        => ['biglietto', 'biglietti', 'registrazione', 'registrazioni'],
            'free'          => 'Gratuito',
            'from'          => 'dalle',
            'to'            => 'alle',
            'at'            => 'alle',
            // 'ends at'       => '',
            '(View on map)' => ['Visualizza sulla mappa', '(View on map)'],
            //            'Location' => '',
            //            'Online Event' => '',
            'View event details' => 'Visualizza dettagli evento',
        ],
        "es" => [
            'Order Summary' => 'Resumen del pedido',
            'Order #'       => 'Pedido #',
            'Order total:'  => 'Total de pedido:',
            'Ticket'        => 'entrada',
            'free'          => 'Gratis',
            'from'          => 'desde las',
            'to'            => 'hasta las',
            'at'            => 'a las',
            'ends at'       => 'finaliza a las',
            '(View on map)' => 'Ver en el mapa',
            //            'Location' => '',
            //            'Online Event' => '',
            'View event details' => 'Ver los detalles del evento',
        ],
        "en" => [
            'Order Summary' => 'Order Summary',
            'Order #'       => ['Order #', 'Reihenfolge #'],
            'Ticket'        => ['Ticket', 'Registrations', 'Registration', 'Tickets'],
            '(View on map)' => ['(View on map)', 'View on map'],
        ],
    ];

    private $detectors = [
        'it' => ['Hai domande su questo evento?'],
        'en' => ['Questions about this event?'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $email->setType('OrderConfirmation' . ucfirst($this->lang));

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if ($this->isJunk() === true) {
            $email->setIsJunk(true);

            return $email;
        }

        $event = $email->add()->event();

        $event->setEventType(Event::TYPE_EVENT);

        $orderTotal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order total:'))}]", null, true,
            "/{$this->opt($this->t('Order total:'))}\s*(.+)/i");

        if (preg_match("/^\s*({$this->opt($this->t('free'))})/i", $orderTotal)) {
            $event->price()->total(0);
        } elseif (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $orderTotal, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $orderTotal, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $orderTotal, $m)
        ) {
            $m['currency'] = $this->normalizeCurrency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);
            $event->price()
                ->total($m['amount'])
                ->currency($m['currency']);
        }

        $event->general()
            ->travellers(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Order Summary'))}]/following::text()[starts-with(normalize-space(), '#')]/ancestor::tr[1]/following-sibling::tr/descendant::table[1]/descendant::tr[count(td[normalize-space()]) = 3]/descendant::td[normalize-space()][1]")), true)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order Summary'))}]/following::text()[starts-with(normalize-space(), '#')][1]", null, true, "/#(\d+)/"));

        $eventName = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('(View on map)'))}])[1]/preceding::img[not(contains(@src, '-icon'))][1]/preceding::text()[normalize-space()][1]/ancestor::h2");

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//h2[following::text()[{$this->eq($this->t('(View on map)'))}]]");
        }

        if ($eventName && preg_match("/^[^{}]*\{[^{}]*\}[^{}]*$/", $eventName)) {
            $eventName = str_replace(['{', '}'], ['[', ']'], $eventName);
        }

        if (!empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('(View on map)'))}])[1]"))) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('(View on map)'))}]/preceding::tr[normalize-space()][2]");
            $address = implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('(View on map)'))}]/preceding::tr[normalize-space()][1]//text()[normalize-space()]"));
        } else {
            $name = $this->http->FindSingleNode("//img[contains(@src, 'locPin-icon@2x.png')]/ancestor::td[1]/following-sibling::td[1]/descendant::tr[not(.//tr)][normalize-space()][1]");
            $address = implode(', ', $this->http->FindNodes("//img[contains(@src, 'locPin-icon@2x.png')]/ancestor::td[1]/following-sibling::td[1]/descendant::tr[not(.//tr)][normalize-space()][2]//text()[normalize-space()]"));
        }

        if ($name && preg_match("/^[^{}]*\{[^{}]*\}[^{}]*$/", $name)) {
            $name = str_replace(['{', '}'], ['[', ']'], $name);
        }

        if ($address == 'Online') {
            $email->removeItinerary($event);
            $email->setIsJunk(true);
        }
        $event->place()
            ->name($eventName)
            ->address($name . ', ' . $address);

        $event->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('Order total:'))}]/preceding::td[{$this->contains($this->t('Ticket'))}][1]", null, true, "/(\d+)/"));

        $dateText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order total:'))}]/following::text()[normalize-space()][1]");
        $detectedDates = false;

        // Thursday, July 23, 2020 from 5:00 PM to 7:00 PM (PDT)
        $pattern = "#\w+[,]\s*(?<months>\w+)\s(?<day>\d{1,2})[,]\s*(?<year>\d{4})\s*{$this->opt($this->t('from'))}\s*(?<timeStart>[\d\:]+(?:\s*(?:AM|PM))?)\s*{$this->opt($this->t('to'))}\s*(?<timeEnd>[\d\:]+(?:\s*(?:AM|PM))?)#ui";
        // Monday, 20 July 2020 from 10:30 to 11:00 (BST)
        // Mittwoch, 27. September 2023 from 11:00 to 19:00 (MEZ)
        // Sunday, 15 October 2023 from 1:00 p.m. to 1:45 p.m. (ET)
        // Thursday, 14 December 2023 from 9.00 to 17.00 (CET)
        $pattern2 = "#\w+[,\s]+(?<day>\d{1,2})[.]?\s+(?:de\s+)?(?<months>\w+)\s+(?:de\s+)?(?<year>\d{4})\s*{$this->opt($this->t('from'))}\s*(?<timeStart>\d{1,2}[:.]\d{2}(?:\s*(?:AM|PM|a\.m\.|p\.m\.))?)\s*{$this->opt($this->t('to'))}\s*(?<timeEnd>\d{1,2}[:.]\d{2}(?:\s*(?:AM|PM|a\.m\.|p\.m\.))?)#ui";

        if (preg_match($pattern, $dateText, $m) || preg_match($pattern2, $dateText, $m)) {
            $event->booked()
                ->start($this->normalizeDate($m['day'] . ' ' . $m['months'] . ' ' . $m['year'] . ', ' . str_replace('.', '', preg_replace('/^\s*(\d{1,2})[.](\d{2}\b.*)$/', '$1:$2', $m['timeStart']))))
                ->end($this->normalizeDate($m['day'] . ' ' . $m['months'] . ' ' . $m['year'] . ', ' . str_replace('.', '', preg_replace('/^\s*(\d{1,2})[.](\d{2}\b.*)$/', '$1:$2', $m['timeEnd']))));
            $detectedDates = true;
        }

        //Friday, December 20, 2019 at 10:00 PM - Saturday, December 21, 2019 at 4:00 AM (EST)
        $pattern3 = "#\w+[,]\s*(?<monthStart>\w+)\s*(?<dayStart>\d+)[,]\s*(?<yearStart>\d{4})\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+(?:\s*(?:AM|PM))?)\s*[-]\s*\w+[,]\s*(?<monthEnd>\w+)\s*(?<dayEnd>\d+)[,]\s*(?<yearEnd>\d{4})\s*{$this->opt($this->t('at'))}\s*(?<timeEnd>[\d\:]+(?:\s*(?:AM|PM))?)#ui";
        $pattern6 = "#\w+[,\s]\s*(?<dayStart>\d+)\s+(?<monthStart>\w+)\s+(?<yearStart>\d{4})\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+(?:\s*(?:AM|PM))?)\s*[-]\s*\w+[,\s]\s*(?<dayEnd>\d+)\s+(?<monthEnd>\w+)\s+(?<yearEnd>\d{4})\s*{$this->opt($this->t('at'))}\s*(?<timeEnd>[\d\:]+(?:\s*(?:AM|PM))?)#ui";

        if ($detectedDates == false && (preg_match($pattern3, $dateText, $m) || preg_match($pattern6, $dateText, $m))) {
            $event->booked()
                ->start($this->normalizeDate($m['dayStart'] . ' ' . $m['monthStart'] . ' ' . $m['yearStart'] . ', ' . $m['timeStart']))
                ->end($this->normalizeDate($m['dayEnd'] . ' ' . $m['monthEnd'] . ' ' . $m['yearEnd'] . ', ' . $m['timeEnd']));
            $detectedDates = true;
        }
//        Saturday, September 28, 2019 at 3:00 PM (PDT)
        $pattern4 = "#\w+[,]\s*(?<monthStart>\w+)\s*(?<dayStart>\d+)[,]\s*(?<yearStart>\d{4})\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+(?:\s*(?:AM|PM))?)#ui";
        // Tuesday, 18 August 2020 at 10:15 (BST)
        // Venerdì 1 settembre 2023 alle 22:00 (CET)
        // Viernes, 13 de octubre de 2023 a las 18:00 (hora de verano de Estados Unidos (Detroit))
        $pattern5 = "#\w+[,\s]\s*(?<dayStart>\d+)[.]?\s+(de\s+)?(?<monthStart>\w+)\s+(de\s+)?(?<yearStart>\d{4})\s*{$this->opt($this->t('at'))}\s*(?<timeStart>[\d\:]+(?:\s*(?:AM|PM))?)#ui";

        if ($detectedDates == false && (preg_match($pattern4, $dateText, $m) || preg_match($pattern5, $dateText, $m))) {
            $event->booked()
                ->start($this->normalizeDate($m['dayStart'] . ' ' . $m['monthStart'] . ' ' . $m['yearStart'] . ', ' . $m['timeStart']))
                ->noEnd();
            $detectedDates = true;
        }

        // Friday, 29 September 2023; ends at 19:00 (United Kingdom Time)
        $pattern7 = "#\w+[,\s]\s*(?<dayStart>\d+)\s+(de\s+)?(?<monthStart>\w+)\s+(de\s+)?(?<yearStart>\d{4});\s*{$this->opt($this->t('ends at'))}\s*(?<timeEnd>[\d\:]+(?:\s*(?:AM|PM|a\.m\.|p\.m\.))?)\s*(?:\(|$)#ui";
        // Wednesday, October 11, 2023; ends at 10:00 PM (PT)
        $pattern8 = "#\w+[,\s]\s*(?<monthStart>\w+)\s+(?<dayStart>\d+)\s*,\s*(?<yearStart>\d{4});\s*{$this->opt($this->t('ends at'))}\s*(?<timeEnd>[\d\:]+(?:\s*(?:AM|PM|a\.m\.|p\.m\.))?)\s*(?:\(|$)#ui";

        if ($detectedDates == false && (preg_match($pattern7, $dateText, $m) || preg_match($pattern8, $dateText, $m))) {
            $event->booked()
                ->noStart()
                ->end($this->normalizeDate($m['dayStart'] . ' ' . $m['monthStart'] . ' ' . $m['yearStart'] . ', ' . str_replace('.', '', $m['timeEnd'])));
            $detectedDates = true;
        }
    }

    public function isJunk()
    {
        // Location: online
        if (
            $this->http->XPath->query("//text()[" . $this->eq($this->t("Location")) . "]/following::text()[" . $this->eq($this->t("Online Event")) . "]")->length > 0
            || $this->http->XPath->query("//text()[" . $this->starts($this->t("This event will be hosted online.")) . "]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, 'locPin-icon')]/ancestor::tr[1][" . $this->eq(['Zoom', 'Online on Zoom']) . "]")->length > 0
        ) {
            $this->logger->debug("Location Online");

            return true;
        }
        // Empty location
        if (
               $this->http->XPath->query("//img[contains(@src, 'locPin-icon')]")->length == 0
            && $this->http->XPath->query("//a[contains(@href, 'maps.google.com/maps')]")->length == 0
            && $this->http->XPath->query("//text()[" . $this->eq($this->t("Location")) . " or " . $this->eq($this->t("(View on map)")) . "]")->length == 0
            && $this->http->XPath->query("//img[contains(@src, 'date-icon')]/ancestor::tr[count(.//img) = 1]/following::tr[normalize-space()][1][" . $this->eq($this->t("View event details")) . "]")->length > 0
        ) {
            $this->logger->debug("Empty Location");

            return true;
        }

        /// noreply@order.eventbrite.com
        $xpath = "descendant::h2[1]/following::img/ancestor::*[normalize-space()][count(.//img) = 1][last()][following::text()[{$this->eq($this->t('View event details'))}]]";
        $parts = $this->http->XPath->query($xpath);

        if (($parts->length === 1 || $parts->length === 2)
            && empty(array_filter($this->http->FindNodes("descendant::h2[1]/following::node()[contains(., '20')][following::text()[{$this->eq($this->t('View event details'))}]]",
                null, "/\D20\d{2}\D/")))
            && empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Summary'))}]/following::text()[normalize-space()][1][{$this->starts($this->t('Order #'))}]",
                null, "/\D20\d{2}\D/"))
        ) {
            $src = $this->http->FindSingleNode(".//img/@src", $parts->item(0));
            $text = implode("\n", $this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $parts->item(0)));

            if (strpos($src, '/ticket-icon') === false
                || !preg_match("/^\s*\d+ x \S.+\n\s*{$this->opt($this->t('Order total:'))}.+\s*$/", $text)
            ) {
                return false;
            }
            $src = $this->http->FindSingleNode(".//img/@src", $parts->item(1));
            $text = implode("\n", $this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $parts->item(1)));

            if ($parts->length === 2
                && (strpos($src, '/locPin-icon') === false
                || !preg_match("/^\s*(.+\n){2}\s*{$this->opt($this->t('(View on map)'))}\s*$/", $text))
            ) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eventbrite\.com/i', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".eventbrite.com/") or contains(@href,"www.eventbrite.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Eventbrite. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeCurrency(?string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP'   => ['£'],
            'EUR'   => ['€'],
            'USD'   => ['US$'],
            'CAD'   => ['CA$'],
            'HKD'   => ['HK$'],
            '$'     => ['$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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
            if (!is_string($lang) || empty($phrases['Order Summary']) || empty($phrases['Order #'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Order Summary'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Order #'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug($str);
        $in = [
            "#^(\d+\s+\w+\s+\d{4}\,\s+[\d\:]+)$#ui", //23 ottobre 2020, 09:30
        ];
        $out = [
            "$1",
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
