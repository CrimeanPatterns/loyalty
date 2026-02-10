<?php

namespace Tests\Unit;

use AppBundle\Model\Resources\Answer;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;

/**
 * @backupGlobals disabled
 */
class SequrityQuestionsTest extends BaseWorkerTestClass {

    public function testAskQuestion() {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('question')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_QUESTION, $response->getState());
    }

    public function testSuccessQuestionAnswer() {
        $answer = new Answer();
        $answer->setQuestion("What is your mother's middle name (answer is Petrovna)?")
               ->setAnswer("Petrovna");

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('question')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setAnswers([$answer]);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
    }

    public function testInvalidAnswers() {
        $myQuestion = "What is your mother's middle name (answer is Petrovna)?";
        $myAnswer = "Ivanovna";
        $answer = new Answer();
        $answer->setQuestion($myQuestion)
               ->setAnswer($myAnswer);

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('question')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setAnswers([$answer]);

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_QUESTION, $response->getState());
        $this->assertEquals(1, count($response->getInvalidanswers()));
        /** @var Answer $invalidAnswer */
        $invalidAnswer = $response->getInvalidanswers()[0];
        $this->assertInstanceOf(Answer::class, $invalidAnswer);
        $this->assertEquals($myQuestion, $invalidAnswer->getQuestion());
        $this->assertEquals($myAnswer, $invalidAnswer->getAnswer());
    }

    public function testAskQuestionLong()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setLogin('question.long')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
        $this->assertStringContainsString('Question is too long:', $response->getDebuginfo());
    }

}
