<?php

namespace AwardWallet\Engine\delta\Email\Statement;

class Statement extends \TAccountChecker
{
    // delta personal statement email, only 1 known kind
    // subject: your <month> skymiles/medallion statement

    public function ParseStatement()
    {
        $result = [];

        $nodes = $this->findParentCells("Medallion", 2);

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            if (preg_match("/Qualification.+Miles/", CleanXMLValue($node->nodeValue)) && !isset($result["MedallionMilesYTD"])) {
                $result["MedallionMilesYTD"] = $this->findNextNumericCell($node, false);
            }

            if (preg_match("/Qualification.+Segments/", CleanXMLValue($node->nodeValue)) && !isset($result["MedallionSegmentsYTD"])) {
                $result["MedallionSegmentsYTD"] = $this->findNextNumericCell($node, false);
            }
        }
        $balanceNodes = $this->http->XPath->query("//*[contains(text(), 'Balance')]");

        if ($balanceNodes->length > 0) {
            $result["Balance"] = $this->findNextNumericCell($this->findParentNode($balanceNodes->item(0), "td", 6));
        }

        $nodes = $this->http->XPath->query("//sup/parent::*");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $matches = [];

            preg_match("/(.+)\|.+Sk.*yMiles.+\#(\d+)/", CleanXMLValue($node->nodeValue), $matches);

            if (!$matches) {
                preg_match("#([^\|^\=]+)[^\#]+\s?\#(\d+)#", CleanXMLValue($node->nodeValue), $matches);
            }

