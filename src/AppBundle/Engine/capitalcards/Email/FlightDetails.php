<?php

namespace AwardWallet\Engine\capitalcards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parser hopper/YourReceiptAndItinerary (in favor of hopper/YourReceiptAndItinerary)

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "capitalcards/it-121402957.eml, capitalcards/it-121736634.eml, capitalcards/it-122376144.eml, capitalcards/it-150170594.eml, capitalcards/it-624817628.eml, capitalcards/it-656497860.eml, capitalcards/it-657026501.eml, capitalcards/it-692114976.eml";
    public $subjects = [
        'Your trip to', 'View your flight details for your', 'Your flight details have changed',
    ];

    public $lang = 'en';
    public $currentDate;

    public static $dictionary = [
        "en" => [
            'Redress #:'              => ['Redress #:', 'Known Traveler #:'],
            'btnText'                 => ['Manage Your Trip', 'View Trip'],
            'statusVariants'          => ['Changed', 'changed', 'CHANGED', 'Cancelled', 'CANCELLED', 'Canceled', 'CANCELED'],
            'cancelledPhrases'        => ['was cancelled', 'was canceled', 'has been canceled', 'canceled your booking'],
            'Capital One Travel'      => ['Capital One Travel', 'Capital One Travel:'],
            'Your confirmation codes' => ['Your confirmation codes', 'YOUR CONFIRMATION CODES'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@capitalonebooking.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Capital One Travel')]")->length > 0) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(),'Your confirmation codes') or contains(normalize-space(),'You should receive your refund') or starts-with(normalize-space(),'Your trip to') or contains(normalize-space(),'confirmation code:')]")->length > 0
                && $this->http->XPath->query("//img[contains(@src, 'segments')]/ancestor::table[normalize-space()][3]/descendant::tr[1][contains(normalize-space(), ' to ')]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Capital One Travel:')]/following::text()[normalize-space()][2][contains(normalize-space(), 'Airways') or contains(normalize-space(), 'Airlines')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for using Capital One Travel') or contains(normalize-space(), 'Thank you for your understanding and for using Capital One Travel') or contains(normalize-space(), 'Thanks for using Capital One Travel')]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Capital One')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Pack your bags for')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Check out your full receipt with flight and passenger details below')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]capitalonebooking.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $xpathHeadTable = "//tr[ following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()][1][ancestor::a and ({$this->eq($this->t('btnText'))} or ancestor::*[{$this->contains(['#0276B1', '#0276b1'], '@style')}])] ]/*[count(*[normalize-space()])=1]/*[normalize-space()][last()]";

        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Fare Details']/following::text()[normalize-space()='Base Fare:']/preceding::text()[normalize-space()][1]/ancestor::tr[count(.//text()[normalize-space()]) < 3]/descendant::text()[normalize-space()][1]");

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers, true);
        }

        if (count($travellers) === 0) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}\s*(.+)\,/");

            if (!empty($traveller)) {
                $f->general()
                    ->traveller($traveller, false);
            }
        }

        $status = $this->http->FindSingleNode($xpathHeadTable . "/descendant::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/i");

        if ($status) {
            $f->general()->status($status);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(),'Your flight details have changed')]")->length > 0) {
            $f->general()
                ->status('changed');
        }

        $cancelledTexts = array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'Your trip to')]", null, "/Your trip to .+ (?i){$this->opt($this->t('cancelledPhrases'))}[.:;!\s]*$/"));

        if (count($cancelledTexts) === 0) {
            $cancelledTexts = array_filter($this->http->FindNodes("//text()[contains(normalize-space(), 'Capital One Travel:')]/preceding::text()[normalize-space()][1]", null, "/({$this->opt($this->t('cancelledPhrases'))})[.:;!\s]*$/"));
        }

        if (count($cancelledTexts) > 0) {
            $f->general()->cancelled();
        }

        $confirmationTitles = $confirmationValues = [];
        $confNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Your confirmation codes'))} or {$this->eq($this->t('statusVariants'))}]/ancestor::table[1]/following::table[normalize-space()][1]/descendant::tr[normalize-space()][not(contains(normalize-space(),'Capital'))]/descendant::tr[normalize-space()]");

        foreach ($confNodes as $confRoot) {
            $confTitle = $this->http->FindSingleNode("descendant::td[1]", $confRoot, true, '/^(.+?)[\s:：]*$/u');
            $confValue = $this->http->FindSingleNode("descendant::td[2]", $confRoot, true, '/^[-A-z\d]{5,}$/');

            if (empty($confTitle) || empty($confValue)) {
                $confirmationTitles = $confirmationValues = [];

                break;
            }

            $dupKey = array_search($confValue, $confirmationValues);

            if ($dupKey !== false) {
                // filtering duplicates
                $confirmationTitles[$dupKey][] = $confTitle;

                continue;
            }

            $confirmationTitles[] = [$confTitle];
            $confirmationValues[] = $confValue;
        }

        foreach ($confirmationValues as $key => $confVal) {
            if (!empty($confVal)) {
                $f->general()->confirmation($confVal, implode('; ', array_unique($confirmationTitles[$key])));
            }
        }

        if ($confNodes->length === 0) {
            $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Here’s your Capital One Travel confirmation code:')]/following::text()[contains(normalize-space(), 'confirmation code:')][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confirmation code:'))}\s*([A-Z\d\-]+)/");

            if (!empty($conf)) {
                $f->general()
                    ->confirmation($conf);
            }
        }

        // this Total contains applied rewards

        // $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Total'] and not(preceding::tr[normalize-space()='Seat Selection']) ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");
        $currency = $this->normalizeCurrency(preg_replace("/^\s*(\D*)\d[\d,.]*(\D*?)\s*$/", '$1$2',
            $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Total'] and not(preceding::tr[normalize-space()='Seat Selection']) ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/")));

        $totalRows = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Total'] and not(preceding::tr[normalize-space()='Seat Selection']) ]/following-sibling::tr/*[normalize-space()][2]");
        $total = 0.0;
        // $currency = null;
        $spentAwards = [];

        foreach ($totalRows as $row) {
            if (preg_match("/^-?(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)/", $row, $matches)) {
                // $1,415.40
                $currencyV = $this->normalizeCurrency($matches['currency']);

                if ($currency !== $currencyV && $currencyV !== '$') {
                    $total = null;
                    $spentAwards = [];

                    break;
                }
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $total += PriceHelper::parse($matches['amount'], $currencyCode);
            } elseif (preg_match("/^\s*\d[\d,. ]*\s*miles?\s*$/u", $row, $matches)) {
                $spentAwards[] = $row;
            } else {
                $total = null;
                $spentAwards = [];

                break;
            }
        }

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards(implode(' + ', $spentAwards));
        }

        if (!empty($total)) {
            $f->price()
                ->currency($currency)
                ->total($total);
        }

        $ticketText = implode(',', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket #:')]", null, "/{$this->opt($this->t('Ticket #:'))}\s*(.+)/"));

        if (!empty($ticketText)) {
            $f->setTicketNumbers(explode(',', $ticketText), false);
        }

        // https://redmine.awardwallet.com/issues/19223#note-45 "known travellers number - это не номер акка, это какая-то система трэвелоисчесления"
        // $accounts = $this->http->FindNodes("//text()[{$this->starts($this->t('Redress #:'))}]", null, "/{$this->opt($this->t('Redress #:'))}\s*(.+)/");
        //
        // if (count($accounts) > 0) {
        //     $f->setAccountNumbers($accounts, false);
        // }

        $seatsByRoute = [];
        $seatRows = $this->http->XPath->query("//tr[normalize-space()='Seat Selection']/following-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][1][descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}]]/following-sibling::tr[normalize-space()] ][1]/tr[normalize-space()]");

        foreach ($seatRows as $sRow) {
            $sHeader = $this->http->FindSingleNode("self::tr[ descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}] and count(*[normalize-space()])=1 ]", $sRow);

            if (preg_match("/^(.+?)\s+{$this->opt($this->t('to'))}\s+(.+)$/", $sHeader, $m)) {
                $routeName = $m[1] . ' -> ' . $m[2];

                continue;
            }
            $sVal = $this->http->FindSingleNode("self::tr[ not(descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}]) ]/*[normalize-space()][1]", $sRow, true, "/^{$patterns['travellerName']}\s*:\s(\d+[A-Z])$/");

            if ($sVal && isset($routeName)) {
                $seatsByRoute[$routeName][] = $sVal;
            }
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'segments')]");

        if (count($nodes) === 0) {
            $nodes = $this->http->XPath->query("//text()[starts-with(translate(normalize-space(.),'0123456789','dddddddddd'),'dh')][not(contains(normalize-space(), 'stop') or contains(normalize-space(), 'layover'))]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->extra()
                ->cabin($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ' to ')][1]/following::text()[normalize-space()][1]", $root))
                ->duration($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root));

            $date = strtotime($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ' to ')][1]/following::text()[normalize-space()][2]", $root));

            if (empty($this->currentDate)) {
                $this->currentDate = $date;
            } elseif ($this->currentDate > $date) {
                $date = $this->currentDate;
            }

            $flight = $this->http->FindSingleNode("preceding::img[contains(@src, 'airline')][1]/following::text()[normalize-space()][1]", $root);

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode("preceding::img[2]/following::text()[normalize-space()][1]", $root);
            }

            if (preg_match('/^(?:(?<operator>.{2,}?)\s*-\s*)?(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\2)?\s*(?<number>\d+)$/', $flight, $m)) {
                // United - UA4804    |    United - UAUA 4804
                if (!empty($m['operator'])) {
                    $s->airline()->operator($m['operator']);
                }
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $depTime = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);
            $s->departure()
                ->code($this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root))
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][3]", $root))
                ->date(strtotime($depTime, $date));

            $arrTime = $this->http->FindSingleNode("./following::text()[normalize-space()][4]", $root);

            if (preg_match("/^\d+\:\d+/", $arrTime)) {
                $s->arrival()
                    ->code($this->http->FindSingleNode("./following::text()[normalize-space()][3]", $root))
                    ->name($this->http->FindSingleNode("./following::text()[normalize-space()][2]", $root))
                    ->date(strtotime($arrTime, $date));

                // seats (Examples: it-150170594.eml)
                $cityDep = $this->http->FindSingleNode("./preceding::text()[normalize-space()][3]", $root);
                $cityArr = $this->http->FindSingleNode("./following::text()[normalize-space()][2]", $root);

                if ($cityDep && $cityArr && !empty($seatsByRoute[$cityDep . ' -> ' . $cityArr])) {
                    $s->extra()->seats($seatsByRoute[$cityDep . ' -> ' . $cityArr]);
                }
            } else {
                $arrTime = $this->http->FindSingleNode("./following::text()[normalize-space()][3]", $root);
                $s->arrival()
                    ->code($this->http->FindSingleNode("./following::text()[normalize-space()][2]", $root))
                    ->name($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root))
                    ->date(strtotime($arrTime, $date));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Capital One Travel'))}]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'contact'))]");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Here’s your Capital One Travel confirmation code:')]", null, true, "/{$this->opt($this->t('Here’s your Capital One Travel confirmation code:'))}\s*([A-Z\d\-]+)/");
        }
        $email->ota()
            ->confirmation($conf);

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
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
}
