<?php

namespace AwardWallet\Engine\delta\Email;

class Promo extends \TAccountChecker
{
    public $mailFiles = "delta/it-38240544.eml";

    public function ParseStatement()
    {
        $result = [];
        $balance = $this->http->FindSingleNode("//td[contains(., 'Current Bonus Miles balance') and not(.//td)]/following-sibling::td[2]", null, true, "/^([\d\,]+) miles [\d\,]+ bonus miles/");

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(., 'Current SkyMiles balance') and not(.//td)]/following-sibling::td[2]", null, true, "/^([\d\,]+) miles [\d\,]+ bonus miles/");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//text()[normalize-space() = 'Your Current Balance:']/ancestor::tr[1]/following-sibling::tr", null, true, "/^([\d\,]+) MILES/");
        }

        if (isset($balance)) {
            $result["Balance"] = str_replace(",", "", $balance);
        }
        $number = $this->http->FindSingleNode("//a[contains(., 'SkyMiles') and contains(., '#')]", null, true, "/SkyMiles[^\#]+\#(\d+)/");
        $result["Name"] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello,')]", null, true, "/^Hello\, ([\w ]+)/");

        if (isset($number)) {
            $result["Login"] = $result["Number"] = $number;
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "Promo",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'DeltaAirLines@e.delta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(., 'New SkyMiles balance')]")->length > 0
                || ($this->http->XPath->query("//text()[contains(., 'SkyMiles')]")->length > 0
                    && $this->http->XPath->query("//text()[contains(., 'Your Current Balance')]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from) > 0
            || stripos($from, 'delta@express.medallia.com') !== false;
    }
}
