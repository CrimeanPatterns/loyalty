<?php

namespace AwardWallet\Engine\delta\Email;

class SavedStatement extends \TAccountCheckerExtended
{
    // delta personal statement, saved from site and sent by email to AW
    public $mailFiles = "delta/it-4445449.eml, delta/it-4682011.eml, delta/statements/it-67231613.eml, delta/statements/it-67240150.eml, delta/statements/it-67244539.eml, delta/statements/it-69418009.eml, delta/statements/st-2044166.eml, delta/statements/st-2511869.eml, delta/statements/st-2511878.eml, delta/statements/st-3840525.eml, delta/statements/st-3840574.eml, delta/statements/st-3841002.eml, delta/statements/st-3841002.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Name'] = $this->http->FindSingleNode('//a[contains(@id, "custlogin-user-link")]');

        if (empty($result['Name'])) {
            $result['Name'] = $this->http->FindSingleNode("//div[@class='myDeltaProfile_p3']");
        }

        if (empty($result['Name'])) {
            $result['Name'] = $this->http->FindSingleNode("//div[@id='myDeltaProfile']/descendant::text()[normalize-space()!=''][1][//following::text()[string-length(normalize-space())>2][1][starts-with(.,'SKYMILES')]]");
        }
        $number = $this->http->FindSingleNode('//span[contains(@id, "skymiles_number")]');

        if (!isset($number)) {
            $roots = $this->http->XPath->query('//*[text()[contains(., "SKYMILES #")]]');

            for ($i = 0; $i < 5 && !isset($number) && $roots->length > 0; $i++) {
                if (preg_match('/SKYMILES\s*\#\s*:\s*(\d{7,})\b/', CleanXMLValue($roots->item(0)->nodeValue), $m) > 0) {
                    $number = $m[1];
                }
                $roots = $this->http->XPath->query('parent::*', $roots->item(0));
            }
        }
        $result['Login'] = $result['Number'] = $number;

        $result['Balance'] = $this->http->FindSingleNode('//div[@class="miles_value"]');

        if (!isset($result['Balance'])) {
            $node = $this->http->XPath->query('//*[contains(text(), "TOTAL AVAILABLE MILES")]');
            $i = 0;

            while ($node->length > 0 && !isset($result['Balance']) && $i < 3) {
                $result['Balance'] = $this->http->FindSingleNode('.', $node->item(0));
                $result['Balance'] = preg_match('/^TOTAL AVAILABLE MILES\s*(Miles never expire)?\s*(?<balance>[\d\,]+)$/', $result['Balance'], $m) ? str_replace(',', '', $m['balance']) : null;
                $node = $this->http->XPath->query('./parent::*', $node->item(0));
                $i++;
            }
        }

        if (!isset($result['Balance'])) {
            $result['Balance'] = $this->http->FindSingleNode("//*[contains(text(), 'TOTAL AVAILABLE MILES')]/following::text()[normalize-space() and not(contains(.,'Miles never expire'))][1]", null, false, '/^[\d\.\,]+$/');
        }

        if (isset($result['Balance'])) {
            $result['Balance'] = str_replace([' ', ','], '', $result['Balance']);
        }

