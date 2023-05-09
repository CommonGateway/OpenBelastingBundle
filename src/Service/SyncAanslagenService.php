<?php
/**
 * This services synchronizes aanslagen from a OpenBelastingen API.
 *
 * @author  Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\OpenBelastingBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use CommonGateway\CoreBundle\Service\CallService;
use App\Service\SynchronizationService;
use Exception;
use App\Entity\Gateway;
use App\Entity\Entity;
use DateTime;
use DateInterval;

class SyncAanslagenService
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
    private function syncAanslag(array $aanslag, Gateway $source, Entity $entity)
    {
        $sourceId = $aanslag['aanslagbiljetnummer'].'-'.$aanslag['aanslagbiljetvolgnummer'];
        // Get or create sync and map object.
        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $sourceId);
        // $synchronization->setMapping($this->mapping); // not needed probably
        $synchronization = $this->synchronizationService->synchronize($synchronization, $aanslag, true);
        $this->entityManager->persist($synchronization->getObject());

        $this->logger->info("Synced aanslag: $sourceId");

        return $synchronization->getObject()->toArray();

    }//end syncAanslag()


    /**
     * Synchronizes fetched aanslagen from openbelastingen api to a gateway aanslag.
     *
     * @param array   $fetchedAanslagen Fetched aanslagen from openbelastingen api.
     * @param Gateway $source           OpenBelasting API.
     * @param Entity  $entity           Aanslagbiljet entity.
     *
     * @return array $syncedAanslagen
     */
    private function syncAanslagen(array $fetchedAanslagen, Gateway $source, Entity $entity): array
    {
        $syncedAanslagen      = [];
        $syncedAanslagenCount = 0;
        $flushCount           = 0;
        foreach ($fetchedAanslagen as $fetchedAanslag) {
            if ($syncedAanslag = $this->syncAanslag($fetchedAanslag, $source, $entity)) {
                $syncedAanslagenCount = ($syncedAanslagenCount + 1);
                $flushCount           = ($flushCount + 1);
                $syncedAanslagen[]    = $syncedAanslag;
            }//end if

            // Flush every 20.
            if ($flushCount == 20) {
                $this->entityManager->flush();
                $flushCount = 0;
            }//end if
        }//end foreach

        // Flush if we have some aanslagen left
        if ($flushCount > 0) {
            $this->entityManager->flush();
            $flushCount = 0;
        }//end if

        $this->logger->debug("Synced $flushCount aanslagen from the $syncedAanslagenCount fetched aanslagen");

        return $syncedAanslagen;

    }//end syncAanslagen()


    /**
     * Fetches aanslagen from openbelastingen api.
     *
     * @param Gateway $source OpenBelasting API.
     * @param string  $bsn    Dutch burgerservicenummer to fetch aanslagen for.
     *
     * @return array $fetchedAanslagen
     */
    private function fetchAanslagen(Gateway $source, string $bsn): array
    {
        $endpoint = '/v1/aanslagen';
        $dateTime = new DateTime('-4Y');
        $dateTime->add(DateInterval::createFromDateString('-4 year'));
        $fourYearsAgo = $dateTime->format('Y');
        $query        = [
            'bsn'                 => $bsn,
            'belastingjaar-vanaf' => $fourYearsAgo,
        ];
        try {
            $fetchedAanslagen = $this->callService->getAllResults($source, $endpoint, ['query' => $query], 'result.instance.rows?');
        } catch (Exception $e) {
            $this->logger->error("Failed to fetch: {$e->getMessage()}");

            return [];
        }

        $fetchedAanslagenCount = count($fetchedAanslagen);
        $this->logger->debug("Fetched $fetchedAanslagenCount aanslagen");

        return $fetchedAanslagen;

    }//end fetchAanslagen()


    /**
     * Fetches aanslagbiljetten from the openbelastingen api with given bsn and synchronizes them in the gateway.
     *
     * @param string $bsn Dutch burgerservicenummer to fetch aanslagen for.
     *
     * @return array $syncedAanslagen
     **/
    public function fetchAndSyncAanslagen(string $bsn): array
    {

        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => 'https://openbelasting.nl/source/openbelasting.pinkapi.source.json']);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://openbelasting.nl/schemas/openblasting.aanslagbiljet.schema.json']);

        if ($source === null || $entity === null) {
            $this->logger->error("Source or entity not found");
            return [];
        }

        $this->logger->debug("SyncAanslagenService -> syncAanslagenHandler()");

        $fetchedAanslagen = $this->fetchAanslagen($source, $bsn);

        return $this->syncAanslagen($fetchedAanslagen, $source, $entity);

    }//end fetchAndSyncAanslagen()


    /**
     * An example handler that is triggered by an action.
     *
     * @param array|null $data          The data array
     * @param array|null $configuration The configuration array
     *
     * @return array A handler must ALWAYS return an array
     */
    public function syncAanslagenHandler(?array $data=[], ?array $configuration=[]): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        return ['response' => $this->fetchAndSyncAanslagen('123412341')];

    }//end syncAanslagenHandler()


}//end class
