<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;

class Post extends XFCP_Post
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Post',
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => [],
        'activeKey'  => 'thread',
    ];
    use UserActivityInjector;

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
    {
        if ($reply instanceof ViewReply)
        {
            $actionL = \strtolower($action);
            switch ($actionL)
            {
                case 'like':
                case 'likes':
                case 'threadmark':
                    $viewState = 'valid';

                    if (($threadId = $this->request->get('thread_id')) &&
                        ($thread = $this->em()->findCached('XF:Thread', $threadId)))
                    {

                        /** @var \XF\Entity\Thread $thread */
                        $this->getUserActivityRepo()->pushViewUsageToParent($reply, $thread->Forum->Node);
                    }

                    return true;
            }
        }

        return parent::canUpdateSessionActivity($action, $params, $reply, $viewState);
    }

    protected function svInjectThreadIdIntoRequest(ViewReply $reply, ParameterBag $params)
    {
        $postId = (int)$params->get('post_id', 0);
        if ($postId !== 0)
        {
            $post = $this->em()->findCached('XF:Post', $postId);
            if ($post instanceof \XF\Entity\Post)
            {
                $threadId = $post->thread_id;
                $this->request->set('thread_id', $threadId);
                $params['thread_id'] = $threadId;

                return;
            }
        }

        $post = $reply->getParam('post');
        $threadId = (int)($post['thread_id'] ?? 0);
        if ($threadId !== 0)
        {
            $this->request->set('thread_id', $threadId);
            $params['thread_id'] = $threadId;

            return;
        }

        $thread = $reply->getParam('thread');
        $threadId = (int)($thread['thread_id'] ?? 0);
        if ($threadId !== 0)
        {
            $this->request->set('thread_id', $threadId);
            $params['thread_id'] = $threadId;
        }
    }

    protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
    {
        if ($reply instanceof ViewReply &&
            $this->request->get('thread_id') === false)
        {
            // $reply should be Error if they don't have permission.
            // if the entity is cached, then this request returned something useful
            $this->svInjectThreadIdIntoRequest($reply, $params);
        }

        parent::updateSessionActivity($action, $params, $reply);
    }

    /**
     * @return \XF\Mvc\Entity\Repository|UserActivity
     */
    protected function getUserActivityRepo()
    {
        return \XF::repository('SV\UserActivity:UserActivity');
    }
}
