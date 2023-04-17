<?php
/**
 * This class handles the command for the synchronization of OpenBelasting aanslagbiljetten.
 *
 * This Command executes the zaakTypeService->zaakTypeHandler.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Command
 */

namespace CommonGateway\OpenBelastingBundle\Command;

use CommonGateway\OpenBelastingBundle\Service\SyncAanslagenService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SyncAanslagenCommando extends Command
{

    /**
     * The actual command.
     *
     * @var static $defaultName
     */
    protected static $defaultName = 'openbelasting:aanslagbiljet:synchronize';

    /**
     * The sync aanslagbiljet service.
     *
     * @var SyncAanslagenService
     */
    private SyncAanslagenService $syncAanslagenService;


    /**
     * Class constructor.
     *
     * @param SyncAanslagenService $syncAanslagenService The sync aanslagbiljet service
     */
    public function __construct(SyncAanslagenService $syncAanslagenService)
    {
        $this->syncAanslagenService = $syncAanslagenService;
        parent::__construct();

    }//end __construct()


    /**
     * Configures this command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command triggers OpenBelasting syncAanslagenService')
            ->setHelp('This command triggers OpenBelasting syncAanslagenService');

    }//end configure()


    /**
     * Executes this command.
     *
     * @param InputInterface  $input  Handles input from cli
     * @param OutputInterface $output Handles output from cli
     *
     * @return int 0 for failure, 1 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->syncAanslagenService->syncAanslagenHandler() === null) {
            return Command::FAILURE;
        }//end if

        return Command::SUCCESS;

    }//end execute()


}//end class
