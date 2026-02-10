<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * In appearance, email letters are the same, but the layout may differ decently.
 *
 * it-4081962.eml
 *
 * @author Mark Iordan
 */
class BoardingPassHtml2016It extends BoardingPassHtml2016En
{
    public $mailFiles = "alitalia/it-4081962.eml, alitalia/it-4172820.eml, alitalia/it-4534016.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'La tua carta di imbarco') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Ritira la tua carta d\'imbarco in aeroporto alle self, dove possibile') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['it'];
    }

    protected function parseEmail()
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(normalize-space(text()), "CODICE PRENOTAZIONE (PNR)")]', null, false, '/[A-Z\d]{5,6}$/');

        if (!$this->result['RecordLocator'] && $this->http->FindSingleNode('//*[contains(normalize-space(text()), "CODICE PRENOTAZIONE (PNR)")]')) {
            $this->result['RecordLocator'] = CONFNO_UNKNOWN;
        }

        if (empty($this->result['RecordLocator'])) {
            $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(normalize-space(text()), "CODICE PRENOTAZIONE (PNR)")]/ancestor::tr[1]/following-sibling::tr[2]/td[2]', null, false, '/[A-Z\d]{5,6}$/');
        }

        $this->result['Passengers'] = preg_split('/,\s*/', $this->http->FindSingleNode('//*[contains(normalize-space(.), "Gentile ")]/span'));

        $this->parseSegments('//*[contains(normalize-space(text()), "CODICE PRENOTAZIONE (PNR)")]/ancestor::tr[1]/following-sibling::tr[2]/td/table | '
                . '//*[contains(normalize-space(text()), "CODICE PRENOTAZIONE (PNR)")]/ancestor::table[1]/following-sibling::table[1]/tr[2]/td/table');

        return $this->result;
    }
}
