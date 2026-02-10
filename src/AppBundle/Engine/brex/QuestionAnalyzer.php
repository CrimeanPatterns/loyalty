<?php

namespace AwardWallet\Engine\brex;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Please copy-paste the “Trust this device” link which was sent to your email");
    }

    public static function getHoldsSession(): bool
    {
        return false;
    }
}
