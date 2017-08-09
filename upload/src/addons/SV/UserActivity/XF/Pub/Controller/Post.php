<?php

namespace SV\UserActivity\XF\Pub\Controller;

class Post extends XFCP_Post
{
    protected $activityInjector = [
        'controller' => 'XF\Pub\Controller\Post',
        'type' => 'thread',
        'id' => 'thread_id',
    ];
    use \SV\UserActivity\ActivityInjector;
}