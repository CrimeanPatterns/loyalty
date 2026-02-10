<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "azul/it-10044390.eml, azul/it-4420063.eml, azul/it-4428355.eml, azul/it-6994874.eml, azul/it-7081559.eml";

    public $reBody = [
        'pt' => ['Cartão de Embarque', 'Bilhete'], //pdf
    ];

    /** @var \HttpBrowser */
    public $pdf;

    public $lang = '';

    public static $dict = [
        'pt' => [
            //Pdf
            'Record locator' => 'Código Localizador',
            'Ticket number'  => 'Cartão de Embarque',
            'Passenger'      => 'Nome do passageiro',
            'Total cost'     => 'Total da Compra',
            'Seat'           => 'Poltrona',
            'Depart'         => 'Partida',
            'Arrive'         => 'Chegada',
            'Total'          => ['Total da Compra', 'Total da compra'],
            //html
            "PassengerH" => "Passageiro",
            //			"Aprovado" => "",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                }
            }
            $this->pdf->SetBody($html);
            $body = $this->pdf->Response['body'];

            if ($this->assignLang($body)) {
                $this->parseEmailPdf($email);
            }
        } else {
            $this->logger->debug("go to parse by other parsers");
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Azul') !== false) && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'voeazul.com.br') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "voeazul.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf(Email $email)
    {
        // some data from html

        $r = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Código Localizador') and not(descendant::td)]/following-sibling::td[1]",
            null, true, '/([A-Z\d]{5,8})/');

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Código da Reserva')]/following-sibling::*[1]");
        }

        $r->general()
            ->confirmation($confNo)
            ->travellers(array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[contains(text(),'" . $this->t('Passenger') . "')]/following-sibling::p[1]")));

        $accNum = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space() = '" . $this->t('PassengerH') . "']/ancestor::tr[1][normalize-space(td[2]) = '" . $this->t('Nº TudoAzul') . "']/following-sibling::tr/td[2]",
            null, "#.*\d+.*#")));

        if (!empty($accNum)) {
            $r->program()->accounts($accNum, false);
        }

        $total = $this->http->FindSingleNode("//table[contains(@style,'background-color: #FF75B8; color: #fff;')]//p[@style='margin: 0 10px 0 0; font: HelveticaNeueBold;']");

        if (!empty($total)) {
            if (preg_match("#(.+\s+[0-9\.\,]+)#", $total, $m)) {
                $m = $this->getTotalCurrency($m[1]);
                $r->price()
                    ->currency($m['currency'])
                    ->total($m['total']);
            }
        } else {
            $totals = $this->http->FindNodes("//text()[{$this->contains($this->t('Total'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Aprovado'))}]/td[last()]");
            $sum = 0.0;

            foreach ($totals as $total) {
                if (preg_match("#(.+\s+[0-9\.\,]+)#", $total, $m)) {
                    $mm = $this->getTotalCurrency($m[1]);
                    $currency = $mm['currency'];
                    $sum += $mm['total'];
                }
            }

            if (!empty($sum) && isset($currency)) {
                $r->price()
                    ->currency($currency)
                    ->total($sum);
            }
        }

        $flightNum = array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[normalize-space(text())='Voo']/following::p[1]"));

        foreach ($flightNum as $i => $v) {
            $s = $r->addSegment();
            $s->airline()
                ->number(trim($v));

            if ($this->http->XPath->query("//img[contains(@src, 'voeazul.com.br/AzulWebCheckin')]")->length > 0
                || $this->http->XPath->query("//td[contains(normalize-space(.), 'voeazul.com') and contains(normalize-space(.), 'Azul')]")->length > 0
                || $this->pdf->XPath->query("//text()[{$this->contains('Azul Linhas Aéreas Brasileiras')}]")->length > 0
            ) {
                $s->airline()->name('AD');
            } else {
                $s->airline()->noName();
            }

            $seats = array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[text()='" . trim($v) . "']/following-sibling::p[contains(text(),'" . $this->t('Seat') . "')]/following-sibling::p[1]"));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }

            $depDate = implode(",",
                array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[text()='" . trim($v) . "']/following-sibling::p[contains(text(),'" . $this->t('Depart') . "')]/following-sibling::p[3]")));

            $depTime = implode(",",
                array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[text()='" . trim($v) . "']/following-sibling::p[contains(text(),'" . $this->t('Depart') . "')]/following-sibling::p[1]")));
            $arrTime = implode(",",
                array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[text()='" . trim($v) . "']/following-sibling::p[contains(text(),'" . $this->t('Arrive') . "')]/following-sibling::p[1]")));
            $FlightInfo = implode(",",
                array_unique($this->pdf->FindNodes("//div[contains(@id,'page') and contains(@id,'-div')]//p[text()='" . trim($v) . "']/following-sibling::p[contains(text(),'" . $this->t('Depart') . "')]/following-sibling::p[2]")));

            if (preg_match("#(.+)\(([A-Z]{3})\)\s*-s*(.+)\(([A-Z]{3})\)#", $FlightInfo, $m)) {
                $s->departure()
                    ->name(trim($m[1]))
                    ->code(trim($m[2]));
                $s->arrival()
                    ->name(trim($m[3]))
                    ->code(trim($m[4]));
            }

            if (isset($depDate)) {
                if (isset($depTime)) {
                    $s->departure()
                        ->date(strtotime($depTime,
                            strtotime($this->dateStringToEnglish(str_replace("/", " ", strtolower($depDate))))));
                }

                if (isset($arrTime)) {
                    $s->arrival()
                        ->date(strtotime($arrTime,
                            strtotime($this->dateStringToEnglish(str_replace("/", " ", strtolower($depDate))))));
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "₹", "R$"], ["EUR", "GBP", "INR", "BRL"], $node);
        $tot = 0.0;
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t'], '.', ',');

            if (!isset($tot)) {
                $tot = 0.0;
            }
        }

        return ['total' => $tot, 'currency' => $cur];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "contains($text, \"{$s}\")";
        }, $field));
    }
}
