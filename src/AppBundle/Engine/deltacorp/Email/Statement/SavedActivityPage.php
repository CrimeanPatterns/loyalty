<?php

namespace AwardWallet\Engine\deltacorp\Email\Statement;

class SavedActivityPage extends \TAccountChecker
{
    public $mailFiles = "";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivityPage',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//text()[normalize-space()="SKYBONUS POINTS"]')->length > 0
            && $this->http->XPath->query('//a[contains(@href,"skybonus.delta.com/content/skybonus/corporate")]')->length > 0;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $props = [];
        $activity = [];
        $result = ['Properties' => &$props, 'Activity' => &$activity];

        $props['Balance'] = str_replace(['.', ','], '', $this->http->FindSingleNode("//text()[normalize-space()='SKYBONUS POINTS']/following::div[normalize-space()!=''][1]/descendant::*[@id='pointsAvailable']"));
        $props['Redeemed'] = str_replace(['.', ','], '', $this->http->FindSingleNode("//text()[normalize-space()='SKYBONUS POINTS']/following::div[normalize-space()!=''][1]/descendant::*[@id='pointsRedeemed']"));
        $props['Company'] = $this->http->FindSingleNode("//text()[normalize-space()='SkyBonus ID:']/preceding::text()[normalize-space()!=''][1]/ancestor::*[1][@id='compName']");
        $props['AccountNumber'] = $this->http->FindSingleNode("//text()[normalize-space()='SkyBonus ID:']/following::text()[normalize-space()!=''][1]");

        if (isset($props['AccountNumber'])) {
            $props['Login'] = $props['AccountNumber'];
        }
        $props['ExpiringBalance'] = str_replace(['.', ','], '', $this->http->FindSingleNode("//text()[normalize-space()='SKYBONUS POINTS']/following::div[normalize-space()!=''][1]/descendant::*[@id='pointsExpiredCurrYear']"));

        // Activity no need to collect

        return $result;
    }
}
