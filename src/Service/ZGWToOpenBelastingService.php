<?php
/**
 * An example service for adding business logic to your class.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\ZgwToOpenBelastingBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Mapping;
use CommonGateway\CoreBundle\Service\MappingService;

class ZGWToOpenBelastingService
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
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * The plugin logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Mapping|null The mapping we are using for the outgoing call.
     */
    private ?Mapping $mapping;


    /**
     * @param EntityManagerInterface $entityManager  The Entity Manager.
     * @param LoggerInterface        $pluginLogger   The plugin version of the logger interface.
     * @param MappingService         $mappingService MappingService.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $pluginLogger,
        MappingService $mappingService
    ) {
        $this->entityManager  = $entityManager;
        $this->logger         = $pluginLogger;
        $this->mappingService = $mappingService;
        $this->configuration  = [];
        $this->data           = [];

    }//end __construct()


    /**
     * Gets and sets a Mapping object using the required configuration['mapping'] to find the correct Mapping.
     *
     * @return Mapping|null The Mapping object we found or null if we don't find one.
     */
    private function setMapping(): ?Mapping
    {
        $this->mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $this->configuration['mapping']]);
        if ($this->mapping instanceof Mapping === false) {
            $this->logger->error("No mapping found with reference: {$this->configuration['mapping']}");

            return null;
        }

        return $this->mapping;

    }//end setMapping()


    /**
     * This function gets the zaakEigenschappen from the zgwZaak with the given properties (simXml elementen and Stuf extraElementen).
     *
     * @param ObjectEntity $zaakObjectEntity The zaak ObjectEntity.
     * @param array        $properties       The properties / eigenschappen we want to get.
     *
     * @return array zaakEigenschappen
     */
    public function getZaakEigenschappen(ObjectEntity $zaakObjectEntity, array $properties): array
    {
        $zaakEigenschappen = [];
        foreach ($zaakObjectEntity->getValue('eigenschappen') as $eigenschap) {
            if (in_array($eigenschap->getValue('naam'), $properties) || in_array('all', $properties)) {
                $zaakEigenschappen[$eigenschap->getValue('naam')] = $eigenschap->getValue('waarde');
            }
        }

        return $zaakEigenschappen;

    }//end getZaakEigenschappen()


    /**
     * This function gets the bsn of the rol with the betrokkeneType set as natuurlijk_persoon.
     *
     * @param ObjectEntity $zaakObjectEntity The zaak ObjectEntity.
     *
     * @return string bsn of the natuurlijk_persoon
     */
    public function getBsnFromRollen(ObjectEntity $zaakObjectEntity): ?string
    {
        foreach ($zaakObjectEntity->getValue('rollen') as $rol) {
            if ($rol->getValue('betrokkeneType') === 'natuurlijk_persoon') {
                $betrokkeneIdentificatie = $rol->getValue('betrokkeneIdentificatie');

                return $betrokkeneIdentificatie->getValue('inpBsn');
            }
        }

        return null;

    }//end getBsnFromRollen()


    /**
     * Maps zgw eigenschappen to openbelasting properties.
     *
     * @param ObjectEntity $object The zgw case ObjectEntity.
     * @param array        $output The output data
     *
     * @return array
     */
    private function getOpenBelastingProperties(ObjectEntity $object, array $output): array
    {
        $properties        = [
            'bsn',
            'gemeentecode',
            'sub.telefoonnummer',
            'sub.emailadres',
            'geselecteerdNaamgebruik',
        ];
        $zaakEigenschappen = $this->getZaakEigenschappen($object, $properties);

        $bsn = $this->getBsnFromRollen($object);

        // @todo custom mapping
        $naamgebruikBetrokkenen['naam:NaamgebruikBetrokkene'] = [
            'naam:Burgerservicenummer' => $bsn,
            'naam:CodeNaamgebruik'     => $zaakEigenschappen['geselecteerdNaamgebruik'],
        ];

        return $output;

    }//end getOpenBelastingProperties()


    /**
     * An example handler that is triggered by an action.
     *
     * @param array $data          The data array
     * @param array $configuration The configuration array
     *
     * @return array A handler must ALWAYS return an array
     */
    public function openBelastingHandler(array $data, array $configuration): array
    {
        $this->data          = $data;
        $this->configuration = $configuration;

        if ($this->setMapping() === null) {
            return [];
        }

        $this->logger->debug("ZGWToOpenBelastingService -> ZGWToOpenBelastingHandler()");

        $dataId = $data['object']['_self']['id'];

        $object      = $this->entityManager->find('App:ObjectEntity', $dataId);
        $objectArray = $object->toArray();
        $zaakTypeId  = $objectArray['zaaktype']['identificatie'];

        // Do mapping with Zaak ObjectEntity as array.
        $objectArray = $this->mappingService->mapping($this->mapping, $objectArray);
        $objectArray = $this->getOpenBelastingProperties($object, $objectArray);

        return ['response' => $objectArray ];

    }//end openBelastingHandler()


}//end class
