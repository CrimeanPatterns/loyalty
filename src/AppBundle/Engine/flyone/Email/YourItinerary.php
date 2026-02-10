<?php

namespace AwardWallet\Engine\flyone\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "flyone/it-702496278.eml, flyone/it-706571729-ru.eml";

    public $lang = '';

    public static $dictionary = [
        'ru' => [
            'confNumber'  => ['Код билета'],
            'First Name'  => 'Имя',
            'Last Name'   => 'Фамилия',
            'Route'       => 'Маршрут',
            'Seat'        => 'Место',
            'Total paid:' => 'Итого:',
            'feeHeaders'  => ['Багаж', 'Место'],
        ],
        'en' => [
            'confNumber' => ['Ticket code'],
            // 'First Name' => '',
            // 'Last Name' => '',
            // 'Route' => '',
            // 'Seat' => '',
            // 'Total paid:' => '',
            'feeHeaders' => ['Baggage', 'Seats'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]flyone\.eu$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true && strpos($headers['subject'], 'FLYONE') === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,"//flyone.eu/") or contains(@href,".flyone.eu/") or contains(@href,"www.flyone.eu") or contains(@href,"bookings.flyone.eu")]')->length === 0
            && $this->http->XPath->query('//tr[not(.//tr[normalize-space()]) and normalize-space()="www.flyone.eu"]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourItinerary' . ucfirst($this->lang));

        $xpathTime = 'contains(translate(.,"0123456789：","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';

        $patterns = [
            'date'          => '\b.{4,}?\b\d{4}\b', // Mon, 12 Aug 2024
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,7}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][position()<3][{$this->starts($this->t('Status'))}]", null, true, "/{$this->opt($this->t('Status'))}\s*[:]+\s*(.{2,})$/");

        if ($status) {
            $f->general()->status($status);
        }

        $segments = $this->http->XPath->query("//*[*[1][{$xpathTime}] and not(*[2][{$xpathTime}]) and *[3][{$xpathTime}] and not(*[4][{$xpathTime}])]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = implode("\n", $this->http->FindNodes("*[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[- ]*(?<number>\d+)$/m", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $departure = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern = "/^(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})\s+(?<airport>[\s\S]{2,})$/", $departure, $m)) {
                /*
                    Thu, 22 Aug 2024
                    06:20
                    Milan (MXP)
                */
                $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));

                if (preg_match("/^(?<city>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)(?:\n|\s*$)/", $m['airport'], $m2)) {
                    // Milan (MXP)
                    $s->departure()->name($m2['city'])->code($m2['code']);
                } else {
                    $s->departure()->name($m['airport']);
                }
            }

            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()->date(strtotime($m['time'], strtotime($m['date'])));

                if (preg_match("/^(?<city>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)(?:\n|\s*$)/", $m['airport'], $m2)) {
                    $s->arrival()->name($m2['city'])->code($m2['code']);
                } else {
                    $s->arrival()->name($m['airport']);
                }
            }

            /*
            $classOfService = implode("\n", $this->http->FindNodes("*[4]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?:STANDARD|LOYAL)$/i", $classOfService, $m)) {
                $s->extra()->cabin($m[0]);
            }
            */

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $routeVariants = [$s->getDepCode() . ' - ' . $s->getArrCode(), $s->getDepCode() . '-' . $s->getArrCode()];
                $seatRows = $this->http->XPath->query("//*[ *[4][{$this->eq($this->t('Route'))}] and *[7][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[ *[4]/descendant::node()[{$this->eq($routeVariants)}] ]");

                foreach ($seatRows as $seatRow) {
                    $nameParts = [];

                    $firstName = $this->http->FindSingleNode("*[2]", $seatRow, true, "/^{$patterns['travellerName']}$/u");

                    if ($firstName) {
                        $nameParts[] = $firstName;
                    }

                    $lastName = $this->http->FindSingleNode("*[3]", $seatRow, true, "/^{$patterns['travellerName']}$/u");

                    if ($lastName) {
                        $nameParts[] = $lastName;
                    }

                    $passengerName = count($nameParts) > 0 ? implode(' ', $nameParts) : null;
                    $seat = $this->http->FindSingleNode("*[7]", $seatRow, true, "/^\d+[A-Z]$/");

                    if ($seat) {
                        $s->extra()->seat($seat, false, false, $passengerName);
                    }
                }
            }
        }

        $passengersRows = $this->http->XPath->query("//*[ *[2][{$this->eq($this->t('First Name'))}] and *[3][{$this->eq($this->t('Last Name'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($passengersRows as $pRow) {
            $nameParts = [];

            $firstName = $this->http->FindSingleNode("*[2]", $pRow, true, "/^{$patterns['travellerName']}$/u");

            if ($firstName) {
                $nameParts[] = $firstName;
            }

            $lastName = $this->http->FindSingleNode("*[3]", $pRow, true, "/^{$patterns['travellerName']}$/u");

            if ($lastName) {
                $nameParts[] = $lastName;
            }

            if (count($nameParts) > 1) {
                $f->general()->traveller(implode(' ', $nameParts), true);
            } elseif (count($nameParts) === 1) {
                $f->general()->traveller(implode(' ', $nameParts), false);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Total paid:'))}]/following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=2]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 150.00 €
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Total paid:'))}]/preceding-sibling::tr[normalize-space()][count(*)=1 or count(*)=2]");
            $feeHeader = null;

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');

                if (!$feeName) {
                    continue;
                }

                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow, true);

                if ($feeCharge === '') {
                    $feeHeader = $feeName;

                    continue;
                }

                $feeCharge = preg_replace('/^(.*?\d.*?)\s*\(.*/', '$1', $feeCharge);

                if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $feeCharge, $m)) {
                    $f->price()->fee($feeHeader && preg_match("/^{$this->opt($this->t('feeHeaders'))}$/i", $feeHeader) > 0 ? ($feeHeader . ': ' . $feeName) : $feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->eq($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
}
