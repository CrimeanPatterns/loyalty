<?php

namespace AwardWallet\Engine\delta\Email;

class SavedStatementText extends \TAccountChecker
{
    public $mailFiles = "";

    public function ParseStatement($text)
    {
        $result = [];

        if (preg_match('/CHECK\s+IN\s*(.+?)\s*You are/s', $text, $matches)) {
            $result['Name'] = $matches[1];
        }

        if (preg_match('/SKYMILES\s+#:\s+(\d+)/', $text, $matches)) {
            $result['Number'] = $result['Login'] = $matches[1];
        }

        if (preg_match('/Miles\s+never\s+expire\s*(.{1,20})\s*VIEW/s', $text, $matches)) {
            $result['Balance'] = (int) preg_replace('/[^\d]+/', '', $matches[1]);
        }

        if (preg_match('/Qualification\s+Miles\s*(.+?)Medallion/s', $text, $matches)) {
            $result['MedallionMilesYTD'] = preg_replace('/[^\d]+/', '', $matches[1]);
        }

        if (preg_match('/Qualification\s+Segments\s*(.+?)Medallion/s', $text, $matches)) {
            $result['MedallionSegmentsYTD'] = trim($matches[1]);
        }

        if (preg_match('/Qualification\s+Dollars.*?(\$[\d.,\s]+)Card/s', $text, $matches)) {
            $result['MedallionDollarsYTD'] = trim($matches[1]);
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $this->cutToWord($parser->getHTMLBody(), 'TRIP MAP');
        $statement = $this->ParseStatement($this->text($text));

        return [
            'parsedData' => ['Properties' => $statement],
            'emailType'  => 'SavedStatements',
        ];
    }

    public function cutToWord($text, $word)
    {
        return substr($text, 0, strpos($text, $word));
    }

    public function text($string)
    {
        return preg_replace('/<[^>]+>/', ' ', str_replace(['<br>', '<br/>', '<br />', '&nbsp;', '·'], ' ', $string));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Miles never expire') !== false
                && strpos($parser->getHTMLBody(), 'Keep Track of Your Trip') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
