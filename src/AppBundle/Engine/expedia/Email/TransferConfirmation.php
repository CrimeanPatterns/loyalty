<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferConfirmation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-666713749.eml";
    public $subjects = [
        '',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Ride Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Vendor'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('transfer'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eg\.expedia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Pick up')]");

        if ($nodes->length !== 2) {
            $this->logger->debug('Any format');

            return $email;
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Price summary']/following::text()[normalize-space()][1]/following::div[1]", null, true, "/^(\D{1,3}\s*[\d\.\,\']+)$/");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,\']+)/", $total, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        foreach ($nodes as $key => $root) {
            $t = $email->add()->transfer();

            $t->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation is')]/following::text()[normalize-space()='Itinerary #'][1]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Itinerary #'))}\s*(\d+)/"))
                ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Traveler Details']/following::text()[normalize-space()][1]", null, true, "/^(.+)\,/"));

            $yearsText = $this->http->FindSingleNode("//text()[normalize-space()='View full Itinerary']/preceding::text()[contains(normalize-space(), '-')][1]");

            if (preg_match_all("/\s(\d{4})/", $yearsText, $m)) {
                $yearsText = $m[1];
                $s = $t->addSegment();

                $pickUp = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::div[1]", $root);

                if (stripos($pickUp, 'Drop off') !== false) {
                    $pickUp = $this->re("/{$this->opt($this->t('Pick up'))}\s*(.+)\s*{$this->opt($this->t('Drop off'))}/su", $pickUp);
                } else {
                    $pickUp = $this->re("/{$this->opt($this->t('Pick up'))}\s*(.+)/su", $pickUp);
                }

                $dropOff = $this->http->FindSingleNode("./following::text()[normalize-space()='Drop off'][1]/ancestor::div[1]", $root);
                $dropOff = $this->re("/{$this->opt($this->t('Drop off'))}\s*(.+)/su", $dropOff);

                if ($key === 0) {
                    if (preg_match("/^(?<depName>.+)\s+\-\s+(?<depDate>.+)/", $pickUp, $m)) {
                        $time = $this->http->FindSingleNode("./following::text()[normalize-space()='Flight'][1]/following::text()[normalize-space()][1]", $root, true, "/^\d+\:\d+\s*A?P?M/uis");

                        $this->logger->debug($m['depDate'] . ' ' . $yearsText[0] . ', ' . $time);

                        $s->departure()
                            ->name($m['depName'])
                            ->date(strtotime($m['depDate'] . ' ' . $yearsText[0] . ', ' . $time));
                    }

                    $s->arrival()
                        ->name($dropOff)
                        ->noDate();
                } else {
                    if (preg_match("/^(?<depName>.+)\s+\-\s+(?<depDate>.+)/", $pickUp, $m)) {
                        $time = $this->http->FindSingleNode("./following::text()[normalize-space()='Flight'][1]/following::text()[normalize-space()][1]", $root, true, "/^\d+\:\d+\s*A?P?M/uis");

                        $s->departure()
                            ->name($m['depName'])
                            ->noDate();

                        $dropOffDate = strtotime($m['depDate'] . ' ' . $yearsText[1] . ', ' . $time);
                        $s->arrival()
                            ->name($dropOff)
                            ->date(strtotime('-3 hours', $dropOffDate));
                    }
                }
            }
        }

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
