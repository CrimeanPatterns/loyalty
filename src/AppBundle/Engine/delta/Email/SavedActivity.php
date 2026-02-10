<?php

namespace AwardWallet\Engine\delta\Email;

class SavedActivity extends \TAccountChecker
{
    // account activity page from delta site saved in html

    protected $dict = [
        "MQMs" => [],
        "MQSs" => [],
    ];

    protected $lang;

    public function ParseStatement()
    {
        $result = [];

        foreach ([$this->trans("MQMs") => "MedallionMilesYTD", $this->trans("MQSs") => "MedallionSegmentsYTD", $this->trans("MQDs") => "MedallionDollarsYTD"] as $text => $field) {
            $value = $this->http->FindSingleNode("(//*[contains(text(), '($text)')]/ancestor::*[contains(., 'CURRENT')][1])[1]", null, true, "#([\d,.]+)$#");
            $result[$field] = preg_replace("#,#", '', $value);
        }

        $result["Name"] = trim($this->http->FindSingleNode("//*[contains(normalize-space(text()), 'LOG OUT')]/ancestor::div[1][contains(., '#')]//*[contains(@id, 'custlogin_name')]"));
        $result["Number"] = $result["Login"] = $this->http->FindSingleNode("//*[contains(normalize-space(text()), 'LOG OUT')]/ancestor::div[1][contains(., '#')]//*[contains(text(), '#')]", null, true, "/^\#\s*(\d+)/");
        $balance = $this->http->FindSingleNode("//*[contains(normalize-space(text()), 'LOG OUT')]/ancestor::div[1][contains(., '#')]//*[contains(text(), '#')]", null, true, "/^\#\s*\d+\s*\|\s*([\d,.]+)/");

        if (isset($balance)) {
            $result["Balance"] = intval(preg_replace("#,#", '', $balance));
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectLang();
        $props = $this->ParseStatement();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "SavedActivity",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'SkyMiles') !== false && $this->http->XPath->query("//img[contains(@src, 'delta.com')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    protected function trans($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function detectLang()
    {
        $this->lang = "en";
    }
}
