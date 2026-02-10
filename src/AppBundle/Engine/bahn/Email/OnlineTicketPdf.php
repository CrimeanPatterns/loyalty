<?php

namespace AwardWallet\Engine\bahn\Email;

require_once __DIR__ . '/../functions.php';

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class OnlineTicketPdf extends \TAccountCheckerBahn
{
    public $mailFiles = "bahn/it-11271234.eml, bahn/it-1726138.eml, bahn/it-1726139.eml, bahn/it-1734853.eml, bahn/it-1735482.eml, bahn/it-21977355.eml, bahn/it-2202250.eml, bahn/it-2596687.eml, bahn/it-3023539.eml, bahn/it-3195203.eml, bahn/it-523924388.eml, bahn/it-53916811.eml";

    public $reFrom = '@bahn.de';

    public $reSubject = [
        'de'  => 'Anreise.pdf',
        'de2' => 'Rückreise.pdf',
        'de3' => 'Buchungsbestätigung (Auftrag',
        'en'  => 'Deutsche Bahn booking confirmation',
    ];

    public $langDetectors = [
        'de' => ['Reservierung', 'Bemerkung', 'Reisende'],
        'en' => ['order number', 'Services'],
    ];

    public static $dictionary = [
        'de' => [
            'namePrefixes' => ['Ms.', 'Dr.', 'Herr', 'Dame', 'Frau', 'Mann', 'Familie'],
            'previous'     => ['Kreditkartenzahlung', 'Ticket', 'Dieses Dokument', 'Gesamtpreis', 'Uhr', 'Ziel', 'Eine Stornierung'],
        ],

        'en' => [
            'Deutschen Bahn'     => ['Deutsche Bahn', 'Deutschen Bahn'],
            'Auftragsnummer'     => 'order number',
            'wie folgt gebucht:' => 'as follows:',
            'Leistungen'         => 'Services',
            'in Höhe von'        => 'journey, amounting to',
            'von'                => 'from',
            'nach'               => 'to',
        ],
    ];

    public $lang = '';
    public $subject;
    public $html = false;

    /** @var \HttpBrowser */
    private $pdf;
    private $pdfBody;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('You have been sent this email by the Trainline Group.'))}]")->length > 0) {
            return false;
        }
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (strpos($textPdf, 'bahn.de') === false) {
                continue;
            }

            if (strpos($textPdf, 'Ihre Reiseverbindung') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        $this->assignLangHTML();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Deutschen Bahn'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('wie folgt gebucht:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Leistungen'))}]")->length > 0) {
            $this->html = true;

            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            $this->html = true;
        }

        $this->pdf = clone $this->http;

        if ($this->html === false) {
            foreach ($pdfs as $pdf) {
                $this->pdfBody = $parser->getAttachmentBody($pdf);

                if (($html = \PDF::convertToHtml($this->pdfBody, \PDF::MODE_SIMPLE)) === null) {
                    continue;
                }

                $this->pdf->SetEmailBody($html);

                $body = $this->pdf->Response['body'];
                $body = str_replace("&#160;", " ", $body);

                if (!$this->assignLang($body)) {
                    continue;
                }

                if (strpos($body, 'Ihre Reiseverbindung') === false) {
                    continue;
                }

                $this->subject = $parser->getSubject();

                $this->parsePdf($email, $parser->getSubject());
            }
        } else {
            $this->assignLangHTML();
            $this->parseHtml($email);
        }

        $email->setType('OnlineTicketPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePdf(Email $email, string $subject): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $text = text($this->pdf->Response['body']);
        $r = $email->add()->train();

        $recordLocator = $this->re("#Auftrag\s+\([A-Z]{3}\)\s*:\s*([A-Z\d]{5,})#", $text);

        if (empty($recordLocator)) {
            $recordLocator = $this->re("#Auftragsnummer\s*:\s*([A-Z\d]{5,})#", $text);
        }

        if (empty($recordLocator)) {
            $recordLocator = $this->re('/\n([A-Z\d]{5,}).*\nSeite \d+/u', $text);
        }

        if (empty($recordLocator)) {
            $recordLocator = $this->re('/Order\s*No\.\s*([A-Z\d]{6})$/u', $this->subject);
        }

        $r->general()
            ->confirmation($recordLocator, 'Auftragsnummer', true);

        $travellers = array_unique($this->pdf->FindNodes("//text()[contains(.,'Auftragsnummer') or contains(.,'Die Firmen-Kreditkarte wurde')][not(./preceding::*[contains(.,'THIS IS NOT A TICKET!')])]/preceding-sibling::text()[{$this->starts($this->t('namePrefixes'))}][1]"));

        if (count($travellers) === 0) {
            $traveller = $this->pdf->FindSingleNode("//text()[contains(.,'Auftrag (NVS):')]/preceding-sibling::text()[1]", null, false, '/^([[:alpha:]\s,]+)$/u');

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[contains(.,'Datum')]/preceding-sibling::text()[1]", null, false, '/^([[:alpha:]\s,]+)$/u');
            }

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[normalize-space()='Zangenabdruck']/following::text()[normalize-space()='GL']/following::text()[not(contains(normalize-space(), 'Kreditkartenzahlung'))][1]", null, false, '/^([[:alpha:]\s,]+)$/u');
            }

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[normalize-space()='Zangenabdruck']/following::text()[{$this->starts($this->t('namePrefixes'))}][1]", null, true, "/^\s*{$this->opt($this->t('namePrefixes'))}\s*([[:alpha:]\s,]+)$/u");
            }

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[contains(.,'Auftragsnummer') or contains(.,'Die Firmen-Kreditkarte wurde')][not(./preceding::*[contains(.,'THIS IS NOT A TICKET!')])]/preceding-sibling::text()[normalize-space()][1]", null, true, "/^\s*([[:alpha:]\s\-]+)$/u");
            }

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[normalize-space()='Zangenabdruck']/following::text()[contains(normalize-space(), 'Gesamtpreis')][1]/preceding::text()[normalize-space()][1]", null, true, "/^\s*([[:alpha:]\s\-]+)$/u");
            }

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[normalize-space()='Zangenabdruck']/following::text()[normalize-space()][not(contains(normalize-space(), 'Ticket'))][1]", null, true, "/^\s*([[:alpha:]\s\-]+)$/u");
            }

            if (empty($traveller)) {
                $traveller = $this->pdf->FindSingleNode("//text()[contains(.,'Auftragsnummer')]/preceding-sibling::text()[normalize-space()][not({$this->contains($this->t('previous'))})][1]", null, true, "/^\s*([[:alpha:]\s\-]+)$/u");
            }

            if ($traveller) {
                $travellers = [$traveller];
            }
        }

        if (count($travellers) === 0) {
            $traveller = $this->pdf->FindSingleNode("//text()[contains(.,'Kreditkartenzahlung')]/following::text()[normalize-space()][1]", null, false, '/^([[:alpha:]\s,]+)$/u')
                ?? $this->pdf->FindSingleNode("//text()[contains(.,'. Die Buchung')]/following-sibling::text()[1]", null, false, '/^\s*([[:upper:]\s]{5,30})\s*$/u')
                ?? $this->pdf->FindSingleNode("//text()[contains(.,'. Die Buchung')]/preceding-sibling::text()[1]", null, false, '/^\s*([[:upper:]\s]{5,30})\s*$/u')
                ?? preg_match("/Ticket für\s+({$patterns['travellerName']})\s*,\s*Auftragsnummer/u", $subject, $m) && stripos($text, $m[1]) !== false ? $m[1] : null
            ;

            if ($traveller) {
                $travellers = [$traveller];
            }
        }

        if (count($travellers) > 0) {
            $r->general()->travellers(preg_replace("/^(?:{$this->opt($this->t('namePrefixes'))}\s*)+\b(.+)$/u", '$1', $travellers), true);
        }

        $ticket = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(), 'Belegnummer:')]/following::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");

        if (!empty($ticket)) {
            $r->setTicketNumbers([$ticket], false);
        }
        $this->ParsePDFTicket($r, $this->pdfBody, true, $email);
    }

    private function parseHtml(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Auftragsnummer'))}]", null, true, "/{$this->opt($this->t('Auftragsnummer'))}\:?\s*(\d{8,})/u"));

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('in Höhe von'))}]", null, true, "/{$this->opt($this->t('in Höhe von'))}\s*([\d\.\,]+\s*[A-Z]{3})/");

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
            $t->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Leistungen'))}]/ancestor::table[1]/descendant::tr[normalize-space()][not({$this->contains($this->t('Leistungen'))})]");

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $segText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/\s*(?<cabin>\d+\.\s*Klasse)\s*{$this->opt($this->t('von'))}\s*(?<depName>.+)\,\s+(?<depDate>\d+\.\d+\.\d{4}\s*[\d\:]+)\s*(?:Uhr)?\s*{$this->opt($this->t('nach'))}\s*(?<arrName>.+)\,\s+(?<arrDate>\d+\.\d+\.\d{4}\s*[\d\:]+)\s*(?:Uhr)?/", $segText, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate']));

                $s->arrival()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate']));

                $s->setCabin($m['cabin']);

                $s->setNoNumber(true);
            }
        }
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangHTML(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                    $this->logger->debug($lang);
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($s) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '/'));
    }
}
