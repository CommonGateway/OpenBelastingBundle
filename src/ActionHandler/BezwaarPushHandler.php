<?php
/**
 * An example handler for the per store.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\OpenBelastingBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\OpenBelastingBundle\Service\BezwaarPushService;


class OpenBelastingHandler implements ActionHandlerInterface
{

    /**
     * The pet store service used by the handler
     *
     * @var BezwaarPushService
     */
    private BezwaarPushService $bezwaarPushService;


    /**
     * The constructor
     *
     * @param BezwaarPushService $OpenBelastingService The pet store service
     */
    public function __construct(BezwaarPushService $bezwaarPushService)
    {
        $this->bezwaarPushService = $bezwaarPushService;

    }//end __construct()


    /**
     * Returns the required configuration as a https://json-schema.org array.
     *
     * @return array The configuration that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://example.com/ActionHandler/OpenBelastingHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'OpenBelasting ActionHandler',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     *
     * @SuppressWarnings("unused") Handlers ara strict implementations
     */
    public function run(array $data, array $configuration): array
    {
        return $this->bezwaarPushService->bezwaarPushHandler($data, $configuration);

    }//end run()


}//end class
