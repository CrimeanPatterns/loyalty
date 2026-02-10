<?php

namespace AwardWallet\Engine\chinaeastern\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class AirTicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "chinaeastern/it-9094222.eml"; // +1 bcdtravel(html)[en]

    public $reFrom = ['flychinaeastern', '@ceair.com'];
    public $reBody = [
        'Call China Eastern Airlines',
        '請致電中國東方航空台灣呼叫中心客服專線',
        'China Eastern Airlines al numerol',
    ];
    public $reSubject = [
        'Air ticket issue confirmation',
        '中國東方航空電子機票行程單',
        'Conferma emissione biglietto aereo',
    ];
    private $lang = '';
    private $langDetectors = [
        'en' => ['ARR City'],
        'zh' => ['抵達城市'],
        'it' => ['Città di arrivo'],
    ];
    private static $dict = [
        'en' => [],
        'zh' => [
            'The order No. is' => '訂單號碼是',
            'Flight No.'       => '航班號碼',
            'Total Amount'     => '總金額',
            'Ticket fares:'    => '票價:',
            'Tax & Fees'       => '稅金與費用',
            'Tax & Fees:'      => '稅金與費用：',
            'Passenger name'   => '乘客姓名',
            'Passenger type'   => '乘客類型',
        ],
        'it' => [
            'The order No. is' => 'Il n. di ordine è',
            'Flight No.'       => 'N. volo',
            'Total Amount'     => 'Importo totale：',
            'Ticket fares:'    => 'Tariffe del biglietto:',
            'Tax & Fees'       => 'Tasse e spese',
            //'Tax & Fees:' => 'Tasse e spese',
            'Passenger name' => 'Nome di passeggeri',
            'Passenger type' => 'Tipo passeggero',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query("//node()[contains(.,'@ceair.com') or {$this->contains($this->reBody)} or contains(.,'sg.ceair.com')]")->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//sg.ceair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('The order No. is'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, '/^([A-Z\d]{5,})$/');
//        if ($tripNumber)
        $email->ota()->confirmation($tripNumber);
        $r->general()->noConfirmation();

        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight No.'))}]/ancestor::tr[1]/following-sibling::tr[ ./td[7] ]");

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $flight = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            }

            $s->departure()
                ->name($this->http->FindSingleNode('./td[3]', $segment))
                ->date(strtotime($this->http->FindSingleNode('./td[5]', $segment)))
                ->noCode();

            $s->arrival()
                ->name($this->http->FindSingleNode('./td[4]', $segment))
                ->date(strtotime($this->http->FindSingleNode('./td[6]', $segment)))
                ->noCode();

            $class = $this->http->FindSingleNode('./td[7]', $segment);
            // Economy Class T
            if (preg_match('/(.+)\s+([A-Z]{1,2})$/', $class, $matches)) {
                $s->extra()
                    ->cabin($matches[1])
                    ->bookingCode($matches[2]);
            } elseif ($class) {
                $s->extra()
                    ->cabin($class);
            }
        }

        $payment = $this->http->FindSingleNode("//td[{$this->starts($this->t('Total Amount'))}]");
        // Total Amount：811.70 SGD
        if (preg_match("/{$this->opt($this->t('Total Amount'))}[\s：]*([,.\d\s]+)\s*([A-Z]{3,})\b/", $payment, $matches)) {
            $r->price()
                ->total(PriceHelper::cost($matches[1]))
                ->currency($matches[2]);

            $reCurrency = preg_replace('/([.$*)|(\/])/', '\\\\$1', $matches[2]);

            $baseFare = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket fares:'))}]/ancestor::div[1]"); //['Ticket fares:','Ticket fares：']
            // Ticket fares:527SGD
            if (preg_match('/([,.\d\s]+)\s*' . $reCurrency . '$/', $baseFare, $m)) {
                $r->price()
                    ->cost(PriceHelper::cost($m[1]));
            }
            $taxFare = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tax & Fees:'))}]/ancestor::div[1]");
            // Tax & Fees:527SGD
            if (preg_match('/([,.\d\s]+)\s*' . $reCurrency . '$/', $taxFare, $m)) {
                $r->price()
                    ->tax(PriceHelper::cost($m[1]));
            } else {
                $feesRows = $this->http->XPath->query("//div[{$this->eq($this->t('Tax & Fees'))}]/following-sibling::div[normalize-space(.)!='']");

                foreach ($feesRows as $feesRow) {
                    // YQ：232.20SGD
                    if (preg_match("/^([^:：]+)[:：]([,.\d\s]+)\s*{$reCurrency}\s*$/u", $feesRow->nodeValue, $m)) {
                        $r->price()
                            ->fee(trim($m[1]), PriceHelper::cost($m[2]));
                    }
                }
            }
        }

        $ticketNumbers = [];
        $passengers = [];
        $accountNumbers = [];
        $passengerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger name'))}]/ancestor::tr[1][ ./descendant::text()[{$this->eq($this->t('Passenger type'))}] ]/following-sibling::tr[ ./td[4] ]");

        foreach ($passengerRows as $row) {
            if ($ticketNumber = $this->http->FindSingleNode('./td[1]', $row, true, '/^(\d[- \d]*\d{4}[- \d]*)$/')) {
                $ticketNumbers[] = $ticketNumber;
            }

            if ($passenger = $this->http->FindSingleNode('./td[3]', $row, true, '/^([^}{]{2,})$/')) {
                $passengers[] = $passenger;
            }

            if ($accountNumber = $this->http->FindSingleNode('./td[5]', $row, true, '/^([- A-Z\d]{5,})$/')) {
                $accountNumbers[] = $accountNumber;
            }
        }

        if (!empty($ticketNumbers[0])) {
            $r->issued()
                ->tickets(array_unique($ticketNumbers), false);
        }

        if (!empty($passengers[0])) {
            $r->general()
                ->travellers(array_unique($passengers));
        }

        if (!empty($accountNumbers[0])) {
            $r->program()
                ->accounts(array_unique($accountNumbers), false);
        }

        return true;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
