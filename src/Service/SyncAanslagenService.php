<?php
/**
 * This services synchronizes aanslagen from a OpenBelastingen API.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\OpenBelastingBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use CommonGateway\CoreBundle\Service\CallService;
use App\Service\SynchronizationService;
use Exception;

class SyncAanslagenHandler
{

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $synchronizationService;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * The plugin logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * @param EntityManagerInterface $entityManager  The Entity Manager.
     * @param LoggerInterface        $pluginLogger   The plugin version of the logger interface.
     * @param MappingService         $mappingService MappingService.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $pluginLogger,
        SynchronizationService $synchronizationService,
        CallService $callService
    ) {
        $this->entityManager          = $entityManager;
        $this->logger                 = $pluginLogger;
        $this->synchronizationService = $synchronizationService;
        $this->callService            = $callService;
        $this->configuration          = [];
        $this->data                   = [];

    }//end __construct()


    /**
     * Synces a aanslag.
     *
     * @param array   $aanslag Aanslag from the OpenBelastingen API
     * @param Gateway $source  OpenBelasting API
     * @param Entity  $entity  Aanslag schema
     *
     * @var Synchronization
     *
     * @return void|null
     */
    public function syncAanslag(array $aanslag, Gateway $source, Entity $entity)
    {
        // Get or create sync and map object.
        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $aanslag['aanslagbiljetnummer']);
        // $synchronization->setMapping($this->mapping); // not needed probably
        $synchronization = $this->synchronizationService->synchronize($synchronization, $aanslag);

        $this->logger->success("Synced aanslag: {$aanslag['aanslagbiljetnummer']}");

        return $synchronization->getObject();

    }//end syncAanslag()


    /**
     * An example handler that is triggered by an action.
     *
     * @param array $data          The data array
     * @param array $configuration The configuration array
     *
     * @return array A handler must ALWAYS return an array
     */
    public function syncAanslagenHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => 'https://openbelasting.nl/source/openbelasting.pinkapi.source.json']);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://openbelasting.nl/schemas/openblasting.bezwaaraanvraag.schema.json']);

        if ($source === null || $entity === null) {
            return [];
        }

        $this->logger->debug("SyncAanslagenService -> syncAanslagenHandler()");
        $this->logger->debug("Fetching aanslagen");

        try {
            $fetchedAanslagen = $this->callService->getAllResults($source, '/v1/aanslagen', [], 'result.instance.rows?');
        } catch (Exception $e) {
            $this->logger->error("Failed to fetch: {$e->getMessage()}");

            return null;
        }

        $fetchedAanslagenCount = count($fetchedAanslagen);
        $this->logger->debug("Fetched $fetchedAanslagenCount casetypes");

        $syncedAanslagen = 0;
        $flushCount      = 0;
        foreach ($fetchedAanslagen as $fetchedAanslag) {
            if ($this->syncAanslag($fetchedAanslag, $source, $entity)) {
                $fetchedAanslagenCount = ($fetchedAanslagenCount + 1);
                $flushCount            = ($flushCount + 1);
            }//end if

            // Flush every 20.
            if ($flushCount == 20) {
                $this->entityManager->flush();
                $flushCount = 0;
            }//end if
        }//end foreach

        $this->logger->debug("Synced $flushCount aanslagen from the $syncedAanslagen fetched casetypes");

        return ['response' => []];

    }//end syncAanslagenHandler()


}//end class
