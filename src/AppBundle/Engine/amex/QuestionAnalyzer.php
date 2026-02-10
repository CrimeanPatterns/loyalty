<?php

namespace AwardWallet\Engine\amex;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter a temporary security code which was sent to ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
