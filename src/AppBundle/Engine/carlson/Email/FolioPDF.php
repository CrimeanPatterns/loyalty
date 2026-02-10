<?php

namespace AwardWallet\Engine\carlson\Email;

// TODO: merge with parsers goldpassport/InvoicePDF, omnihotels/FolioForPDF (in favor of goldpassport/InvoicePDF)

class FolioPDF extends \TAccountChecker
{
    public $mailFiles = "carlson/it-34111039.eml";

    public $reFrom = "clubcarlson.com";
    public $reFromH = "carlson";
    public $reBody = [
        'en' => ['Departure', 'Folio No'],
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        $this->AssignLang($html);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FolioPDF" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->reFromH) !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];

        $text = implode("\n", $this->pdf->FindNodes("//body/text()[normalize-space(.)!='']"));

        $it['ConfirmationNumber'] = $this->re("#Conf\.\s+No\.\s+:\s+([A-Z\d-]+)\s*\n#", $text);

        $it['AccountNumbers'][] = $this->re("#Membership\s+No[\.:\s]+([A-Z]*\s*\d+)\s*\n#", $text);

        $it['HotelName'] = $this->pdf->FindSingleNode("//text()[contains(.,'Total')]/following::b[last()]/following::text()[normalize-space(.)!='' and not(contains(.,'.com') or contains(.,'phone') or contains(.,'Guest') or contains(.,'charges') or contains(.,'I agree'))][1]");

        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#" . $this->t('Arrival') . "\s+:\s+(.+?)\s*\n#", $text)));

        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#" . $this->t('Departure') . "\s+:\s+(.+?)\s*\n#", $text)));

        $it['Address'] = implode(' ', $this->pdf->FindNodes("//text()[contains(.,'Total')]/following::b[last()]/following::text()[normalize-space(.)!='' and not(contains(.,'.com') or contains(.,'phone') or contains(.,'Guest') or contains(.,'charges') or contains(.,'I agree'))][position()>1]"));

        $it['Phone'] = $this->pdf->FindSingleNode("//text()[contains(.,'Total')]/following::b[last()]/following::text()[contains(.,'phone')]", null, true, "#phone:\s*(.+?)\s+Fax#");

        $it['Fax'] = $this->pdf->FindSingleNode("//text()[contains(.,'Total')]/following::b[last()]/following::text()[contains(.,'phone')]", null, true, "#phone:\s*.+?\s+Fax:\s+(.+)#");

        $it['GuestNames'][] = $this->pdf->FindSingleNode("//b[1]/text()[1]");

        $it['RoomTypeDescription'] = $this->re("#(Room\s+No\.\s+:\s+.+?)\s*\n#", $text);

        $it['Rate'] = $this->re("#Room\s+(\d.+?)\s*\n#", $text);

        $it['Total'] = $this->pdf->FindSingleNode("//text()[contains(.,'Total')]/following::b[1]");

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+)\-(\d+)\-(\d+)#',
        ];
        $out = [
            '$3-$1-$2',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return preg_replace("#\s+#", " ", $m[$c]);
        }

        return null;
    }
}
