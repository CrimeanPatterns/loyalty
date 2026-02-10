<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkAttractions extends \TAccountChecker
{
    public $mailFiles = "booking/it-78511018.eml";

    private $lang;
    private static $dictionary = [
        'en' => [
            'See all'                             => 'See all',
            'attractions'                         => 'attractions',
            'Iconic'                              => 'Iconic',
            'Attractions you won\'t want to miss' => 'Attractions you won\'t want to miss',
        ],
        'pt' => [
            'See all'                             => 'Ver todas as',
            'attractions'                         => 'atrações',
            'Iconic'                              => ['Imperdíveis de', 'Imprescindível em'],
            'Attractions you won\'t want to miss' => ['Atrações que você não pode perder', 'Atrações que não vai querer perder'],
        ],
        'es' => [
            'See all'                             => 'Ver todas',
            'attractions'                         => 'atracciones turísticas',
            'Iconic'                              => 'Los imprescindibles de',
            'Attractions you won\'t want to miss' => 'Atracciones turísticas que no te querrás perder',
        ],
        'it' => [
            'See all'                             => 'Vedi tutte',
            'attractions'                         => 'le attrazioni',
            'Iconic'                              => 'da vivere',
            'Attractions you won\'t want to miss' => 'Attrazioni da non perdere',
        ],
        'ro' => [
            'See all'                             => 'Vedeți toate',
            'attractions'                         => 'atracțiile',
            'Iconic'                              => 'De neratat în',
            'Attractions you won\'t want to miss' => 'Atracții pe care vreţi să nu le rataţi',
        ],
        'hu' => [
            'See all'                             => 'Lássam az',
            'attractions'                         => 'összes látnivalót',
            'Iconic'                              => 'élményei',
            'Attractions you won\'t want to miss' => 'Átélni való látnivalók',
        ],
        'zh' => [
            'See all'                             => '查看全部',
            'attractions'                         => '個景點',
            'Iconic'                              => '必去景點',
            'Attractions you won\'t want to miss' => '超多推薦景點，千萬別錯過',
        ],
        'fr' => [
            'See all'                             => 'Voir toutes',
            'attractions'                         => 'les attractions',
            'Iconic'                              => 'et ses incontournables',
            'Attractions you won\'t want to miss' => 'Les attractions à ne pas manquer',
        ],
        'pl' => [
            'See all'                             => 'Zobacz wszystkie',
            'attractions'                         => 'atrakcje',
            'Iconic'                              => 'Słynne miejsca w mieście',
            'Attractions you won\'t want to miss' => 'Atrakcje, których nie możesz przegapić',
        ],
        'de' => [
            'See all'                             => 'Alle',
            'attractions'                         => 'Attraktionen ansehen',
            'Iconic'                              => 'Kultiges',
            'Attractions you won\'t want to miss' => 'Attraktionen, die Sie sich nicht entgehen lassen sollten',
        ],
        'fi' => [
            'See all'                             => 'Näytä kaikki',
            'attractions'                         => 'nähtävyydet',
            'Iconic'                              => 'Ikoninen',
            'Attractions you won\'t want to miss' => 'Nähtävyyksiä, joissa haluat varmasti käydä',
        ],
        'da' => [
            'See all'                             => 'Se alle',
            'attractions'                         => 'seværdigheder',
            'Iconic'                              => 'Ikoniske',
            'Attractions you won\'t want to miss' => 'Seværdigheder du ikke vil gå glip af',
        ],
        'el' => [
            'See all'                             => 'Δείτε όλα',
            'attractions'                         => 'τα αξιοθέατα',
            'Iconic'                              => 'Κορυφαία στη',
            'Attractions you won\'t want to miss' => 'Αξιοθέατα που δεν πρέπει να χάσετε',
        ],
        'nl' => [
            'See all'                             => 'Bekijk alle',
            'attractions'                         => 'attracties',
            'Iconic'                              => 'Echt',
            'Attractions you won\'t want to miss' => 'Attracties die je niet wilt missen',
        ],
        'ru' => [
            'See all'                             => 'Посмотреть все',
            'attractions'                         => 'варианты досуга',
            'Iconic'                              => 'Легендарные места в',
            'Attractions you won\'t want to miss' => 'Эти варианты досуга нельзя пропустить',
        ],
        'sl' => [
            'See all'                             => 'Prikaži vse',
            'attractions'                         => 'znamenitosti',
            'Iconic'                              => 'Ikonično mesto:',
            'Attractions you won\'t want to miss' => 'Znamenitosti, ki jih ne želite zamuditi',
        ],
        'sv' => [
            'See all'                             => 'Se alla',
            'attractions'                         => 'sevärdheter',
            'Iconic'                              => 'Ikoniska',
            'Attractions you won\'t want to miss' => 'Sevärdheter du inte vill missa',
        ],
        'cs' => [
            'See all'                             => 'Zobrazit všechny',
            'attractions'                         => 'atrakce',
            'Iconic'                              => 'Ikonická místa',
            'Attractions you won\'t want to miss' => 'Turistické atrakce, které byste neměl/a vynechat',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectBody() == true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['See all']) || empty($dict['attractions']) || empty($dict['Iconic']) || empty($dict['Attractions you won\'t want to miss'])) {
                continue;
            }

            if (
                $this->http->XPath->query("//a[contains(@href, '.booking.com') and " . $this->starts($dict['See all']) . " and " . $this->contains($dict['attractions']) . "]")->length > 0
                and $this->http->XPath->query("//text()[" . $this->contains($dict['Iconic']) . "]/following::text()[normalize-space()][1][" . $this->contains($dict['Attractions you won\'t want to miss']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
