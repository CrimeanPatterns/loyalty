<?php
/**
 * Created by PhpStorm.
 * User: rshakirov.
 */

namespace AwardWallet\Engine\azul\Email;

use PlancakeEmailParser;

class AirReservation extends \TAccountChecker
{
    public $mailFiles = "azul/it-12091364.eml";

    private $detects = [
        'Obrigado por utilizar o Portal de Concessão de Passagens da Azul!',
    ];

    private $from = '/[.@]voeazul\.com\.br/';

    private $lang = 'pt';

    private $prov = 'Azul';

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//node()[normalize-space(.)='Localizador:']/following-sibling::node()[normalize-space(.)][1]");

        $node = $this->http->FindSingleNode("//b[contains(normalize-space(.), 'Total')]");
        $node = str_replace(chr(194) . chr(160), ' ', $node);

        if (preg_match('/Total\s+(\S+)\s+([\d\,]+)/', $node, $m)) {
            $it['Currency'] = str_replace(['R$'], ['BRL'], $m[1]);
            $it['TotalCharge'] = str_replace(',', '.', $m[2]);
        }

        $it['Passengers'] = $this->http->FindNodes("//p[normalize-space(.)='Passageiros']/following::table[1]/descendant::tr[normalize-space(.)][not(.//tr)]", null, '/\d+\s+(.+)\s+[\[A-Z\]]+/');

        $xpath = "//p[normalize-space(.)='Trechos']/following::table[1]/descendant::tr[count(td)>=5]/td[3]/text()";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $i++;

            if (preg_match('/([A-Z]{3})\s*-\s*([A-Z]{3})/', $root->nodeValue, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }

            if (preg_match('/(\d+)/', $this->http->FindSingleNode("ancestor::td[1]/preceding-sibling::td[1]/descendant::text()[{$i}]", $root), $m)) {
                $seg['FlightNumber'] = $m[1];
                $seg['AirlineName'] = AIRLINE_UNKNOWN;
            }

            $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("ancestor::td[1]/following-sibling::td[1]/text()[{$i}]", $root));

            $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("ancestor::td[1]/following-sibling::td[2]/text()[{$i}]", $root));

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate(?string $str)
    {
        $in = [
            '/\w+\s+\w+\s+(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+(\d{1,2}:\d{2})/',
        ];
        $out = [
            '$2.$1.$3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }
}
