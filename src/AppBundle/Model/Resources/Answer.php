<?php

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Generated resource DTO for 'Answer'.
 * @MongoDB\EmbeddedDocument
 */
class Answer
{
    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $question;

    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    private $answer;

    public function __construct($question = null, $answer = null)
    {
        $this->question = $question;
        $this->answer = $answer;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setQuestion($question)
    {
        $this->question = $question;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setAnswer($answer)
    {
        $this->answer = $answer;

        return $this;
    }

    /**
     * @return string
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * @return string
     */
    public function getAnswer()
    {
        return $this->answer;
    }

    public function clear()
    {
        $this->question = trim($this->question);
        $this->answer = trim($this->answer);
    }

    public function validate(): bool
    {
        return !(strlen($this->question) > 0 xor strlen($this->answer) > 0);
    }
}
