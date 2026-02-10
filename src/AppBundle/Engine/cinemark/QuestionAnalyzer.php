<?php

namespace AwardWallet\Engine\cinemark;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A code has been sent ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
