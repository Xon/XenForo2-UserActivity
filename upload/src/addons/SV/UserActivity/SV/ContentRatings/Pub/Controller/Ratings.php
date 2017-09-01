<?php

namespace SV\UserActivity\SV\ContentRatings\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use XF\Mvc\ParameterBag;

class Ratings extends XFCP_Ratings
{
    protected $activityInjector = [
        'controller' => 'SV\\ContentRatings\\Pub\\Controller\\Ratings',
        'type' => null,
        'id' => null,
        'actions' => ['like', 'list'],
    ];
    use ActivityInjector
    {
        preDispatchController as preDispatchControllerTrait;
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $input = $this->filter([
            'content_type' => 'str',
            'content_id' => 'uint',
        ]);
        $this->activityInjector['type'] = $input['content_type'];
        $this->activityInjector['id'] = $input['content_id'];

        $this->preDispatchControllerTrait($action, $params);
    }
}