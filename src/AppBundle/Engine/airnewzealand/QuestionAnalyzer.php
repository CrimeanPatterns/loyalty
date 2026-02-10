<?php

namespace AwardWallet\Engine\airnewzealand;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "A magic link was sent to")
            || (str_starts_with($question, "An authentication code was sent to") && strstr($question, "@"))
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
