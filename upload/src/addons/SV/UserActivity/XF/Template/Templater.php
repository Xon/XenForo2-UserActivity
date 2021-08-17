<?php

namespace SV\UserActivity\XF\Template;



/**
 * Extends \XF\Template\Templater
 */
class Templater extends XFCP_Templater
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function fnAvatar($templater, &$escape, $user, $size, $canonical = false, $attributes = [])
    {
        $fauxUser = $this->processAttributeToRaw($attributes, 'faux-user', '', true);
        if ($fauxUser && \is_array($user))
        {
            $em = \XF::em();
            $fauxUser = $em->findCached('XF:User', $user['user_id']);
            if (!$fauxUser)
            {
                /** @var \XF\Entity\User $fauxUser */
                $fauxUser = $em->instantiateEntity('XF:User', ['user_id' => $user['user_id'], 'language_id' => 0]);
                foreach ($user as $key => $value)
                {
                    if ($fauxUser->offsetExists($key))
                    {
                        $fauxUser->setTrusted($key, $value);
                    }
                }

                $fauxUser->setReadOnly(true);
            }
            $user = $fauxUser;
        }

        return parent::fnAvatar($templater, $escape, $user, $size, $canonical, $attributes);
    }
}