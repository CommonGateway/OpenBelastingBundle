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

        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => 'https://openbelasting.nl/source/openbelasting.pinkapi.source.json']);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://openbelasting.nl/schemas/openblasting.bezwaaraanvraag.schema.json']);

        if ($source === null || $entity === null) {
            return [];
        }

        $this->logger->debug("OpenBelastingService -> OpenBelastingHandler()");

        $dataId = $data['object']['_self']['id'];

        $object      = $this->entityManager->find('App:ObjectEntity', $dataId);
        $objectArray = $object->toArray();

        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $object->getId()->toString());
        $this->synchronizationService->synchronize($synchronization, $objectArray);

        // @todo maybe unset
        unset($objectArray['id']);
        unset($objectArray['_self']);

        // Send the POST/PUT request to pink.
        try {
            $response = $this->callService->call($source, '/v1/bezwaren', 'POST', ['body' => $objectArray]);
            $result   = $this->callService->decodeResponse($source, $response);
            dump($result);
            $bezwaarId   = $result['result']['reference'] ?? null;
        } catch (Exception $e) {
            $this->logger->error("Failed to POST bezwaar, message:  {$e->getMessage()}");

            return false;
        }//end try

        return ['response' => $objectArray];

    }//end openBelastingHandler()


}//end class
