<?php

namespace AwardWallet\Engine\ctmoney;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter 6-Digit Code which was sent to the ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