            if (isset($matches[2])) {
                $result["Name"] = trim($matches[1]);
                $result["Login"] = $result["Number"] = $matches[2];

                break;
            }
        }

        if (empty($result["Number"]) && preg_match("/\#(\d{10})/", $this->http->Response["body"], $matches)) {
            $result["Number"] = $result["Login"] = $matches[1];
        }

        if (!isset($result["Balance"])) {
            $balance = $this->http->FindSingleNode("//td[@width=171]", null, true, "/^[\d\.\,]+$/");

            if (!isset($balance)) {
                $balance = $this->http->FindPreg("/<td width=\"171\"[^\/]+>([\d\,\.]+)</");
            }

            if (isset($balance)) {
                $result["Balance"] = preg_replace("/[\D]/", "", $balance);
            }

            if ($number = $this->http->FindSingleNode("(//*[contains(text(), '#')])[1]")) {
                if (preg_match("/^([^\|]+)\|[^#]+#(\d+)/", $number, $matches)) {
                    $result["Name"] = $matches[1];
                    $result["Number"] = $result["Login"] = $matches[2];
                }
            } else {
                $numbers = $this->http->FindNodes("//*[contains(text(), '|')]");

                foreach ($numbers as $number) {
                    if (preg_match("/^([^\|]+)\|\D+(\d{9,}$)/", $number, $matches)) {
                        $result["Name"] = $matches[1];
                        $result["Number"] = $result["Login"] = $matches[2];
                    }
                }
            }
            $nodes = $this->http->XPath->query("//table/tbody[count(tr[count(td) = 5]) = 3] | //table[count(tr[count(td) = 5]) = 3]");
            $node = ($nodes->length > 0) ? $nodes->item(0) : null;

            for ($i = 1; $i <= 3; $i++) {
                $caption = $this->http->FindSingleNode("tr[$i]/td[2]", $node);

                if (strpos($caption, "MQM") !== false) {
                    $result["MedallionMilesYTD"] = $this->http->FindSingleNode("tr[$i]/td[4]", $node);
                }

                if (strpos($caption, "MQS") !== false) {
                    $result["MedallionSegmentsYTD"] = $this->http->FindSingleNode("tr[$i]/td[4]", $node);
                }
            }
        }
        $levelImg = $this->http->FindSingleNode("//img[contains(@src, 'email_img_status')]/@src", null, true, "/f\.e\.delta\.com.+email_img_status(Silver|Gold|Platinum|Diamond)_/");
        $result["Level"] = $levelImg ? "$levelImg Medallion" : "SkyMiles Member";

        if (isset($result["Balance"]) && isset($result["Number"])) {
            return $result;
        }
        // old ways
        $table = $this->findTableElement($this->http->XPath->query("(//td[count(table) > 5])[1]/table"), 3, 'Balance');

        if (!$table) {
            return [];
        }

        if (!isset($result["Name"]) || !isset($result["Number"])) {
            $table2 = $this->findTableElement($this->http->XPath->query("tr/td/table", $table), 0, 'SkyMiles');

            if (!$table2) {
                $table2 = $this->findTableElement($this->http->XPath->query("tbody/tr/td/table", $table), 0, 'SkyMiles');
            }

            if ($table2) {
                $data = $this->http->FindSingleNode("tr/td/table/tr[2]/td/font[2]", $table2);

                if (!$data) {
                    $data = $this->http->FindSingleNode("tbody/tr/td/table[1]/tbody/tr[2]/td/font[2]", $table2);
                }
                preg_match("/([^\|^\=]+)[^#]+#(\d+)/", $data, $matches);

                if ($matches) {
                    $result["Name"] = trim($matches[1]);
                    $result["Login"] = $result["Number"] = $matches[2];
                }
            }
        }

        if (!isset($result["Balance"])) {
            $table2 = $this->findTableElement($this->http->XPath->query("tbody/tr/td/table", $table), 4, 'Balance');

            if (!$table2) {
                $table2 = $this->findTableElement($this->http->XPath->query("tr/td/table", $table), 4, 'Balance');
            }

            if ($table2) {
                $data = $this->http->FindSingleNode("tbody/tr/td[6]/font", $table2);

                if (!$data) {
                    $data = $this->http->FindSingleNode("tr/td[6]/font", $table2);
                }

                if ($data) {
                    $result["Balance"] = str_replace(",", "", $data);
                }
            }
        }

        if (!isset($result["MedallionMilesYTD"]) || !isset($result["MedallionSegmentsPY"])) {
            $table2 = $this->findTableElement($this->http->XPath->query("tbody/tr/td/table", $table), 5, 'Earned');

            if (!$table2) {
                $table2 = $this->findTableElement($this->http->XPath->query("tr/td/table", $table), 5, 'Earned');
            }

            if ($table2) {
                $node = $this->http->XPath->query("tbody/tr/td[2]/table/tbody/tr/td[3]/table[2]/tbody", $table2);

                if ($node->length == 0) {
                    $node = $this->http->XPath->query("tr/td[2]/table/tr/td[3]/table[2]", $table2);
                }

                if ($node->length > 0) {
                    $result["MedallionMilesYTD"] = $this->http->FindSingleNode("tr/td/font[contains(text(), 'MQM')]/parent::td/following-sibling::td[2]", $node->item(0));
                    $result["MedallionSegmentsYTD"] = $this->http->FindSingleNode("tr/td/font[contains(text(), 'MQS')]/parent::td/following-sibling::td[2]", $node->item(0));
                }
            }
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "Statements",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match("/your [\S]+ (skymiles|medallion) statement/ims", $headers['subject'])
            || isset($headers['from']) && stripos($headers['from'], 'DeltaAirLines@e.delta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, 'SkyMiles') !== false
            || $this->http->XPath->query("//img[contains(@src, 'e.delta.com') and contains(@src, 'insider')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from);
    }

    public function findParentCells($text, $limit = 5)
    {
        $result = "//*[contains(text(), '$text')]/parent::td";
        $xpath = "//*[contains(text(), '$text')]"; //  /parent::td";

        for (; $limit > 0; $limit--) {
            $xpath .= "/parent::*";
            $result .= " | $xpath/parent::td";
        }

        return $this->http->XPath->query($result);
    }

    public function findNextNumericCell($node, $clean = true)
    {
        if (empty($node)) {
            return null;
        }
        $nodes = $this->http->XPath->query("following-sibling::td", $node);

        for ($i = 0; $i < $nodes->length; $i++) {
            preg_match("/^([\d\,]+)$/", CleanXMLValue($nodes->item($i)->nodeValue), $matches);

            if ($matches) {
                return $clean ? str_replace(",", "", $matches[1]) : $matches[1];
            }
        }

        return null;
    }

    /**
     * @param $text
     * @param $number
     *
     * @return \DOMNode|null
     */
    public function findTableElement(\DOMNodeList $elements, $number, $text)
    {
        if ($elements->length <= $number) {
            return null;
        }

        if (!$text) {
            return $elements->item($number);
        }

        if (strpos($elements->item($number)->nodeValue, $text) !== false) {
            return $elements->item($number);
        }
        $i = 0;
        $node = null;

        do {
            if (strpos($elements->item($i)->nodeValue, $text) !== false) {
                $node = $elements->item($i);
            }
            $i++;
        } while ($i < $elements->length && $node == null);

        return $node;
    }

    public static function getEmailLanguages()
    {
        return ["en", "es", "de"];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    /**
     * @param \DOMNode $node
     * @param string $nodeName
     * @param int $limit
     *
     * @return \DOMNode | null
     */
    private function findParentNode($node, $nodeName = "td", $limit = 3)
    {
        while (strtolower($node->nodeName) != $nodeName && $limit > 0) {
            $nodes = $this->http->XPath->query("parent::*", $node);

            if ($nodes->length == 0) {
                $limit = 0;
            } else {
                $node = $nodes->item(0);
            }
            $limit--;
        }

        if (strtolower($node->nodeName) != $nodeName) {
            return null;
        }

        return $node;
    }
}
