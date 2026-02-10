<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class HistoryColumn
{

    public const POSTING_DATE_COLUMN = 'PostingDate';
    public const DESCRIPTION_COLUMN = 'Description';
    public const CATEGORY_COLUMN = 'Category';
    public const AMOUNT_COLUMN = 'Amount';
    public const MILES_COLUMN = 'Miles';
    public const AMOUNT_BALANCE_COLUMN = 'AmountBalance';
    public const MILES_BALANCE_COLUMN = 'MilesBalance';
    public const CURRENCY_COLUMN = 'Currency';
    public const BONUS_COLUMN = 'Bonus';
    public const INFO_COLUMN = 'Info';
    public const INFO_COLUMN_STRING = 'Info.String';
    public const INFO_COLUMN_INT = 'Info.Int';
    public const INFO_COLUMN_DECIMAL = 'Info.Decimal';
    public const INFO_COLUMN_DATE = 'Info.Date';
    public const INFO_KINDS = [
        self::INFO_COLUMN_STRING, self::INFO_COLUMN_INT, self::INFO_COLUMN_DECIMAL, self::INFO_COLUMN_DATE
    ];

    private const TYPE_MAPPING = [
        self::POSTING_DATE_COLUMN => 'date',
        self::DESCRIPTION_COLUMN => 'string',
        self::CATEGORY_COLUMN => 'string',
        self::AMOUNT_COLUMN => 'decimal',
        self::MILES_COLUMN => 'decimal',
        self::AMOUNT_BALANCE_COLUMN => 'decimal',
        self::MILES_BALANCE_COLUMN => 'decimal',
        self::CURRENCY_COLUMN => 'string',
        self::BONUS_COLUMN => 'decimal',
        self::INFO_COLUMN => 'string',
        self::INFO_COLUMN_STRING => 'string',
        self::INFO_COLUMN_INT => 'integer',
        self::INFO_COLUMN_DECIMAL => 'decimal',
        self::INFO_COLUMN_DATE => 'date',
    ];

    /**
     * @var string
     * @Type("string")
     */
    private $type;

    /**
     * @var string
     * @Type("string")
     */
    private $kind;

    /**
     * @var string
     * @Type("string")
     */
    private $name;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $isHidden;

    public function __construct(string $name, string $kind, string $type)
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->type = $type;
    }

    public static function createFromTAccountCheckerDefinition(string $name, string $kind): self
    {
        $columnKind = in_array($kind, self::INFO_KINDS) ? self::INFO_COLUMN : $kind;
        return new self($name, $columnKind, self::TYPE_MAPPING[$kind]);
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function isHidden(): ?bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(bool $isHidden): self
    {
        $this->isHidden = $isHidden;
        return $this;
    }

}
