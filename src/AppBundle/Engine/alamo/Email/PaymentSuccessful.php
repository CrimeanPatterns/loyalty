<?php

namespace AwardWallet\Engine\alamo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PaymentSuccessful extends \TAccountChecker
{
    public $mailFiles = "alamo/it-79998181.eml, alamo/it-80606529.eml";
    public $subjects = [
        '/Alamo Reservation Confirmation - Payment Successful$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Total charged by Alamo' => ['Total charged by Alamo', 'To be charged at the Alamo counter'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@goalamo.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Alamo Rent A Car')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Car Pickup'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('My Rental Summary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]goalamo\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Name'))}]/preceding::text()[normalize-space()][1]", null, true, "/([A-Z]{3}\S\d+)/"));

        $statusInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'The reservation is')]");

        if (preg_match("/^The reservation is (\w+)\s*\SThanks to choice (.+)\!/u", $statusInfo, $m)) {
            $r->general()
                ->status($m[1]);

            $r->setCompany($m[2]);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Your reservation has been cancelled')]")->length > 0) {
            $r->general()
                ->status('cancelled')
                ->cancelled();
        }

        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Name'))}]/ancestor::tr[1]/descendant::td[2]"), true);

        $pickUpInfo = $this->http->FindSingleNode("//text()[normalize-space()='Car Pickup']/following::tr[normalize-space()][1]");

        if (preg_match("/^(\d+\s*\w+\s*\d{4}\s*[\d:]+)\s*(.+)\s*Office Hrs.\s*(.+)\s*Tel\.\s*(.+)/", $pickUpInfo, $m)) {
            $r->pickup()
                ->date(strtotime($m[1]))
                ->location($m[2])
                ->openingHours($m[3])
                ->phone($m[4]);
        }

        $dropOffInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Car Dropoff'))}]/following::tr[normalize-space()][1]");

        if (preg_match("/^(\d+\s*\w+\s*\d{4}\s*[\d:]+)\s*(.+)\s*Office Hrs.\s*(.+)\s*Tel\.\s*([\d\s]+)/", $dropOffInfo, $m)) {
            $r->dropoff()
                ->date(strtotime($m[1]))
                ->location($m[2])
                ->openingHours($m[3])
                ->phone($m[4]);
        }

        $carInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Car Dropoff'))}]/following::text()[starts-with(normalize-space(), 'Tel.')]/following::text()[normalize-space()][not(contains(normalize-space(), 'Do you need directions?'))][1]/ancestor::td[1]");
        $this->logger->error($carInfo);

        if (preg_match("/^(.+or similar)\s*(\D+)\s*\d+\s*\d+\s*\w+$/", $carInfo, $m)) {
            $r->car()
                ->model($m[1])
                ->type($m[2]);
        }
        $r->car()
            ->image($this->http->FindSingleNode("//text()[{$this->eq($this->t('Car Dropoff'))}]/following::text()[starts-with(normalize-space(), 'Tel.')]/following::img[1]/@src"));

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total charged by Alamo'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^[A-Z]{3}\s*([\d\.\,]+)/");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total charged by Alamo'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z]{3})/");

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(str_replace(',', '', $total))
                ->currency($currency);
        }

        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total charged by Alamo'))}]/preceding::text()[contains(normalize-space(), 'Tax')][1]/ancestor::tr[1]/descendant::td[3]");

        if (!empty($tax)) {
            $r->price()
                ->tax(str_replace(',', '', $tax));
        }

        $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total charged by Alamo'))}]/preceding::text()[contains(normalize-space(), 'Fee')][1]/ancestor::tr[1]/descendant::td[3]");

        if (!empty($fee)) {
            $r->price()
                ->fee('Fee', str_replace(',', '', $fee));
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[3]");

        if (!empty($cost)) {
            $r->price()
                ->cost(str_replace(',', '', $cost));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
}
