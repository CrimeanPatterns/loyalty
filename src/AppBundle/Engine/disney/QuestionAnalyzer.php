<?php

namespace AwardWallet\Engine\disney;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We sent a code to ");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
