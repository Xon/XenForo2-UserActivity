<?php

namespace SV\UserActivity\SV\ContentRatings\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use XF\Mvc\ParameterBag;

class Ratings extends XFCP_Ratings
{
    protected $activityInjector = [
        'controller' => 'SV\\ContentRatings\\Pub\\Controller\\Ratings',
        'type' => null,
        'id' => 'container_id',
        'actions' => ['like', 'list'],
    ];
    use ActivityInjector
    {
        preDispatchController as preDispatchControllerTrait;
    }

    /** @var ParameterBag $_params */
    protected $_params;

    /**
     * @return array
     */
    protected function assertViewableContent($contentType, $contentId)
    {
        $list = parent::assertViewableContent($contentType, $contentId);
        /** @var \XF\Mvc\Entity\Entity $content */
        $content = $list[0];

        if (is_callable([$content, 'getContainer']) && ($container = $content->getContainer()))
        {
            /** @var \XF\Mvc\Entity\Entity $container */

            $this->activityInjector['type'] = $container->getEntityContentType();
            $this->_params['container_id'] = $container->getEntityId();

            /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
            $userActivityRepo = $this->app->repository('SV\UserActivity:UserActivity');
            $userActivityRepo->registerHandler($this->activityInjector['controller'], $this->activityInjector['type'], $this->activityInjector['id']);
        }

        return $list;
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->_params = $params;
        parent::preDispatchController($action, $params);
    }
}