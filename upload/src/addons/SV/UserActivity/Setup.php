<?php


namespace SV\UserActivity;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use SV\RedisCache\Redis;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\User;

/**
 * Add-on installation, upgrade, and uninstall routines.
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * Creates add-on tables.
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * Alters core tables.
     */
    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep3()
    {
        $this->db()->query(
            "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0'),
            (?, 0, 'RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers', 'allow', '0')
        ", [User::GROUP_GUEST, User::GROUP_REG]
        );
    }

    public function upgrade2020002Step1()
    {
        $this->installStep1();
    }

    public function upgrade2020002Step2()
    {
        $this->installStep2();
    }

    /**
     * Drops add-on tables.
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     * Drops columns from core tables.
     */
    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    /**
     * @return array
     */
    protected function getTables()
    {
        $tables = [];

        $tables['xf_sv_user_activity'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'id', 'int')->autoIncrement()->primaryKey();
            $this->addOrChangeColumn($table, 'content_type', 'varbinary', 25);
            $this->addOrChangeColumn($table, 'content_id', 'int');
            $this->addOrChangeColumn($table, 'timestamp', 'int');
            $this->addOrChangeColumn($table, 'blob', 'varbinary', 255);
            $table->addUniqueKey(['content_type', 'content_id', 'blob'], 'content');
        };

        return $tables;
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        return $tables;
    }

    protected function getRemoveAlterTables()
    {
        $tables = [];

        return $tables;
    }

    /**
     * @param Create|Alter $table
     * @param string       $name
     * @param string|null  $type
     * @param string|null  $length
     * @return \XF\Db\Schema\Column
     * @throws \LogicException If table is unknown schema object
     */
    protected function addOrChangeColumn($table, $name, $type = null, $length = null)
    {
        if ($table instanceof Create)
        {
            $table->checkExists(true);

            return $table->addColumn($name, $type, $length);
        }
        else if ($table instanceof Alter)
        {
            if ($table->getColumnDefinition($name))
            {
                return $table->changeColumn($name, $type, $length);
            }

            return $table->addColumn($name, $type, $length);
        }
        else
        {
            throw new \LogicException('Unknown schema DDL type ' . \get_class($table));
        }
    }

    public function checkRequirements(&$errors = [], &$warnings = [])
    {
        /** @var Redis $cache */
        $cache = \XF::app()->cache();
        if (!($cache instanceof Redis) || !($credis = $cache->getCredis(false)))
        {
            $warnings[] = 'It is recommended that Redis Cache to be installed and configured, but a MySQL fallback is supported';
        }
    }
}
