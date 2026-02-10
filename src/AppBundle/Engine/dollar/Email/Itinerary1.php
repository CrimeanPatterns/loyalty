<?php

namespace AwardWallet\Engine\dollar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "dollar/it-1.eml, dollar/it-1598725.eml, dollar/it-2.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = "emails.dollar.com";

    private $detectSubject = [
        'Dollar Rent A Car Reservation Confirmation',
        'Reminder: We look forward to seeing you',
    ];
    private $detectBody = [
        'Below are the details of your reservation including your confirmation number',
        'Reservation Reminder Email',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '//dollar.') or contains(@href, '.dollar.')]")->length > 0) {
            foreach ($this->detectBody as $dBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                    return true;
                }
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation # :'))}][1]", null, true, '/Confirmation # :\s*(\w+)$/'))
        ;
        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]", null, true, '/^([[:alpha:] \-]+)$/');

        if (!empty($name)) {
            $r->general()->traveller($name);
        }

        // Pick Up
        $puInfo = "//tr[not(.//tr) and " . $this->eq('VEHICLE PICKUP INFO') . "]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]";
        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode($puInfo . "//td[" . $this->eq('Date/Time:') . "]/following-sibling::td[normalize-space()][1]")))
            ->location($this->http->FindSingleNode($puInfo . "//td[" . $this->eq('Location:') . "]/following-sibling::td[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode($puInfo . "//td[" . $this->eq('Phone:') . "]/following-sibling::td[normalize-space()][1]"), true, true)
        ;

        // Car
        $r->car()
            ->type($this->http->FindSingleNode($puInfo . "//td[" . $this->eq('Vehicle Type:') . "]/following-sibling::td[normalize-space()][1]", null, true, "/^(.+?) - .+/"))
            ->model($this->http->FindSingleNode($puInfo . "//td[" . $this->eq('Vehicle Type:') . "]/following-sibling::td[normalize-space()][1]", null, true, "/.+? - (.+)/"))
        ;

        // Dropp Off
        $doInfo = "//tr[not(.//tr) and " . $this->eq('VEHICLE RETURN INFO') . "]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]";
        $r->dropoff()
            ->date($this->normalizeDate($this->http->FindSingleNode($doInfo . "//td[" . $this->eq('Date/Time:') . "]/following-sibling::td[normalize-space()][1]")))
            ->location($this->http->FindSingleNode($doInfo . "//td[" . $this->eq('Location:') . "]/following-sibling::td[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode($doInfo . "//td[" . $this->eq('Phone:') . "]/following-sibling::td[normalize-space()][1]"), true, true)
        ;

        if ($r->getPickUpLocation() == $r->getDropOffLocation()) {
            $r->dropoff()->same();
        }

        // Price
        $chargesRows = $this->http->FindNodes("//td[not(.//td) and " . $this->eq('Charges:') . "]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]");

        if ((count($chargesRows) % 2) == 0) {
            for ($i = 0; $i < count($chargesRows); $i += 2) {
                $val = $this->getTotalCurrency($chargesRows[$i + 1]);

                if (!empty($val['Total']) && preg_match("/(.+):\s*$/", $chargesRows[$i], $m)) {
                    $r->price()
                        ->fee($m[1], $val['Total']);
                }
            }
        }

        $cost = $this->getTotalCurrency($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq('Base Rate:') . "]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));

        if ($cost['Total'] !== null) {
            $r->price()
                ->cost($cost['Total'])
                ->currency($cost['Currency']);
        }

        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Estimated Grand Total:'))}]",
            null, true, "/:\s*(.+)/"));

        if ($total['Total'] !== null) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
        $currency = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq('Currency:') . "]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]");

        if ($currency == 'U.S. DOLLAR') {
            $r->price()->currency('USD');
        }
        $discount = $this->getTotalCurrency($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq('Discount:') . "]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]",
            null, true, "/^\s*\((.+)\)\s*$/"));

        if ($discount['Total'] !== null) {
            $r->price()
                ->discount($discount['Total']);
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            // Wednesday, July 10, 2013 @ 12:30 PM
            '#^(\w+),\s+(\w+)\s+(\d+),\s+(\d{4})[\s@]+(\d+:\d+(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$3 $2 $4, $5',
        ];
        $str = strtotime(preg_replace($in, $out, $date));

        return $str;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#^\s*(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)\s*$#", $node, $m)
            || preg_match("#^\s*(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})\s*$#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
