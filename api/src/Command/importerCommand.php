<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Manager\ProcessManager;

class importerCommand extends Command
{

    protected static $defaultName = 'importer:import';
    
    /**
     * @var ProcessManager
     */
    protected $processManager;
    
    public function __construct(ProcessManager $processManager)
    {
        $this->processManager = $processManager;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setDescription('Import all tours.')
            ->setHelp('This command allows you to import all new tours from importer.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $io = new SymfonyStyle($input, $output);
        $io->newLine(1);
        $output->writeln('<info>Start import:</>');
        $io->newLine(1);

        $this->processManager->import($output);

        $io->newLine(2);
        $output->writeln('<comment>Import is finish!</>');
        $io->newLine(1);

        return 1;

    }
    
}