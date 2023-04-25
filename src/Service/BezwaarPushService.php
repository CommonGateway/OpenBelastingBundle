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

        $data = $data['response'];

        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => 'https://openbelasting.nl/source/openbelasting.pinkapi.source.json']);
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://openbelasting.nl/schemas/openblasting.bezwaaraanvraag.schema.json']);

        if ($source === null || $entity === null) {
            return [];
        }

        $this->logger->debug("OpenBelastingService -> OpenBelastingHandler()");

        $dataId = $data['_self']['id'];

        $object      = $this->entityManager->find('App:ObjectEntity', $dataId);
        $objectArray = $object->toArray();

        $synchronization = $this->synchronizationService->findSyncBySource($source, $entity, $objectArray['aanslagbiljetnummer'].$objectArray['aanslagbiljetvolgnummer']);

        // If we already have a sync with a object for given aanslagbiljet return error (cant create 2 bezwaren for one aanslagbiljet).
        // if ($synchronization->getObject() !== null) {
        // return [];
        // }
        $this->synchronizationService->synchronize($synchronization, $objectArray);

        // Unset all gateway specific stuff.
        unset($objectArray['id']);
        unset($objectArray['_self']);
        if (isset($objectArray['bijlagen']) === true) {
            foreach ($objectArray['bijlagen'] as $key => $bijlage) {
                unset($objectArray['bijlagen'][$key]['_self']);
            }
        }

        if (isset($objectArray['beschikkingsregels']) === true) {
            foreach ($objectArray['beschikkingsregels'] as $key => $beschikkingsregel) {
                unset($objectArray['beschikkingsregels'][0]['_self']);
                if (isset($objectArray['beschikkingsregels'][$key]['grieven'][0])) {
                    foreach ($objectArray['beschikkingsregels'][$key]['grieven'] as $key2 => $grief) {
                        unset($objectArray['beschikkingsregels'][$key]['grieven'][$key2]['_self']);
                    }
                }
            }
        }

        if (isset($objectArray['belastingplichtige']) === true) {
            unset($objectArray['belastingplichtige']['_self']);
        }

        if (isset($objectArray['aanslagregels']) === true) {
            foreach ($objectArray['aanslagregels'] as $key => $aanslagregel) {
                unset($objectArray['aanslagregels'][$key]['_self']);
                if (isset($objectArray['aanslagregels'][$key]['grieven'][0])) {
                    foreach ($objectArray['aanslagregels'][$key]['grieven'] as $key2 => $grief) {
                        unset($objectArray['aanslagregels'][$key]['grieven'][$key2]['_self']);
                    }
                }
            }
        }

        // Flush
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        var_dump(json_encode($objectArray));
        die;

        // Send the POST request to pink.
        try {
            $response = $this->callService->call($source, '/v1/bezwaren', 'POST', ['form_params' => $objectArray]);
            $result   = $this->callService->decodeResponse($source, $response);
        } catch (Exception $e) {
            $this->logger->error("Failed to POST bezwaar, message:  {$e->getMessage()}");
            var_dump($e->getMessage());

            return false;
        }//end try

        // Flush
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return ['response' => $objectArray];

    }//end bezwaarPushHandler()


}//end class
