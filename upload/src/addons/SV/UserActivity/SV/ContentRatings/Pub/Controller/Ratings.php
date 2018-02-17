<?php

namespace SV\UserActivity\SV\ContentRatings\Pub\Controller;

use SV\UserActivity\UserActivityInjector;
use XF\Mvc\ParameterBag;

class Ratings extends XFCP_Ratings
{
    protected $activityInjector = [
        'controller' => 'SV\\ContentRatings\\Pub\\Controller\\Ratings',
        'type' => null,
        'id' => 'container_id',
        'actions' => ['like', 'list'],
    ];
    use UserActivityInjector
    {
        preDispatch as preDispatchTrait;
    }

    /** @var ParameterBag $_params */
    protected $_params = null;

    protected function assertViewableContent(\XF\Like\AbstractHandler $likeHandler, $contentType, $contentId)
    {
        $content = parent::assertViewableContent($likeHandler, $contentType, $contentId);
        /** @noinspection PhpUndefinedMethodInspection */
        if ($this->_params !== null && is_callable([$content, 'getContainer']) && ($container = $content->getContainer()))
        {
            /** @var \XF\Mvc\Entity\Entity $container */

            $this->activityInjector['type'] = $container->getEntityContentType();
            $this->_params['container_id'] = $container->getEntityId();
            if ($this->activityInjector['type'])
            {
                /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
                $userActivityRepo = $this->app->repository('SV\UserActivity:UserActivity');
                $userActivityRepo->registerHandler($this->activityInjector['controller'], $this->activityInjector);
            }
        }

        return $content;
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    public function _preDispatch($action, ParameterBag $params)
    {
        $this->_params = $params;
        $this->preDispatchTrait($action, $params);
    }
}
