<?php

namespace AwardWallet\Engine\broadway\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTickets extends \TAccountChecker
{
    public $mailFiles = "broadway/it-124726395.eml, broadway/it-125723697.eml, broadway/it-125925413.eml";
    public $subjects = [
        'Your tickets for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Manage Order'                               => ['Manage Order', 'View Order', 'View Tickets'],
            'Order #'                                    => ['Order #', 'Order Reference #'],
            'View theater information & safety policies' => [
                'View theater information & safety policies',
                'Your Ticket Delivery Method',
                'Have Questions? We’re here to help!',
                'Each ticket is valid for a single entry',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@broadway.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Broadway.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->eq($this->t('Manage Order'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View theater information & safety policies'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]broadway\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(4);

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Name']/ancestor::tr[1]/descendant::td[2]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//img[contains(@alt, 'Map')]/preceding::text()[normalize-space()='Name'][1]/ancestor::tr[1]/descendant::td[2]");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)/");
        }

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order #'))}]/ancestor::tr[1]/descendant::td[2]"))
            ->traveller(trim($traveller, ','));

        $e->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Manage Order'))}]/following::text()[contains(normalize-space(), ':')][1]")))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Manage Order'))}]/following::text()[contains(normalize-space(), 'Ticket')][1]", null, true, "/^(\d+)\s*Ticket/i"))
            ->noEnd();

        $seat = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Manage Order'))}]/following::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/following-sibling::tr"));

        if (!empty($seat)) {
            $e->booked()
                ->seat($seat);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Manage Order'))}]/following::a[normalize-space()][1][not({$this->contains($this->t('Manage Order'))})]");
        $e->setName($name);

        $address = $this->http->FindSingleNode("//img[contains(@alt, 'Map')]/following::text()[normalize-space()][2]/ancestor::td[1]");
        /*if (empty($address))
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View theater information & safety policies'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]");
        if (empty($address))
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Ticket Delivery Method'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]");
        if (empty($address))
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Have Questions? We’re here to help!'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]");*/
        $e->setAddress($address);

        $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Grand Total')]/ancestor::tr[1]/descendant::td[last()]");

        if (preg_match("/^Grand Total\s*([A-Z]{3})\s*\D*([\d\,\.]+)$/us", $price, $m)) {
            $e->price()
                ->total(PriceHelper::cost($m[2], ',', '.'))
                ->currency($m[1]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

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

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+a?p?m)$#', //Thursday, Dec 30, 2021 at 7:00pm
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
