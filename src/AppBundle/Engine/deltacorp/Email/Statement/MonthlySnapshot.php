<?php

namespace AwardWallet\Engine\deltacorp\Email\Statement;

class MonthlySnapshot extends \TAccountChecker
{
    public $mailFiles = "deltacorp/statements/it-69562325.eml";

    // subject: Your <month> SkyBonus Statement SNAPSHOT

    public function ParseStatement()
    {
        $result = [];
        $result["Name"] = $this->http->FindSingleNode("//text()[contains(., 'Hello,')]", null, true, "/Hello\, ([^\.]+)/");

        foreach ([
            ['AccountNumber', 'SkyBonus® ID:', '/^SkyBonus® ID:\s*(US\d+|[A-Z\d]{4,})$/'], ['Balance', 'SKYBONUS POINTS:', '/SKYBONUS POINTS:\s*([\d\,]+)$/'],
        ] as $item) {
            $root = $this->http->XPath->query(sprintf('//td[not(.//td) and contains(normalize-space(.), "%s")]', $item[1]));

            for ($i = 0; $i < 5 && $root->length > 0 && !isset($result[$item[0]]); $i++) {
                if (preg_match($item[2], CleanXMLValue($root->item(0)->nodeValue), $m) > 0) {
                    $result[$item[0]] = $m[1];
                }
                $root = $this->http->XPath->query('parent::*', $root->item(0));
            }
        }

        if (!isset($result['Balance']) && $this->http->XPath->query('//text()[contains(., "SKYBONUS POINTS:")]')->length === 0) {
            $result['Balance'] = $this->http->FindSingleNode('//img[contains(@alt, "SKYBONUS POINTS")]/@alt', null, true, '/SKYBONUS POINTS:\s*([\d\,]+)\b/');
        }

        if (isset($result['AccountNumber'])) {
            $result['Login'] = $result['AccountNumber'];
        }

        if (isset($result['Balance'])) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
        }
        $result['Company'] = $this->http->FindSingleNode('//td[not(.//td) and contains(., "Company Name:")]', null, true, '/Company Name: (.+)/');
        $result['ExpiringBalance'] = $this->http->FindSingleNode('//td[not(.//td) and contains(., "Point Expiration:")]', null, true, '/Point Expiration: ([\d\,]+) points expiring/');

        if (isset($result['ExpiringBalance'])) {
            $result['ExpiringBalance'] = intval(str_replace(',', '', $result['ExpiringBalance']));
        }

        if (isset($result['ExpiringBalance']) && $result['ExpiringBalance'] > 0) {
            $exp = $this->http->FindSingleNode('//td[not(.//td) and contains(., "Point Expiration:")]', null, true, '/Point Expiration: [\d\,]+ points expiring (\w+ \d+, \d+)/');

            if (isset($exp) && strtotime($exp) > strtotime('01/01/2010')) {
                $result['AccountExpirationDate'] = strtotime($exp);
            }
        } else {
            unset($result['ExpiringBalance']);
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "MonthlySnapshot",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers["subject"], "SkyBonus Statement SNAPSHOT") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//img[contains(@src, 'delta.com')]")->length > 0 && stripos($this->http->Response["body"], "skybonus points") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ee.delta.com') !== false;
    }
}
