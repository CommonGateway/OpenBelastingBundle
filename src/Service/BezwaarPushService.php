<?php
/**
 * An example service for adding business logic to your class.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\OpenBelastingBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use CommonGateway\CoreBundle\Service\CallService;
use App\Service\SynchronizationService;
use App\Entity\Gateway;
use App\Entity\Synchronization;
use CommonGateway\CoreBundle\Service\MappingService;
use Exception;

class BezwaarPushService
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
     * @var MappingService
     */
    private MappingService $mappingService;

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
        MappingService $mappingService,
        CallService $callService
    ) {
        $this->entityManager          = $entityManager;
        $this->logger                 = $pluginLogger;
        $this->synchronizationService = $synchronizationService;
        $this->mappingService            = $mappingService;
        $this->callService            = $callService;
        $this->configuration          = [];
        $this->data                   = [];

    }//end __construct()

    /**
     * Sends bezwaar to open belastingen api
     * 
     * @param Gateway $source
     * @param array $bezwaar
     * 
     * @return array if went wrong
     */
    private function sendBezwaar(Gateway $source, array $bezwaar, Synchronization $synchronization): ?array
    {
        // Send the POST request to pink.
        try {
            $response = $this->callService->call($source, '/v1/bezwaren', 'POST', ['form_params' => $bezwaar, 'headers' => ['Content-Type' => 'application/json']]);
            $result   = $this->callService->decodeResponse($source, $response);
        } catch (Exception $e) {
            $this->logger->error("Failed to POST bezwaar, message:  {$e->getMessage()}");

            return ['response' => []];
        }//end try

        // Flush
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();
    }


    /**
     * An example handler that is triggered by an action.
     *
     * @param array $data          The data array
     * @param array $configuration The configuration array
     *
     * @return array A handler must ALWAYS return an array
     */
    public function bezwaarPushHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        $data = $data['response'];

        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => 'https://openbelasting.nl/source/openbelasting.pinkapi.source.json']);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://openbelasting.nl/schemas/openblasting.bezwaaraanvraag.schema.json']);
        $mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => 'https://dowr.openbelasting.nl/mapping/openbelasting.bezwaar.push.mapping.json']);

        if ($source === null || $entity === null) {
            return [];
        }

        $this->logger->debug("OpenBelastingService -> OpenBelastingHandler()");

        $dataId = $data['_self']['id'];

        $object      = $this->entityManager->find('App:ObjectEntity', $dataId);
        $objectArray = $object->toArray();

        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $objectArray['aanslagbiljetnummer'].$objectArray['aanslagbiljetvolgnummer']);

        // If we already have a sync with a object for given aanslagbiljet return error (cant create 2 bezwaren for one aanslagbiljet).
        if ($synchronization->getObject() !== null) {
            return [];
        }

        $this->synchronizationService->synchronize($synchronization, $objectArray);

        $objectArray = $this->mappingService->mapping($mapping, $objectArray);

        $this->sendBezwaar($source, $objectArray, $synchronization);

        return ['response' => $objectArray];

    }//end bezwaarPushHandler()


}//end class