        $result['MedallionMilesYTD'] = trim($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQMs:")]', null, true, '/^MQMs: ([\d\,\s]+)(?:MQSs|$)/'));

        if ($result['MedallionMilesYTD'] === '') {
            $result['MedallionMilesYTD'] = trim($this->http->FindSingleNode('//*[contains(normalize-space(text()), "Medallion Qualification Miles")]/ancestor-or-self::span[not(.//a)]/following-sibling::span[1]', null, true, '/^[\d\,\s]+$/'));
        }

        $result['MedallionSegmentsYTD'] = trim($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQSs:")]', null, true, '/^MQSs: ([\d\,]+)(?:\s*to |$)/'));

        if ($result['MedallionSegmentsYTD'] === '') {
            $result['MedallionSegmentsYTD'] = trim($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQMs:")]', null, true, '/[\d\,\s]+MQSs: ([\d\,\s]+)(?:to |$)/'));
        }

        if ($result['MedallionSegmentsYTD'] === '') {
            $result['MedallionSegmentsYTD'] = trim($this->http->FindSingleNode('//*[contains(normalize-space(text()), "Medallion Qualification Segments")]/ancestor-or-self::span[not(.//a)]/following-sibling::span[1]', null, true, '/^[\d\,\s]+$/'));
        }

        $result['MedallionDollarsYTD'] = trim($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQDs:")]', null, true, '/^MQDs: (\$[\d\,\s]+)(?:CARD SPEND|$)/'));

        if ($result['MedallionDollarsYTD'] === '') {
            $result['MedallionDollarsYTD'] = trim($this->http->FindSingleNode('//*[contains(normalize-space(text()), "Medallion Qualification Dollars")]/ancestor-or-self::span[not(.//a)]/following-sibling::span[string-length(normalize-space())>2][1]', null, true, '/^\$[\d\,\s]+$/'));
        }

        if (empty($result['MedallionDollarsYTD']) && $this->http->FindSingleNode("//div[./a[starts-with(normalize-space(),'MQDs')] and contains(normalize-space(),'applicable for U.S. Members only')]")) {
            unset($result['MedallionDollarsYTD']);
        }

        $result['MillionMiles'] = $this->http->FindSingleNode('//div[@class="milliom_miler_dgt"]');

        if (!isset($result['MillionMiles'])) {
            $node = $this->http->XPath->query('//*[contains(text(), "MILLION MILER")]');
            $i = 0;

            while ($node->length > 0 && !isset($result['MillionMiles']) && $i < 3) {
                $result['MillionMiles'] = $this->http->FindSingleNode('.', $node->item(0));
                $result['MillionMiles'] = preg_match('/^MILLION MILER.{1,5}BALANCE\s*(?<balance>[\d\,]+)$/', $result['MillionMiles'], $m) ? str_replace(',', '', $m['balance']) : null;
                $node = $this->http->XPath->query('./parent::*', $node->item(0));
                $i++;
            }
        }

        if (empty($result['MillionMiles'])) {
            $result['MillionMiles'] = $this->http->FindSingleNode("//div[@class='label_icon_hldr']/div[contains(@class,'milliom_miler_dgt')]");
        }
        $year = (int) date('Y', strtotime($parser->getDate()));

        if (empty($result['MillionMiles'])
            && ($this->http->FindSingleNode("//a[contains(normalize-space(),'view million miler qualifications') or contains(normalize-space(),'View Million Miler Qualifications')]")
                || $year < 2017)
        ) {
            unset($result['MillionMiles']);
        }

        $patterns['status'] = '(SKYMILES\s+MEMBER|SILVER\s+MEDALLION|GOLD\s+MEDALLION|PLATINUM\s+MEDALLION|DIAMOND\s+MEDALLION)';
        $result['Level'] = $this->http->FindSingleNode('//div[normalize-space()="Current Status:"]/following-sibling::div[string-length(normalize-space())>1][1]', null, true, '/^' . $patterns['status'] . '/i');

        if (empty($result['Level'])) {
            $result['Level'] = $this->http->FindSingleNode('//div[@class="small_status_holder"][1]', null, true,
                '/^' . $patterns['status'] . '/i');
        }

        if (empty($result['Level']) && $year < 2017) {
            unset($result['Level']);
        }

        if (empty($result['MedallionMilesYTD']) && empty($result['MedallionSegmentsYTD']) && empty($result['MedallionDollarsYTD']) && empty($result['Level'])
            && $this->http->XPath->query("//div[@class='common_year_holder_header'][contains(.,'View Current Year Details')]")->length === 0
            && $this->http->XPath->query("//div[@class='myDeltaContentContainer']")->length === 1
            // check format
            && (!empty($result['MillionMiles'])
                || $this->http->FindSingleNode("//a[starts-with(normalize-space(),'MY ') and contains(.,'STATUS')]/following::text()[normalize-space()!=''][1][contains(.,'MY RECENT ACTIVITY')]/following::text()[normalize-space()!=''][1][contains(.,'MY ACCOUNT ACTIVITY')]")
            )
        ) {
            unset($result['MedallionMilesYTD']);
            unset($result['MedallionSegmentsYTD']);
            unset($result['MedallionDollarsYTD']);
            unset($result['Level']);

            if (empty($result['MillionMiles'])) {
                unset($result['MillionMiles']);
            }
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody($parser->getHTMLBody());
        $props = $this->ParseStatement($parser);

        return [
            'parsedData' => ['Properties' => $props],
            'emailType'  => 'SavedStatements',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;

        return $this->http->XPath->query('//a[contains(normalize-space(.), "VIEW MY PROFILE") and contains(@href, "delta.com/profile/index")]')->length > 0
            || ($this->http->XPath->query("//div[@id='skymiles_summary_parent']")->length > 0 && $this->http->XPath->query("//div[contains(@id,'myDeltaProfile')]")->length > 0)
            || ($this->http->XPath->query('//*[contains(@id, "skymiles_text")]')->length > 0 && $this->http->XPath->query('//*[contains(@class, "miles_text")]')->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
