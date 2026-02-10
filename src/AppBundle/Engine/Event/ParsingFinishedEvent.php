<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/04/2018
 * Time: 17:44
 */

namespace AppBundle\Event;


use AppBundle\Document\CheckAccount;
use Symfony\Component\EventDispatcher\Event;

class ParsingFinishedEvent extends Event
{
    public const NAME = 'aw.parsing_finished';

}