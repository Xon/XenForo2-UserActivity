<?php

namespace SV\UserActivity\SV\StandardLib;

use XF\Entity\User as UserEntity;
use function is_array;

/**
 * @extends \SV\StandardLib\TemplaterHelper
 */
class TemplaterHelper extends XFCP_TemplaterHelper
{
    /**  @var callable|callable-string|null */
    protected $oldFnAvatar;

    public function addDefaultHandlers()
    {
        parent::addDefaultHandlers();

        $func = $this->function('avatar');
        if ($func)
        {
            $this->oldFnAvatar = $this->mangleCallable($func);
            // don't use the same name, or mangleCallable will bind to it
            $this->addFunction('avatar', 'fnArrayAvatar', true);
        }
    }

    public function fnArrayAvatar($templater, &$escape, $user, $size, $canonical = false, $attributes = [])
    {
        $fauxUser = $this->templater->processAttributeToRaw($attributes, 'faux-user', '', true);
        if ($fauxUser && is_array($user))
        {
            $em = \XF::em();
            $fauxUser = $em->findCached('XF:User', $user['user_id']);
            if (!$fauxUser)
            {
                /** @var UserEntity $fauxUser */
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

        $oldFnAvatar = $this->oldFnAvatar;
        //$this->templater->fnAvatar($templater, $escape, $user, $size, $canonical, $attributes);
        return $oldFnAvatar($templater, $escape, $user, $size, $canonical, $attributes);
    }
}