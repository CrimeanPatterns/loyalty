<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;


/** @MongoDB\EmbeddedDocument */
class RetriesState
{
    /** @MongoDB\Field(type="hash") */
    private $invalidAnswers;

    /** @MongoDB\Field(type="hash") */
    private $checkerState;

    public function __construct(?array $invalidAnswers = [], ?array $checkerState = [])
    {
        $this->invalidAnswers = $invalidAnswers;
        $this->checkerState = $checkerState;
    }

    public function getInvalidAnswers(): ?array
    {
        return $this->invalidAnswers;
    }

    public function setInvalidAnswers(?array $invalidAnswers)
    {
        $this->invalidAnswers = $invalidAnswers;
        return $this;
    }

    public function getCheckerState(): ?array
    {
        return $this->checkerState;
    }

    public function setCheckerState(?array $checkerState)
    {
        $this->checkerState = $checkerState;
        return $this;
    }

}