<?php

namespace SV\UserActivity\SV\ContentRatings\Pub\Controller;

class Ratings extends XFCP_Ratings
{
    protected $activityInjector = [
        'controller' => 'SV\\ContentRatings\\Pub\\Controller\\Ratings',
        'type' => null,
        'id' => null,
        'actions' => ['like', 'list'],
    ];
    use \SV\UserActivity\ActivityInjector
    {
        preDispatchController as preDispatchControllerTrait;
    }

    protected function preDispatchController($action, ParameterBag $params)
    {
        $input = $this->filter([
            'content_type' => 'str',
            'content_id' => 'uint',
        ]);
        $this->activityInjector['type'] = $input['content_type'];
        $this->activityInjector['id'] = $input['content_id'];

        return $this->preDispatchControllerTrait();
    }
}