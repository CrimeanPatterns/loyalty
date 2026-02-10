<?php

namespace AppBundle\Model\Resources;

class ExtensionAnswersConverter
{

    /**
     * @param Answer[] $answers
     * @return array
     */
    public static function convert(iterable $answers) : array
    {
        $result = [];
        foreach ($answers as $answer) {
            $result[$answer->getQuestion()] = $answer->getAnswer();
        }

        return $result;
    }

}