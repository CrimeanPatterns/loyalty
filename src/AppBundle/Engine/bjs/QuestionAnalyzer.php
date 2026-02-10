<?php

namespace AwardWallet\Engine\bjs;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the 6-digit authentication code which was sent to");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
