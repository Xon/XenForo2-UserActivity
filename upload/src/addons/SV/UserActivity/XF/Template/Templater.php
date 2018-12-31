<?php

namespace SV\UserActivity\XF\Template;



/**
 * Extends \XF\Template\Templater
 */
class Templater extends XFCP_Templater
{
    public function fnAvatar($templater, &$escape, $user, $size, $canonical = false, $attributes = [])
    {
        $fauxUser = $this->processAttributeToRaw($attributes, 'faux-user', '', true);
        if ($fauxUser && is_array($user))
        {
            /** @var \XF\Entity\User $fauxUser */
            $fauxUser = \XF::em()->create('XF:User');
            foreach($user as $key => $value)
            {
                if ($fauxUser->offsetExists($key))
                {
                    $fauxUser->setTrusted($key, $value);
                }
            }

            $fauxUser->setReadOnly(true);
            $user = $fauxUser;
        }

        return parent::fnAvatar($templater, $escape, $user, $size, $canonical, $attributes);
    }
}