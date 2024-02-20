<?php
/**
 * An example service for adding business logic to your class.
 *
 * @author  Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Conduction.nl <info@conduction.nl>
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
        $this->mappingService         = $mappingService;
        $this->callService            = $callService;
        $this->configuration          = [];
        $this->data                   = [];

    }//end __construct()


    /**
     * Sends bezwaar to open belastingen api.
     *
     * @param Gateway              $source
     * @param array                $bezwaar
     * @param Synchronization|null $synchronization
     *
     * @return array if went wrong
     */
    private function sendBezwaar(Gateway $source, array $bezwaar, ?Synchronization $synchronization=null): ?array
    {
        // Send the POST request to pink.
        try {
            $response = $this->callService->call($source, '/v1/bezwaren', 'POST', ['body' => \Safe\json_encode($bezwaar), 'headers' => ['Content-Type' => 'application/json']]);
            $result   = null;
            if ($response->getStatusCode() !== 204) {
                $result = $this->callService->decodeResponse($source, $response);
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to POST bezwaar, message:  {$e->getMessage()}");

            return ['response' => ['Error' => $e->getMessage()]];
        }//end try

        // Flush
        // Old sync code
        // $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return [
            'response' => $result,
            'bezwaar'  => $bezwaar,
        ];

    }//end sendBezwaar()


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
        // $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://openbelasting.nl/schemas/openblasting.bezwaaraanvraag.schema.json']);
        if ($source === null
            // || $entity === null
        ) {
            return [];
        }

        $this->logger->debug("OpenBelastingService -> OpenBelastingHandler()");

        $dataId = $data['_self']['id'];

        $object      = $this->entityManager->find('App:ObjectEntity', $dataId);
        $objectArray = $object->toArray(['metadata' => false]);

        // Old version with sync:
        // There are (very) few cases in which there are multiple aanslagBiljetNummer with different aanslagVolgNummer.
        // $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $objectArray['aanslagbiljetnummer'].'-'.$objectArray['aanslagbiljetvolgnummer']);
        //
        // $this->synchronizationService->synchronize($synchronization, $objectArray);
        return $this->sendBezwaar($source, $objectArray);
        // Old version with sync:
        // return $this->sendBezwaar($source, $objectArray, $synchronization);

    }//end bezwaarPushHandler()


}//end class
