<?php

namespace App\MessageHandler;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use App\Message\TourMessage;
use App\Manager\ProcessManager;

/**
 * @AsMessageHandler
 */
class TourMessageHandler implements MessageHandlerInterface
{

    /**
     * @var ProcessManager
     */
    private $processManager;

    /**
     * Constructor
     * 
     * @param ProcessManager $processManager
     */
    public function __construct(ProcessManager $processManager)
    {
        $this->processManager = $processManager;
    }

    public function __invoke(TourMessage $message)
    {
        $this->processManager->proccessTourMessage($message);
    }

}