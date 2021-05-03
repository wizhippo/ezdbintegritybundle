<?php

namespace TanoConsulting\eZDBIntegrityBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TanoConsulting\DataValidatorBundle\Command\ValidateCommand;
use TanoConsulting\DataValidatorBundle\ContainerConstraintValidatorFactory;
use TanoConsulting\DataValidatorBundle\DatabaseValidatorBuilder;
use TanoConsulting\DataValidatorBundle\Mapping\Loader\Database\TaggedServiceLoader;

class CheckSchemaCommand extends ValidateCommand
{
    protected static $defaultName = 'ezdbintegrity:check:schema';

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
            ->setDescription('Checks integrity of data in the database')
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
        /// @todo inject the verbosity of $output into the validator services

        return parent::execute($input, $output);
    }

    protected function getValidatorBuilder($input)
    {
        $validatorBuilder = new DatabaseValidatorBuilder();

        $validatorBuilder->addFileMapping(__DIR__ . '/../../config/constraints/schema.yaml');

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
