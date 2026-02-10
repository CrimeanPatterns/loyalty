<?php

namespace AwardWallet\Engine\delta\Email;

class SavedStatementPDF extends \TAccountCheckerExtended
{
    // delta personal statement, saved to PDF from site and sent by email to AW
    public $mailFiles = "delta/statements/st-2044194.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            $text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_COMPLEX));

            if (preg_match('#BOOK\s+A\s+TRIP\s+(.*?)\s*(SILVER\s+MEDALLION|GOLD\s+MEDALLION\s+|PLATINUM\s+MEDALLION|DIAMOND\s+MEDALLION)#is', $text, $m)) {
                $result['Name'] = nice(beautifulName($m[1]));
                $result['Level'] = nice(beautifulName($m[2]));
            }
            $result['Number'] = $result['Login'] = re('#SKYMILES\s*\#\s*:\s*(?:VIEW\s+MY\s+PROFILE)?\s*(\d+)#i', $text);
            $balances = [
                'Balance'              => '#TOTAL\s+AVAILABLE\s+MILES\s+([\d,]+)#i',
                'MillionMiles'         => '#MILLION\s+MILER\s+BALANCE\s+([\d,]+)#i',
                'MedallionMilesYTD'    => '#MQMs\s*:\s*((?:\d+,)?\d{3})#i',
                'MedallionSegmentsYTD' => '#MQSs\s*:\s*(\d+)#i',
                'MedallionDollarsYTD'  => '#MQDs\s*:\s*\$((?:\d+,)?\d{1,3})#i',
            ];

            foreach ($balances as $key => $regex) {
                $balance = re($regex, $text);
                $result[$key] = $balance ? str_replace(',', '', $balance) : null;
            }

            if (!isset($result['Login'])) {
                unset($result['Balance']);
            }
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
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
        if ($pdfs = $parser->searchAttachmentByName('.*pdf')) {
            $text = $parser->getAttachmentBody($pdfs[0]);
            $pdfText = \PDF::convertToText($text);

            return preg_match('#MY\s+SKYMILES\s+SUMMARY#i', $pdfText);
        } else {
            return false;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
