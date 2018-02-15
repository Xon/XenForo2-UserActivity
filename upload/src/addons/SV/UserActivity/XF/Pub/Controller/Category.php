<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\ForumActivityInjector;

/**
 * Extends \XF\Pub\Controller\Category
 */
class Category extends XFCP_Category
{
    protected $forumActivityInjector = [
        'index' => [
            'depth' => 0,
        ],
    ];
    use ForumActivityInjector;
}
