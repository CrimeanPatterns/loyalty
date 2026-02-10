<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class YourReceipt extends \TAccountChecker
{
    public $mailFiles = "airindia/it-40858403.eml, airindia/it-39982593.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Your Trip Confirmation' => ['Your Trip Confirmation'],
            'Description'            => ['Description'],
            'totalPrice'             => ['Total Price for All Passengers', 'Total Price forAll Passengers'],
        ],
    ];

    private $detectors = [
        'en' => ['Services Receipt', 'Thank you for booking'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airindia.in') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'Your receipt from AIAL') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.airindia.in/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Please contact Air India") or contains(normalize-space(),"Kind regards Air India") or contains(normalize-space(),"Thank you for booking with Air India") or contains(.,"@airindia.in") or contains(.,"www.airindia.in")]')->length === 0
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

        $this->parseFlight($email);
        $email->setType('YourReceipt' . ucfirst($this->lang));

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

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip Confirmation'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip Confirmation'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $passengers = [];
        $ticketNumbers = [];

        $passengerSections = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Description'))}] and *[2][{$this->eq($this->t('Payment Method'))}] ]");

        foreach ($passengerSections as $pSection) {
            $passenger = $this->http->FindSingleNode('preceding::text()[normalize-space()][3][ ancestor::*[contains(@class,"medium m-none")] ]', $pSection, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if ($passenger) {
                $passengers[] = $passenger;
            }

            $dateText = $this->http->FindSingleNode('preceding::text()[normalize-space()][2]', $pSection);
            $date = strtotime($dateText);

            // AI 158 (CPH-DEL)
            $pattern1 = "/(?:^| )(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[ ]*\((?<airportDep>[A-Z]{3})[ ]*-[ ]*(?<airportArr>[A-Z]{3})\)/";
            $flightsText = $this->http->FindSingleNode('preceding::text()[normalize-space()][1]', $pSection);

            if (!preg_match_all($pattern1, $flightsText, $flightMatches, PREG_SET_ORDER)) {
                $f->addSegment(); // for 100% error

                return;
            }

            foreach ($flightMatches as $m) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
                $s->departure()
                    ->day($date)
                    ->code($m['airportDep'])
                    ->noDate();
                $s->arrival()
                    ->code($m['airportArr'])
                    ->noDate();

                // Seat #04A (AI 543) 0988202483946
                $descriptionTexts = $this->http->FindNodes("ancestor::table[1]/descendant::tr[ *[3] ]/*[normalize-space()][1]", $pSection);

                foreach ($descriptionTexts as $description) {
                    if (preg_match("/{$this->opt($this->t('Seat'))}[ ]*#[ ]*(\d+[A-Z])[ ]*\({$m['name']}[ ]*{$m['number']}\)/", $description, $matches)) {
                        $s->extra()->seat($matches[1]);
                    }

                    if (preg_match('/\)[ ]*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})$/', $description, $matches)) {
                        $ticketNumbers[] = $matches[1];
                    }
                }
            }
        }

        if (count($passengers)) {
            $f->general()->travellers($passengers);
        }

        if (count($ticketNumbers)) {
            $f->setTicketNumbers(array_unique($ticketNumbers), false);
        }

        $payment = $this->http->FindSingleNode("//td[{$this->eq($this->t('totalPrice'))}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})\b/', $payment, $m)) {
            // 7.30 USD
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }

        $this->uniqueFlightSegments($f);
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
            if (!is_string($lang) || empty($phrases['Your Trip Confirmation']) || empty($phrases['Description'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Your Trip Confirmation'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Description'])}]")->length > 0
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
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function uniqueFlightSegments(Flight $f)
    {
        $segments = $f->getSegments();

        if (empty($segments)) {
            return;
        }

        foreach ($segments as $key => $s) {
            for ($i = $key - 1; $i >= 0; $i--) {
                if (empty($segments[$i])) {
                    continue;
                }

                $condition1 = $s->getNoFlightNumber() === $segments[$i]->getNoFlightNumber()
                    && $s->getFlightNumber() === $segments[$i]->getFlightNumber();
                $condition2 = $s->getNoDepCode() === $segments[$i]->getNoDepCode()
                    && $s->getDepCode() === $segments[$i]->getDepCode()
                    && $s->getNoArrCode() === $segments[$i]->getNoArrCode()
                    && $s->getArrCode() === $segments[$i]->getArrCode();
                $condition3 = $s->getNoDepDate() === $segments[$i]->getNoDepDate()
                    && $s->getDepDate() === $segments[$i]->getDepDate();

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($s->getSeats())) {
                        if (!empty($segments[$i]->getSeats())) {
                            $segments[$i]->setSeats(array_unique(
                                array_merge($segments[$i]->getSeats(), $s->getSeats())
                            ));
                        } else {
                            $segments[$i]->setSeats($s->getSeats());
                        }
                    }
                    $f->removeSegment($s);
                    unset($segments[$key]);

                    break;
                }
            }
        }
    }
}
