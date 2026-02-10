<?php

namespace AwardWallet\Engine\chase;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter Identification Code which was sent to the following email address");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
