<?php

namespace AwardWallet\Engine\cvs\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

//use AwardWallet\Common\Parser\Util\EmailDateHelper;

class YourReceipt extends \TAccountChecker
{
    public $mailFiles = "cvs/statements/it-64345284.eml, cvs/statements/it-71006809.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@pharmacy.cvs.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your CVS Pharmacy® Receipt -') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".cvs.com/") or contains(@href,"pharmacy.cvs.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"CVS Pharmacy, Inc.") or contains(.,"www.CVSHealthSurvey.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode('*[normalize-space()][1]', $root, true, "/:\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u");
        $st->addProperty('Name', $name);

//        $cardNumber = $this->http->FindSingleNode('*[normalize-space()][2]', $root, true, "/Coupon valid for card ending in[:\s]+([A-Z\d]+)\s*(?:\||$)/i");
//        $st->setNumber($cardNumber)->masked();

        $seqNo = $this->http->FindSingleNode("descendant::tr[not(.//tr) and starts-with(normalize-space(),'Receipt Seq No')][1]", null, true, "/:\s*(\d{5,})$/");
        $st->addProperty('SequenceNumber', $seqNo);

        $barcodes = array_merge(
            array_filter($this->http->FindNodes("//tr//img[contains(@src,'.cvs.com/bca') and normalize-space(@alt)]/@alt", null, '/^\d{7,}$/')),
            array_filter($this->http->FindNodes("//tr//img[contains(@src,'.cvs.com/') and contains(@src,'bc=')]/@src", null, '/bc=(\d{7,})(?:\D|$)/i'))
        );
        $barcodes = array_values(array_unique($barcodes));

        if (count($barcodes) === 1) {
            $st->addProperty('BarCodeNumber', $barcodes[0]);
        }

//        $asOfValue = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'ExtraCare Card balances as of')]", null, true, "/ExtraCare Card balances as of[:\s]+(\d.+)$/i");
//        if ($asOfValue !== null && !preg_match('/\d{4}$/', $asOfValue) ) {
//            $asOf = EmailDateHelper::calculateDateRelative($asOfValue, $this, $parser, '%D%/%Y%');
//        } elseif ($asOfValue !== null) {
//            $asOf = strtotime($asOfValue);
//        } else {
//            $asOf = null;
//        }
//        $st->setBalanceDate($asOf);

        $YTDSavings = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Year to Date Savings')]", null, true, "/Year to Date Savings[:\s]+(\d[,.\'\d ]*)$/i");
        $st->addProperty('YTDSavings', $this->normalizeAmount($YTDSavings));

        $st->setNoBalance(true);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr/descendant::*[ *[normalize-space()][1][starts-with(normalize-space(),'ExtraCare')] and *[normalize-space()][2][starts-with(normalize-space(),'Coupon valid for card ending in')] ]");
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
