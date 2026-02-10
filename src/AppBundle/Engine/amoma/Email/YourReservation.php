<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "amoma/it-35605760.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Arrival date:'   => ['Arrival date:'],
            'Departure date:' => ['Departure date:'],
        ],
    ];

    private $subjects = [
        'en' => ['Your reservation number'],
    ];

    private $detectors = [
        'en' => ['According to your request', 'will be modified as follows'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'AMOMA Customer Support Team') !== false
            || stripos($from, '@amoma.com') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,"www.amoma.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for being a part of AMOMA") or contains(normalize-space(),"Best regards, AMOMA") or contains(.,"@amoma.com") or contains(.,"www.amoma.com")]')->length === 0
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

        $this->parseHotel($email);
        $email->setType('YourReservation' . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $allParagraphs = $this->http->FindNodes("//p[ count(preceding-sibling::p[normalize-space()])>3 or count(following-sibling::p[normalize-space()])>3 ]");
        $text = implode("\n", $allParagraphs);

        $h = $email->add()->hotel();

        if (preg_match("/^[> ]*Dear ([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[,.!]+$/m", $text, $m)) {
            $h->addTraveller($m[1]);
        }

        if (preg_match("/\b(reservation number) (\d{5,}) in the (.{3,}) from the/", $text, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
            $h->hotel()
                ->name($m[3])
                ->noAddress()
            ;
        }

        if (preg_match("/^[> ]*Arrival date:\s*(.{6,})/m", $text, $m)) {
            $h->booked()->checkIn2($m[1]);
        }

        if (preg_match("/^[> ]*Departure date:\s*(.{6,})/m", $text, $m)) {
            $h->booked()->checkOut2($m[1]);
        }

        if (preg_match("/^[> ]*Quantity and type of rooms:\s*(.{2,})/m", $text, $m)) {
            $room = $h->addRoom();
            $room->setDescription($m[1]);
        }

        if (preg_match("/^[> ]*New total:\s*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})\b/m", $text, $m)) {
            // 8,953.00 ILS
            $h->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency'])
            ;
        }

        if (preg_match("/^[> ]*New cancellation\/modification policy:\s+((?:^[> ]*.+$\s*){1,5})^[> ]*Please keep in mind that/m", $text, $m)) {
            $m[1] = preg_replace('/([^,.!?;])$/m', '$1;', trim($m[1]));
            $h->general()->cancellation(preg_replace('/\n+/', ' ', $m[1]));
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
            if (!is_string($lang) || empty($phrases['Arrival date:']) || empty($phrases['Departure date:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Arrival date:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Departure date:'])}]")->length > 0
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
