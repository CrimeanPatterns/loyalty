<?php


namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class RaAccountAnswer
{

    /** @MongoDB\Id */
    private $id;

    /** @MongoDB\Field(type="string") */
    private $question;

    /** @MongoDB\Field(type="string") */
    private $answer;

    public function __construct(string $question, string $answer)
    {
        $this->question = $question;
        $this->answer = $answer;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * @param string $question
     * @return RaAccountAnswer
     */
    public function setQuestion(string $question)
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return string
     */
    public function getAnswer()
    {
        return $this->answer;
    }

    /**
     * @param string $answer
     * @return RaAccountAnswer
     */
    public function setAnswer(string $answer)
    {
        $this->answer = $answer;
        return $this;
    }



}