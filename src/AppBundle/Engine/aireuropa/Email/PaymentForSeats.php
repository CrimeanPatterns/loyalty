<?php

namespace AwardWallet\Engine\aireuropa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class PaymentForSeats extends \TAccountChecker
{
    public $mailFiles = "aireuropa/it-31814156.eml, aireuropa/it-31592229.eml, aireuropa/it-32195999.eml, aireuropa/it-32291742.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Booking locator' => ['Booking locator'],
            'RESERVED SEATS'  => ['RESERVED SEATS'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation of Payment for seats'],
    ];

    private $detectors = [
        'en' => ['Confirmation of Payment for seats'],
    ];

    private $segmentsType = '';
    private $dateRelative = 0;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@air-europa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"www.aireuropa.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for flying Air Europa")]')->length === 0
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

        $this->dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);

        if (empty($this->dateRelative)) {
            $this->dateRelative = strtotime($parser->getDate());
        } // it-31592229.eml

        $this->parseFlight($email);
        $email->setType('PaymentForSeats' . $this->segmentsType . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking locator'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking locator'))}]/preceding::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:+\s*$/', '', $confirmationNumberTitle));

        $dates['outbound'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking locator'))}]/ancestor::td[1]/following-sibling::*[{$this->contains($this->t('OUTBOUND'))}]", null, true, "/(.{3,}?)\s*{$this->opt($this->t('OUTBOUND'))}/");
        $dates['inbound'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking locator'))}]/ancestor::td[1]/following-sibling::*[{$this->contains($this->t('INBOUND'))}]", null, true, "/(.{3,}?)\s*{$this->opt($this->t('INBOUND'))}/");
        $dates['departure'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking locator'))}]/ancestor::td[1]/following-sibling::*[{$this->contains($this->t('DEPARTURE'))}]", null, true, "/(.{3,}?)\s*{$this->opt($this->t('DEPARTURE'))}/");

        $travellers = [];
        $flights_temp = [];

        $this->segmentsType = '1';
        $segments = $this->http->XPath->query("//p[ not(.//p) and descendant::text()[normalize-space()][1][{$this->contains($this->t('Seat'))}] and descendant::text()[normalize-space()][position()>1 and contains(.,'-')] ]");

        if ($segments->length === 0) {
            $this->segmentsType = '2';
            $segments = $this->http->XPath->query("//p[ not(.//p) and {$this->contains($this->t('Seat'))} and preceding-sibling::*[normalize-space()][2][contains(.,'-')] ]");
        }

        // JFK (New York (JFK)) - MAD (Madrid) UX0092
        $patterns['route'] = '/^(?<depCode>[A-Z]{3})\s*\(\s*(?<depName>.*?)\s*\)\s+-\s+(?<arrCode>[A-Z]{3})\s*\(\s*(?<arrName>.*?)\s*\)\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/';

        foreach ($segments as $segment) {
            $travellers[] = $this->http->FindSingleNode("ancestor::table[1]/ancestor::tr[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]", $segment, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            $route = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()][position()>1]", $segment));

            if (preg_match($patterns['route'], $route, $m)
                || preg_match($patterns['route'], $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][2]", $segment), $m)
            ) {
                $seat = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Seat'))}][1]", $segment, true, "/^\s*{$this->opt($this->t('Seat'))}\s+(\d{1,5}[A-Z])\s*$/u");

                $flight = $m['airline'] . ' ' . $m['flightNumber'];

                if (!empty($flights_temp[$flight])) {
                    $flights_temp[$flight]->addSeat($seat);

                    continue;
                }

                $s = $f->addSegment();
                $flights_temp[$flight] = $s;

                $s->addSeat($seat);

                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;

                $course = $this->http->FindSingleNode("ancestor::tr[1]/preceding-sibling::*[normalize-space()][last()]", $segment);

                if (preg_match("/^{$this->opt($this->t('OUTBOUND'))}$/", $course)) {
                    $dateText = $dates['outbound'];
                } elseif (preg_match("/^{$this->opt($this->t('INBOUND'))}$/", $course)) {
                    $dateText = $dates['inbound'];
                } else {
                    $dateText = $dates['departure'];
                }

                if ($this->dateRelative && $dateText) {
                    $dateDep = EmailDateHelper::parseDateRelative($dateText, strtotime('-1 days', $this->dateRelative));
                    $s->departure()
                        ->day($dateDep)
                        ->noDate()
                    ;
                }

                $s->departure()
                    ->code($m['depCode'])
                    ->name($m['depName'])
                ;
                $s->arrival()
                    ->noDate()
                    ->code($m['arrCode'])
                    ->name($m['arrName'])
                ;
            }
        }

        $this->logger->debug('Found ' . count($flights_temp) . ' unique flight segment(s): ' . implode(', ', array_keys($flights_temp)));

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        $xpathFragmentPayment = "//text()[{$this->eq($this->t('Total amount'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]";

        $amount = $this->http->FindSingleNode($xpathFragmentPayment . '[1]', null, true, '/^\d[,.\'\d]*$/');
        $currency = $this->http->FindSingleNode($xpathFragmentPayment . '[2]', null, true, '/^[A-Z]{3}$/');

        if ($amount && $currency) {
            $f->price()
                ->total($this->normalizeAmount($amount))
                ->currency($currency)
            ;
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
            if (!is_string($lang) || empty($phrases['Booking locator']) || empty($phrases['RESERVED SEATS'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking locator'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['RESERVED SEATS'])}]")->length > 0
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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }
}
