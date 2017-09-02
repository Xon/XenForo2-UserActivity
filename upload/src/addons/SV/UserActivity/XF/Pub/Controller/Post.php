<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

class Post extends XFCP_Post
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Post',
        'type' => 'thread',
        'id' => 'thread_id',
    ];
    use ActivityInjector;

    protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
    {
        $actionL = strtolower($action);
        switch($actionL)
        {
            case 'like':
            case 'threadmark':
                return true;
        }

        return parent::canUpdateSessionActivity($action, $params, $reply, $viewState);
    }

    protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
    {
        if ($reply instanceof View && $this->_request->getParam('thread_id') === null)
        {
            if (isset($controllerResponse->params['post']['thread_id']))
            {
                $this->_request->setParam('thread_id', $controllerResponse->params['post']['thread_id']);
            }
            else if (isset($controllerResponse->params['thread']['thread_id']))
            {
                $this->_request->setParam('thread_id', $controllerResponse->params['thread']['thread_id']);
            }
        }
        parent::updateSessionActivity($action, $params, $reply);
    }
}