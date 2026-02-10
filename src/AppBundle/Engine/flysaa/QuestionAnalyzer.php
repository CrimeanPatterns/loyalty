<?php

namespace AwardWallet\Engine\flysaa;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please enter the code sent to your");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
