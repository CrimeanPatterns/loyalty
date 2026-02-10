<?php


namespace AppBundle\Worker\CheckExecutor;


use AppBundle\Document\BaseDocument;

interface ExecutorInterface
{
    public function getMethodKey(): string;

    public function execute(BaseDocument $row): void;

}