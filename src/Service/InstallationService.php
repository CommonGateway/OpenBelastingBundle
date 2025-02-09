<?php
/**
 * The installation service
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\OpenBelastingBundle\Service;

use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;


class InstallationService implements InstallerInterface
{

    /**
     * The entity manager
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    
    /**
     * The installation logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * The constructor
     *
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param LoggerInterface $installationLogger The installation logger.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $installationLogger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $installationLogger;

    }//end __construct()


    /**
     * Every installation service should implement an install function
     *
     * @return void
     */
    public function install(): void
    {
        $this->logger->debug("OpenBelastingBundle -> Install()");
        
        $this->checkDataConsistency();

    }//end install()


    /**
     * Every installation service should implement an update function
     *
     * @return void
     */
    public function update(): void
    {
        $this->logger->debug("OpenBelastingBundle -> Update()");
        
        $this->checkDataConsistency();

    }//end update()


    /**
     * Every installation service should implement an uninstall function
     *
     * @return void
     */
    public function uninstall(): void
    {
        $this->logger->debug("OpenBelastingBundle -> Uninstall()");
        
        // Do some cleanup to uninstall correctly...
    }//end uninstall()


    /**
     * The actual code run on update and installation of this bundle
     *
     * @return void
     */
    public function checkDataConsistency()
    {
        // This is the place where you can add or change Installation data from/for this bundle or other required bundles.
        // Note that in most cases it is recommended to use .json files in the Installation folder instead, if possible.
        
        $this->entityManager->flush();

    }//end checkDataConsistency()


}//end class
