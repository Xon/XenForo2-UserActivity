<?php


namespace SV\UserActivity;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public static $defaultGuestGroupId = 1;
    public static $defaultRegisteredGroupId = 2;
    public static $defaultAdminGroupId = 3;
    public static $defaultModeratorGroupId = 4;

    public function installStep1()
    {
        $this->db()->query(
            "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0'),
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0')
        ", [self::$defaultGuestGroupId, self::$defaultRegisteredGroupId]
        );
    }
}
