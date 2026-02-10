<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class ActivityReservation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-48181022.eml, expedia/it-330513732-es.eml, expedia/it-363433766.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'Itinerary#'                   => ['Itinerario no.'],
            'Where to meet'                => ['Punto de reunión'],
            'your activity reservation is' => 'reservación de tu actividad está',
            'Supplier reference'           => 'No. de referencia del proveedor:',
            'Reserved for:'                => 'Reservación para:',
            // 'Traveler Details' => '',
            'travellers'                   => 'persona',
            // 'Total' => '',
            // 'Expedia Rewards Points' => '',
            // 'used' => '',
        ],
        'en' => [
            'Itinerary#'                   => ['Itinerary#', 'Itinerary #'],
            'Where to meet'                => ['Where to meet'],
            'your activity reservation is' => ['your activity reservation is', 'Your activity reservation is'],
            'Supplier reference'           => ['Supplier reference', 'Supplier Reference'],
            'Traveler Details'             => ['Traveler Details', 'Traveller Details', 'Traveller details'],
            'travellers'                   => ['travellers', 'travelers', 'traveller', 'traveler'],
        ],
    ];

    private $detectors = [
        'es' => ['Ver detalles de la actividad'],
        'en' => ['Your activity is booked', 'View activity details'],
    ];

    private $dateRelative = 0;

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@](?:expediamail|expedia)\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Confirmación de viaje con Expedia -') !== false
            || stripos($headers['subject'], 'Expedia travel confirmation -') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".expediamail.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Expedia customer support") or contains(normalize-space(),"Expedia, Inc. All rights reserved") or contains(normalize-space(),"Contact Expedia for further") or contains(.,"@expediamail.com")]')->length === 0
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

        $this->dateRelative = strtotime($parser->getDate());

        $this->parseEvent($email);
        $email->setType('ActivityReservation' . ucfirst($this->lang));

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

    private function parseEvent(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
        ];

        $e = $email->add()->event();
        $e->setEventType(Event::TYPE_MEETING);

        $xpathNo = "//tr[not(.//tr) and {$this->starts($this->t('Itinerary#'))}]"; // it-48181022.eml
        $xpathNoV2 = "//text()[{$this->eq($this->t('Itinerary#'))}]"; // it-363433766.eml

        $status = $this->http->FindSingleNode($xpathNo . "/preceding-sibling::tr[{$this->contains($this->t('your activity reservation is'))}]", null, true, $pattern = "/{$this->opt($this->t('your activity reservation is'))}\s*([\w\s]{3,})[.!?]$/u")
            ?? $this->http->FindSingleNode($xpathNoV2 . "/preceding::text()[normalize-space()][1][{$this->contains($this->t('your activity reservation is'))}]", null, true, $pattern)
        ;

        if ($status) {
            $e->general()->status($status);
        }

        $itineraryNo = $this->http->FindSingleNode($xpathNo)
            ?? implode("\n", $this->http->FindNodes($xpathNoV2 . "/ancestor::p[1]/descendant::text()[normalize-space()]")) // always last!
        ;

        if (preg_match("/^({$this->opt($this->t('Itinerary#'))})\s*([-A-Z\d]{5,})(?:\n|$)/", $itineraryNo, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $eventName = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Supplier reference'))}]/preceding-sibling::tr[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//*[ count(*)=2 and *[1][normalize-space()='' and descendant::img] and *[2][normalize-space()] and preceding::*[{$this->eq($this->t('Traveler Details'))}] and following::text()[{$this->eq($this->t('Supplier reference'))} or {$this->eq($this->t('Where to meet'))}] ]/*[2]")
        ;

        $datesRow = $this->http->FindSingleNode($xpathNo . "/following-sibling::tr[normalize-space()][1]")
            ?? $this->http->FindSingleNode($xpathNoV2 . "/following::text()[normalize-space()][2][contains(.,'-')]", null, true, "/^.{6,}[ ]+-[ ]+.{6,}$/")
        ;
        $dates = preg_split('/\s+-\s+/', $datesRow);

        if ($this->dateRelative && !empty($dates[0])) {
            $dates[0] = $this->normalizeDate($dates[0]);

            if (preg_match('/\d{4}$/', $dates[0])) {
                $dateStart = strtotime($dates[0]);
            } else {
                $dateStart = EmailDateHelper::parseDateRelative($dates[0], $this->dateRelative);
            }

            if (preg_match("/^.{2,}?:\s*({$patterns['time']})(?:[ ]*,.{2,})?$/", $eventName, $m)) {
                // The Lion King On Broadway: 07:00 PM, Rear Orchestra
                $e->booked()->start(strtotime($m[1], $dateStart));
            } else {
                $e->booked()->start($dateStart);
            }
        }

        if ($this->dateRelative && !empty($dates[1])) {
            $dates[1] = $this->normalizeDate($dates[1]);

            if (preg_match('/\d{4}$/', $dates[1])) {
                $dateEnd = strtotime($dates[1]);
            } else {
                $dateEnd = EmailDateHelper::parseDateRelative($dates[1], $this->dateRelative);
            }

            if (!empty($e->getStartDate()) && $dateEnd && $e->getStartDate() >= $dateEnd && date('H:i', $dateEnd) === '00:00') {
                $dateEnd = strtotime('23:59', $dateEnd);
            }

            $e->booked()->end($dateEnd);
        }

        $traveller = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Reserved for:'))}]", null, true, "/{$this->opt($this->t('Reserved for:'))}\s*({$patterns['travellerName']})$/u");
        $travelersCount = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Reserved for:'))}]/following-sibling::tr[normalize-space()][position()<4][{$this->contains($this->t('travellers'))}]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('travellers'))}/iu");

        if (empty($traveller) || empty($travelersCount)) {
            $travellerDetails = $this->http->FindSingleNode("//p[{$this->eq($this->t('Traveler Details'))}]/following::p[normalize-space()][1]");

            if (preg_match("/^({$patterns['travellerName']})(?:[ ]*,[ ]*[^,]+)?[ ]*,[ ]*(\d{1,3})[ ]*(?i){$this->opt($this->t('travellers'))}/u", $travellerDetails, $m)) {
                // Miho Goto, 4 vouchers, 4 travelers    |    Miho Goto, 4 travelers
                $traveller = $m[1];
                $travelersCount = $m[2];
            }
        }

        $e->general()->traveller($traveller, true);
        $e->booked()->guests($travelersCount);

        $address = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Where to meet'))}]/following-sibling::tr[normalize-space()][1][not(descendant-or-self::*[{$xpathBold}])]")
            ?? $this->http->FindSingleNode("//div[{$this->eq($this->t('Where to meet'))}]/following-sibling::div[normalize-space()][1][not(descendant-or-self::*[{$xpathBold}])]")
        ;
        $e->place()->name($eventName)->address($address);

        $totalPrice = $this->http->FindSingleNode("//tr/*[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/following-sibling::*[normalize-space()]")
            ?? $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/")
        ;

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $199.60    |    CA $170.68
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $e->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $pointsUsed = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Expedia Rewards Points'))} and {$this->contains($this->t('used'))}]", null, true, "/^(\d[,.\'\d ]*\s*{$this->opt($this->t('Expedia Rewards Points'))})\s+{$this->opt($this->t('used'))}/");
        $e->price()->spentAwards($pointsUsed, false, true);

        $e->general()->noConfirmation();
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
            if (!is_string($lang) || empty($phrases['Itinerary#']) || empty($phrases['Where to meet'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Itinerary#'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Where to meet'])}]")->length > 0
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]{2,})[,.\s]+(\d{1,2})$/u', $text, $m)) {
            // Dec 25    |    Wed, Dec 25
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})[,.\s]+([[:alpha:]]{2,})$/u', $text, $m)) {
            // 25 Dec    |    Wed, 25 Dec
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{4})$/u', $text, $m)) {
            // 13 May 2023    |    Wed, 13 May 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{2})$/u', $text, $m)) {
            // 22 abr 23    |    Wed, 22 abr 23
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]{3,})[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Sep 29, 2023    |    Wed, Sep 29, 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]{3,})[,.\s]+(\d{1,2})[,.\s]+(\d{2})$/u', $text, $m)) {
            // Sep 29, 23    |    Wed, Sep 29, 23
            $month = $m[1];
            $day = $m[2];
            $year = '20' . $m[3];
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'CAD' => ['CA$', 'CA $'],
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
}
