<?php

namespace AwardWallet\Engine\citybank\Email\Statement;

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoBalance extends \TAccountChecker
{
    public $mailFiles = "citybank/statements/it-452659546.eml, citybank/statements/it-452660653.eml, citybank/statements/it-452665342.eml, citybank/statements/it-452799552.eml, citybank/statements/it-65396193.eml, citybank/statements/it-65769109.eml, citybank/statements/it-66285171.eml, citybank/statements/it-66331549.eml, citybank/statements/it-66586434.eml, citybank/statements/it-66595331.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Account no' => ['Account no', 'Account ending in', 'Card ending in'],
            'detectBody' => ['We would like to update you on your account balance', ''],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && preg_match('/[.@]citi[.]com/i', $headers['from']) > 0
            && !empty($headers['subject']) && stripos($headers['subject'], 'activate') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Citibank')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Account no'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]citi\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account no'))}]/preceding::img[contains(@src, 'lock')][1]/ancestor::tr[1]/descendant::text()[normalize-space()]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account no'))}]/preceding::img[contains(@src, 'lock')]/following::span[1]");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account no'))}]/ancestor::td[1]/following::td[1]", null, true, "/^([A-Z]+\d+)$/");

        if ($number === null) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account no'))}]/following::text()[normalize-space()][1]", null, true, "/(\d+)$/");
        }

        if ($number === null) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Account'))}]", null, true, "/(\d+)$/");
        }

        if ($number === null) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cardmember'))}]/ancestor::tr[1]", null, true, "/(\d+)$/");
        }

        if ($number !== null) {
            $st->setNumber($number)->masked('left');
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount:'))}]/ancestor::td[1]/following::td[1]", null, true, "/(\d[,.\'\d ]*)$/");

        if ($balance !== null) {
            $st->setBalance($balance);
        } elseif ($name || $number !== null) {
            $st->setNoBalance(true);
        }

        $rows = $this->http->XPath->query('//tr[not(.//tr) and contains(., "Cardmember since")]');

        if ($rows->length > 0) {
            $owner = $digits = $since = $card = null;
            $root = $rows->item(0);
            $cardInfo = Html::cleanXMLValue($root->nodeValue);
            $this->logger->info($cardInfo);
            // name and card info are either in separate trs, or in one. "lock" symbol is near the name
            if (strpos($cardInfo, 'Cardmember since') === 0) {
                $nameRow = $this->http->XPath->query('./preceding::tr[.//img[contains(translate(@alt, "L", "l"), "lock")]]', $root);

                if ($nameRow->count() > 0) {
                    $owner = Html::cleanXMLValue($nameRow->item($nameRow->count() - 1)->nodeValue);
                }
            } elseif (preg_match('/^([\w\s]+)\s*Cardmember since/', $cardInfo, $m) > 0) {
                $owner = $m[1];
            }

            if (preg_match('/Cardmember since: (\d{4}).+Account ending in: (\d{4})/', $cardInfo, $m)) {
                $since = $m[1];
                $digits = $m[2];
            }

            $card = $this->http->FindSingleNode('(following::tr[normalize-space(.) != ""])[1]', $root);

            if ($owner && $digits && $since && $card) {
                $promo = $email->add()->cardPromo();
                $promo
                    ->setCardOwner($owner)
                    ->setLastDigits($digits)
                    ->setCardMemberSince($since)
                    ->setCardName($card);
            }
        }

        $offer = $this->http->FindSingleNode('//td[not(.//td) and contains(., "EARN") and contains(., "on eligible purchases")]');

        if (isset($promo) && $offer && preg_match('/EARN ([\d+]X).+on eligible purchases through ([^.]+)[.]$/', $offer, $m) > 0) {
            $promo->setMultiplier(strtolower($m[1]))
                ->setOfferDeadline(strtotime(preg_replace('/[\x{200B}-\x{200D}]/u', '', $m[2])));

            if ($apply = $this->http->FindSingleNode('//tr/*[not(.//tr) and contains(., "Activate") and contains(., "this limited-time offer by")]', null, true, '/offer by ([^.]+\d)[.]/')) {
                $promo->setApplicationDeadline(strtotime(preg_replace('/[\x{200B}-\x{200D}]/u', '', $apply)));
            }
            $categories = $this->http->FindNodes('//tr[not(.//tr) and contains(., "Spend") and contains(., "you make at:")]/following-sibling::tr[1]//tr[not(.//tr)]', null, '/^✓\s*\b(.+)$/');
            $promo->setBonusCategories($categories);
            $promo->setApplicationURL($this->http->FindSingleNode('//a[contains(text(), "ACTIVATE IN ONE CLICK")]/@href'));
            $promo->setLimitAmount(str_replace(',', '', $this->http->FindSingleNode('//td[not(.//td) and contains(., "Earn") and contains(., "up to ") and contains(., "points, through")]', null, true, '/^Earn.+up to ([\d,]+) points, through/')));

            if ($promo->getLimitAmount()) {
                $promo->setLimitCurrency('points');
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
}
