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
        if ($reply instanceof View)
        {
            $actionL = strtolower($action);
            switch ($actionL)
            {
                case 'like':
                case 'likes':
                case 'threadmark':
                    $viewState = 'valid';
                    return true;
            }
        }

        return parent::canUpdateSessionActivity($action, $params, $reply, $viewState);
    }

    protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
    {
        if ($reply instanceof View &&
            $this->request->get('thread_id') === false &&
            ($postId = $params->get('post_id')))
        {
            // $reply should be Error if they don't have permission.
            // if the entity is cached, then this request returned something useful,
            /** \XF\Entity\Post */
            $post = $this->em()->findCached('XF:Post', $postId);
            if ($post)
            {
                $this->request->set('thread_id', $post['thread_id']);
            }
            else if ($post = $reply->getParam('post') && isset($post['thread_id']))
            {
                $this->request->set('thread_id', $post['thread_id']);
            }
            else if ($thread = $reply->getParam('thread') && isset($thread['thread_id']))
            {
                $this->request->set('thread_id', $thread['thread_id']);
            }
        }
        parent::updateSessionActivity($action, $params, $reply);
    }
}
