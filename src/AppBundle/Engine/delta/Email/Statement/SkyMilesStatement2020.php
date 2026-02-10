<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SkyMilesStatement2020 extends \TAccountChecker
{
    public $mailFiles = "delta/it-29226432.eml, delta/statements/it-133363327.eml, delta/statements/it-165571604.eml, delta/statements/it-76621745.eml, delta/statements/st-56592917.eml, delta/statements/st-59740138.eml, delta/statements/st-59751167.eml";

    // statement can be displayed in an img, or in a regular html table
    // in the second case, img can still be found in commented html (59751167)
    // or not (59740138)
    private $imgXpath =
        '//img[contains(@src, "mi.delta.com")
            and contains(@src, "&mi_MQM=")
            and contains(@src, "&mi_MQS=")
            and contains(@src, "&mi_MQD=")
            and contains(@src, "?mi_u=")
        ]/@src';

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'delta.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'SkyMiles Statement Is Here') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query($this->imgXpath)->length > 0
            || $this->http->XPath->query('//comment()[contains(., "&mi_MQM_Cnt=")]')->length > 0
            || $this->http->XPath->query('//*[text()[contains(., "MQMs")] and sup]')->length > 0)
            && (strpos($parser->getHTMLBody(), 'Total Miles') !== false
            || $this->http->XPath->query('//text()[normalize-space(.)=\'Total Miles:\']')->length > 0)
        ) {
            return true;
        }

        if ($this->http->XPath->query('//td[not(.//td)][starts-with(normalize-space(), \'Hello,\')][count(.//text()[normalize-space()]) < 10][contains(., \'#\') and contains(., \'Total Miles: \')]')->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]delta\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (($nodes = $this->http->XPath->query($this->imgXpath))->length > 0) {
            $url = parse_url($nodes->item(0)->nodeValue);

            if (!empty($url['query'])) {
                parse_str($url['query'], $params);
            }
        }

        if (!isset($params)) {
            foreach ($this->http->FindNodes('//comment()[contains(., \'img\') and contains(., \'src="http://mi.delta.com\')]',
                null,
                '/<img[^>]+src="(https?:\/\/mi\.delta\.com[^"]+)/') as $node) {
                $url = parse_url($node);

                if (!empty($url['query'])) {
                    parse_str($url['query'], $params);
                }
            }
        }
        $st = $email->add()->statement();

        $dateOfBalance = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]', null, true, "/(\d+\/\d+\/\d+)$/");

        if (!empty($dateOfBalance)) {
            $st->setBalanceDate(strtotime($dateOfBalance));
        }

        if (isset($params)) { // parsing from img src
            $params = $params + ['mi_u' => null, 'mi_MQM' => null, 'mi_MQS' => null, 'mi_MQD' => null];
            $number = $params['mi_u'];
            $st->addProperty('Number', $params['mi_u']);
            $st->addProperty('Login', $params['mi_u']);
            $st->addProperty('MedallionMilesYTD', $params['mi_MQM']);
            $st->addProperty('MedallionSegmentsYTD', $params['mi_MQS']);
            $st->addProperty('MedallionDollarsYTD', $params['mi_MQD']);
        } else { // from html table
            $medallionMilesYTD = $this->http->FindSingleNode('//*[text()[contains(., "MQMs")] and sup]', null, true, '/^([\d,]+) MQMs\d?/');

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//*[text()[contains(., "MQMs")] and sup]/ancestor::*[1]', null, true, '/^([\d,]+) MQMs\d?/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::*[text()[contains(., "MQMs")][1] and sup and contains(normalize-space(), "1")]', null, true, '/^([\d,]+) MQMs\d?/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::span[contains(., "MQMs") and sup][1]/ancestor::p', null, true, '/^([\d,]+) MQMs\d?/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//h1[starts-with(normalize-space(), "Stay Connected With") and contains(normalize-space(), "SkyMiles")]/preceding::text()[contains(., "MQMs")][1]/ancestor::*[1]', null, true, '/^([\d,]+) MQMs\d?/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQM")]/ancestor::tr[1]', null, true, '/^([\d,]+) MQMs\d?/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::text()[starts-with(normalize-space(), "MQM")]/ancestor::tr[1]', null, true, '/^([\d,]+) MQMs\d?/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//*[text()[contains(., "Medallion Qualification Miles")]]/ancestor::tr[1]', null, true, '/^([\d,]+)\s*Medallion Qualification Miles/');
            }

            if (empty($medallionMilesYTD) && $medallionMilesYTD !== '0') {
                $medallionMilesYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of") or starts-with(normalize-space(), "Privacy Policy") ]/preceding::*[text()[contains(., "Medallion Qualification Miles")]]/ancestor::tr[1]', null, true, '/^([\d,]+)\s*Medallion Qualification Miles/');
            }

            if (empty($medallionMilesYTD) && empty($this->http->FindSingleNode('//text()[contains(normalize-space(), "MQM")]'))) {
            } else {
                $st->addProperty('MedallionMilesYTD', $medallionMilesYTD);
            }

            $medallionSegmentsYTD = $this->http->FindSingleNode('//*[text()[contains(., "MQSs")] and sup]', null, true, '/^([\d,.]+) MQSs\d?/');

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//*[text()[contains(., "MQSs")] and sup]/ancestor::*[1]', null, true, '/^([\d,.]+) MQSs\d?/');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::*[text()[contains(., "MQSs")][1] and sup and contains(normalize-space(), "2")]', null, true, '/^([\d,.]+) MQSs\d?/');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::span[contains(., "MQSs") and sup][1]/ancestor::p', null, true, '/^([\d,.]+) MQSs\d?/');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//h1[starts-with(normalize-space(), "Stay Connected With") and contains(normalize-space(), "SkyMiles")]/preceding::text()[contains(., " MQSs")][1]/ancestor::*[1]', null, true, '/^([\d,.]+) MQSs\d?/');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQSs")]/ancestor::tr[1]', null, true, '/^([\d,.]+) MQSs\d?/');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::text()[starts-with(normalize-space(), "MQSs")]/ancestor::tr[1]', null, true, '/^([\d,.]+) MQSs\d?/');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//*[text()[contains(., "Medallion Qualification Segments")]]/ancestor::tr[1]', null, true, '/^\s*([\d,]+)\s*Medallion Qualification Segments/s');
            }

            if (empty($medallionSegmentsYTD) && $medallionSegmentsYTD !== '0') {
                $medallionSegmentsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/preceding::*[text()[contains(., "Medallion Qualification Segments")]]/ancestor::tr[1]', null, true, '/^\s*([\d,]+)\s*Medallion Qualification Segments/s');
            }

            if (empty($medallionMilesYTD) && empty($this->http->FindSingleNode('//text()[contains(normalize-space(), "MQS")]'))) {
            } else {
                $st->addProperty('MedallionSegmentsYTD', $medallionSegmentsYTD);
            }

            $medallionDollarsYTD = $this->http->FindSingleNode('//*[text()[contains(., "MQDs")] and sup]', null, true, '/^(\$[\d,]+) MQDs\d?/');

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//*[text()[contains(., "MQDs")] and sup]/ancestor::*[1]', null, true, '/^(\$[\d,]+) MQDs\d?/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::*[text()[contains(., "MQDs")][1] and sup and contains(normalize-space(), "3")]', null, true, '/^(\$[\d,]+) MQDs\d?/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::span[contains(., "MQDs") and sup][1]/ancestor::p', null, true, '/^(\$[\d,]+) MQDs\d?/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//h1[starts-with(normalize-space(), "Stay Connected With") and contains(normalize-space(), "SkyMiles")]/preceding::text()[contains(., " MQDs")][1]/ancestor::*[1]', null, true, '/^(\$[\d,]+) MQDs\d?/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "MQDs")]/ancestor::tr[1]', null, true, '/^(\$[\d,]+) MQDs\d?/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::text()[starts-with(normalize-space(), "MQDs")]/ancestor::tr[1]', null, true, '/^(\$[\d,]+) MQDs\d?/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/following::text()[starts-with(normalize-space(), "Medallion Qualification Dollars")]/ancestor::tr[1]', null, true, '/^(\$[\d,]+) Medallion Qualification Dollars/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//*[text()[contains(., "Medallion Qualification Dollars")]]/ancestor::tr[1]', null, true, '/^\s*(\$[\d,]+)\s*Medallion Qualification Dollars/');
            }

            if (empty($medallionDollarsYTD)) {
                $medallionDollarsYTD = $this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Balance As Of")]/preceding::*[text()[contains(., "Medallion Qualification Dollars")]]/ancestor::tr[1]', null, true, '/^\s*(\$[\d,]+)\s*Medallion Qualification Dollars/');
            }

            if (empty($medallionMilesYTD) && empty($this->http->FindSingleNode('//text()[contains(normalize-space(), "MQD")]'))) {
            } else {
                $st->addProperty('MedallionDollarsYTD', $medallionDollarsYTD);
            }

            $roots = $this->http->XPath->query('//text()[contains(., \'Total Miles:\')]');

            for ($i = 0; $i < 5 && $roots->length === 1; $i++) {
                $parents = $this->http->XPath->query('parent::*', $roots->item(0));
                $this->http->Log($parents->item(0)->nodeValue);

                if (preg_match('/^\s*Hello,? ([\w\-\' ]+)\s*#(\d+)\s*\|?\s*(.+)Total Miles:\s*([\d,]+)\s*$/u', $parents->item(0)->nodeValue, $m) > 0) {
                    $st->addProperty('Name', $m[1]);
                    $st->addProperty('Number', $m[2]);
                    $st->addProperty('Login', $m[2]);
                    $st->addProperty('Level', trim(str_replace("\u{00ae}", '', $m[3])));
                    $st->setBalance(str_replace(',', '', $m[4]));

                    break;
                }
                $roots = $parents;
            }
        }

        if (isset($number) && ($nodes = $this->http->XPath->query(sprintf('//*[contains(text(), "#%s")]', $number)))->length > 0) {
            for ($i = 0; $i < 5; $i++) {
                $parents = $this->http->XPath->query('parent::*', $nodes->item(0));
                $this->http->Log($parents->item(0)->nodeValue);

                if (preg_match('/^\s*Hello,? ([\w- ]+)\b\s*#(\d+)\s*\|\s*(.+)Total Miles:\s*([\d,]+)\s*$/u', $parents->item(0)->nodeValue, $m) > 0) {
                    $st->addProperty('Name', $m[1]);
                    $st->addProperty('Level', trim(str_replace("\u{00ae}", '', $m[3])));
                    $st->setBalance(str_replace(',', '', $m[4]));

                    break;
                }
                $nodes = $parents;
            }
        }
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
