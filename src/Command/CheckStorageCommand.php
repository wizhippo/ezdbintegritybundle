<?php

namespace TanoConsulting\eZDBIntegrityBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TanoConsulting\DataValidatorBundle\Command\ValidateCommand;
use TanoConsulting\DataValidatorBundle\ContainerConstraintValidatorFactory;
use TanoConsulting\DataValidatorBundle\DatabaseValidatorBuilder;
use TanoConsulting\DataValidatorBundle\FilesystemValidatorBuilder;
use TanoConsulting\DataValidatorBundle\Mapping\Loader\Database\TaggedServiceLoader;

class CheckStorageCommand extends ValidateCommand
{
    protected static $defaultName = 'ezdbintegrity:check:storage';

    protected $container;

    public function __construct(EventDispatcherInterface $eventDispatcher = null, TaggedServiceLoader $taggedServicesLoader = null,
        ContainerConstraintValidatorFactory $constraintValidatorFactory, LoggerInterface $datavalidatorLogger = null, ContainerInterface $container = null)
    {
        $this->container = $container;

        parent::__construct($eventDispatcher, $taggedServicesLoader, $constraintValidatorFactory, $datavalidatorLogger);
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Checks orphan storage files (those which are in the storage dir but not in the database)')
            ->addoption('check-db-orphans', null, InputOption::VALUE_NONE, 'Reverses the check: look for files present in the database but not on disk')
            //->addOption('config-file', null, InputOption::VALUE_REQUIRED, 'A yaml/json file defining the constraints to check. If omitted: load them from config/services')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return parent::execute($input, $output);
    }

    protected function setLogger(LoggerInterface $logger)
    {
        parent::setLogger($logger);

        $this->container->get('TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem\eZBinaryFileAndMediaValidator')->setLogger($logger);
        $this->container->get('TanoConsulting\eZDBIntegrityBundle\Constraints\Filesystem\eZImageFileValidator')->setLogger($logger);

        $this->container->get('TanoConsulting\eZDBIntegrityBundle\Constraints\Database\eZBinaryFileValidator')->setLogger($logger);
        $this->container->get('TanoConsulting\eZDBIntegrityBundle\Constraints\Database\eZImageFileValidator')->setLogger($logger);
        $this->container->get('TanoConsulting\eZDBIntegrityBundle\Constraints\Database\eZMediaValidator')->setLogger($logger);
    }

    protected function getValidatorBuilder($input)
    {
        if ($input->getOption('check-db-orphans')) {
            $validatorBuilder = new DatabaseValidatorBuilder();
            $validatorBuilder->addFileMapping(__DIR__ . '/../../config/constraints/storage_db.yaml');
        } else {
            $validatorBuilder = new FilesystemValidatorBuilder();
            $validatorBuilder->addFileMapping(__DIR__ . '/../../config/constraints/storage_fs.yaml');
        }

        /// @todo allow more flexibility in loading further constraints, from both config files and services
        /*if ($configFile = $input->getOption('config-file')) {
            $validatorBuilder->addFileMapping($configFile);
        } else {
            $validatorBuilder->addLoader($this->taggedServicesLoader);
        }*/

        return $validatorBuilder;
    }

    protected function getValidationTarget($input)
    {
        $connection = $this->container->get('ezpublish.persistence.connection');

        return $connection;
    }
}
