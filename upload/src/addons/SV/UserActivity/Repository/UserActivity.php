<?php

namespace SV\UserActivity\Repository;

use Credis_Client;
use SV\RedisCache\Redis;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

class UserActivity extends Repository
{
    protected static $handlers = array();
    protected static $logging = true;
    protected static $forceFallback = false;

    /**
     * @return int
     */
    public function getSampleInterval()
    {
        return 30;
    }

    public function supressLogging()
    {
        self::$logging = false;
    }

    /**
     * @return bool
     */
    public function isLogging()
    {
        return self::$logging;
    }

    /**
     * @param string $controllerName
     * @param string $contentType
     * @param string $contentIdField
     */
    public function registerHandler($controllerName, $contentType, $contentIdField)
    {
        self::$handlers[$controllerName] = array($contentType, $contentIdField);
    }

    /**
     * @param string $controllerName
     * @return array
     */
    public function getHandler($controllerName)
    {
        if (empty(self::$handlers[$controllerName]))
        {
            return [];
        }
        return self::$handlers[$controllerName];
    }

    /**
     * @param AbstractReply $response
     * @param array         $fetchData
     */
    public function insertBulkUserActivityIntoViewResponse(&$response, array $fetchData)
    {
        if ($response instanceof View)
        {
            $visitor = \XF::visitor();
            if (!$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                return;
            }

            $response->setParam('UA_RecordCounts', $this->getUsersViewingCount($fetchData));
        }
    }

    /**
     * @param string $controllerName
     * @param AbstractReply $response
     */
    public function insertUserActivityIntoViewResponse($controllerName, &$response)
    {
        if ($response instanceof View)
        {
            $handler = $this->getHandler($controllerName);
            if (empty($handler))
            {
                return;
            }
            $contentType = $handler[0];
            $contentIdField = $handler[1];
            $content = $response->getParam($contentType);
            if (empty($content[$contentIdField]))
            {
                return;
            }

            $visitor = \XF::visitor();
            if (!$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                return;
            }
            $records = $this->getUsersViewing($contentType, $content[$contentIdField], $visitor);
            if (!empty($records))
            {
                $response->setParam('UA_Records', $records);
            }
        }
    }

    /**
     * @return Credis_Client|null
     */
    protected function getCredis()
    {
        if (self::$forceFallback)
        {
            return null;
        }
        $app = $this->app();
        /** @var Redis $cache */
        $cache = $app->cache();
        if (!($cache instanceof Redis) || !($credis = $cache->getCredis(false)))
        {
            return null;
        }
        return $credis;
    }

    /**
     * @param array        $data
     * @param integer|null $targetRunTime
     * @return array|bool
     */
    protected function _garbageCollectActivityFallback(/** @noinspection PhpUnusedParameterInspection */array $data, $targetRunTime = null)
    {
        $app = $this->app();
        $options = $app->options();
        $onlineStatusTimeout = $options->onlineStatusTimeout * 60;
        $end = \XF::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        $db = $this->db();
        $db->query('DELETE FROM `xf_sv_user_activity` WHERE `timestamp` < ?', $end);

        return false;
    }


    /**
     * @param array $data
     * @param null  $targetRunTime
     * @return array|bool
     */
    public function garbageCollectActivity(array $data, $targetRunTime = null)
    {
        $credis = $this->getCredis();
        if (!$credis)
        {
            return $this->_garbageCollectActivityFallback($data, $targetRunTime);
        }
        $app = $this->app();
        /** @var Redis $cache */
        $cache = $app->cache();

        $options = $app->options();
        $onlineStatusTimeout = $options->onlineStatusTimeout * 60;
        // we need to manually expire records out of the per content hash set if they are kept alive with activity
        $dataKey = $cache->getNamespacedId('activity_');

        $end = $app->time - $onlineStatusTimeout;
        $end = $end - ($end  % $this->getSampleInterval());

        // indicate to the redis instance would like to process X items at a time.
        $count = 100;
        // prevent looping forever
        $loopGuard = 10000;
        // find indexes matching the pattern
        $cursor = empty($data['cursor']) ? null : $data['cursor'];
        $s = microtime(true);
        do
        {
            $keys = $credis->scan($cursor, $dataKey ."*", $count);
            $loopGuard--;
            if ($keys === false)
            {
                break;
            }
            $data['cursor'] = $cursor;

            // the actual prune operation
            foreach($keys as $key)
            {
                $credis->zremrangebyscore($key, 0, $end);
            }

            $runTime = microtime(true) - $s;
            if ($targetRunTime && $runTime > $targetRunTime)
            {
                break;
            }
            $loopGuard--;
        }
        while($loopGuard > 0 && !empty($cursor));

        if (empty($cursor))
        {
            return false;
        }
        return $data;
    }

    const LUA_IFZADDEXPIRE_SH1 = 'dc1d76eefaca2f4ccf848a6ed7e80def200ac7b7';

    /**
     * @param string  $contentType
     * @param integer $contentId
     * @param integer $time
     * @param array   $data
     * @param string  $raw
     * @return array
     */
    protected function _updateSessionActivityFallback($contentType, $contentId, $time, array $data, $raw)
    {
        $db = $this->db();
        $db->query(
            '
            INSERT INTO xf_sv_user_activity 
            (content_type, content_id, `timestamp`, `blob`) 
            VALUES 
            (?,?,?,?)
             ON DUPLICATE KEY UPDATE `timestamp` = values(`timestamp`)',
            [$contentType, $contentId, $time, $raw]
        );

        return $data;
    }

    /**
     * @param string $contentType
     * @param int    $contentId
     * @param string $ip
     * @param string $robotKey
     * @param User $viewingUser
     */
    public function updateSessionActivity($contentType, $contentId, $ip, $robotKey, User $viewingUser)
    {
        $app = $this->app();
        $score = $app->time - ($app->time  % $this->getSampleInterval());
        $data = array
        (
            'user_id' => $viewingUser->user_id,
            'username' => $viewingUser->username,
            'visible' => $viewingUser->visible && $viewingUser->activity_visible ? 1 : null,
            'robot'  => empty($robotKey) ? null : 1,
            'display_style_group_id' => null,
            'avatar_date' => null,
            'gravatar' => null,
            'ip' => null,
        );

        $options = $app->options();
        if ($viewingUser->user_id)
        {
            $threadViewType = $options->RainDD_UA_ThreadViewType;
            if (!isset($threadViewType))
            {
                // add-on not fully installed
                return;
            }
            else if ($threadViewType == 0)
            {
                $data['display_style_group_id'] = $viewingUser->display_style_group_id;
            }
            else if ($threadViewType == 1)
            {
                $data['avatar_date'] = $viewingUser->avatar_date;
                $data['gravatar'] = $viewingUser->gravatar;
            }
            else
            {
                return;
            }
        }
        else
        {
            $data['ip'] = $ip;
        }

        // encode the data
        $raw = implode("\n", $data);

        $credis = $this->getCredis();
        if (!$credis)
        {
            // do not have a fallback
            $this->_updateSessionActivityFallback($contentType, $contentId, $score, $data, $raw);

            return;
        }
        /** @var Redis $cache */
        $cache = $app->cache();
        $useLua = $cache->useLua();

        // record keeping
        $key = $cache->getNamespacedId("activity_{$contentType}_{$contentId}");
        $onlineStatusTimeout = min(60, intval($options->onlineStatusTimeout * 60));

        if ($useLua)
        {
            $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, array($key), array($score, $raw, $onlineStatusTimeout));
            if ($ret === null)
            {
                $script =
                    "local c = tonumber(redis.call('zscore', KEYS[1], ARGV[2])) ".
                    "local n = tonumber(ARGV[1]) ".
                    "local retVal = 0 ".
                    "if c == nil or n > c then ".
                      "retVal = redis.call('ZADD', KEYS[1], n, ARGV[2]) ".
                    "end ".
                    "redis.call('EXPIRE', KEYS[1], ARGV[3]) ".
                    "return retVal ";
                /** @noinspection PhpUnusedLocalVariableInspection */
                $ret = $credis->eval($script, array($key), array($score, $raw, $onlineStatusTimeout));
            }
        }
        else
        {
            $credis->pipeline()->multi();
            // O(log(N)) for each item added, where N is the number of elements in the sorted set.
            $credis->zAdd($key, $score, $raw);
            $credis->expire($key, $onlineStatusTimeout);
            $credis->exec();
        }
    }

    const CacheKeys = array
    (
        'user_id',
        'username',
        'visible',
        'robot',
        'display_style_group_id',
        'avatar_date',
        'gravatar',
        'ip',
    );

    /**
     * @param string  $contentType
     * @param integer $contentId
     * @param integer $start
     * @param integer $end
     * @return array
     */
    protected function _getUsersViewingFallback(/** @noinspection PhpUnusedParameterInspection */ $contentType, $contentId, $start, $end)
    {
        $db = $this->db();
        $raw = $db->fetchAll(
            'SELECT * FROM xf_sv_user_activity WHERE content_type = ? AND content_id = ? AND `timestamp` >= ? ORDER BY `timestamp` desc',
            [$contentType, $contentId, $start]
        );

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['blob']] = $row['timestamp'];
        }

        return $records;
    }

    /**
     * @param string $contentType
     * @param int    $contentId
     * @param User $viewingUser
     * @return array|null
     */
    public function getUsersViewing($contentType, $contentId, User $viewingUser)
    {
        $isGuest = $viewingUser->user_id ? false : true;
        $memberCount = $isGuest ? 0 : 1;
        $guestCount = 0;
        $robotCount = 0;
        $records = $isGuest ? [] : [$viewingUser];

        $app = $this->app();
        $options = $app->options();
        $start = \XF::$time  - $options->onlineStatusTimeout * 60;
        $start = $start - ($start  % $this->getSampleInterval());
        $end = \XF::$time + 1;

        $credis = $this->getCredis();
        if (!$credis)
        {
            $onlineRecords = $this->_getUsersViewingFallback($contentType, $contentId, $start, $end);
            // check if the activity counter needs pruning
            if ($options->UA_pruneChance > 0 && mt_rand() < $options->UA_pruneChance)
            {
                $this->_garbageCollectActivityFallback([]);
            }
        }
        else
        {
            /** @var Redis $cache */
            $cache = $app->cache();
            $key = $cache->getNamespacedId("activity_{$contentType}_{$contentId}");

            $onlineRecords = $credis->zRevRangeByScore($key, $end, $start, array('withscores' => true));
            // check if the activity counter needs pruning
            if (mt_rand() < $options->UA_pruneChance)
            {
                $credis = $cache->getCredis(false);
                if ($credis->zCard($key) >= count($onlineRecords) * $options->UA_fillFactor)
                {
                    // O(log(N)+M) with N being the number of elements in the sorted set and M the number of elements removed by the operation.
                    $credis->zRemRangeByScore($key, 0, $start - 1);
                }
            }
        }

        $cutoff = $options->SV_UA_Cutoff;
        $memberVisibleCount = $isGuest ? 0 : 1;
        $recordsUnseen = 0;

        if(is_array($onlineRecords))
        {
            $seen = array($viewingUser->user_id => true);
            $bypassUserPrivacy = $viewingUser->canBypassUserPrivacy();
            $sampleInterval = $this->getSampleInterval();

            foreach($onlineRecords as $rec => $score)
            {
                $data = explode("\n", $rec);
                $rec = @array_combine(self::CacheKeys, $data);
                if (empty($rec))
                {
                    continue;
                }
                if ($rec['user_id'])
                {
                    if (empty($seen[$rec['user_id']]))
                    {
                        $seen[$rec['user_id']] = true;
                        $memberCount += 1;
                        if (!empty($rec['visible']) || $bypassUserPrivacy)
                        {
                            $memberVisibleCount += 1;
                            if ($cutoff > 0 && $memberVisibleCount > $cutoff)
                            {
                                $recordsUnseen += 1;
                                continue;
                            }
                            $score = $score - ($score % $sampleInterval);
                            $rec['effective_last_activity'] = $score;
                            $records[] = $rec;
                        }
                        else
                        {
                            $recordsUnseen += 1;
                        }
                    }
                }
                else if (empty($rec['robot']))
                {
                    $guestCount += 1;
                }
                else
                {
                    $robotCount += 1;
                }
            }
        }

        return array
        (
            'members' => $memberCount,
            'guests'  => $guestCount,
            'robots'  => $robotCount,
            'records' => $records,
            'recordsUnseen' => $recordsUnseen,
        );
    }

    /**
     * @param array $fetchData
     * @param int   $start
     * @param int   $end
     * @return array
     */
    protected function _getUsersViewingCountFallback(/** @noinspection PhpUnusedParameterInspection */ $fetchData, $start, $end)
    {
        $db = $this->db();

        $args = [$start];
        $sql = [];
        foreach($fetchData as $contentType => $list)
        {
            $list = array_filter(array_map('intval',array_unique($list)));
            if ($list)
            {
                $sql[] = "\n(content_type = " . $db->quote($contentType) . " AND content_id in (" . $db->quote($list) . "))";
            }
        }

        if (!$sql)
        {
            return [];
        }

        $sql = join(' OR ', $sql);

        $raw = $db->fetchAll(
            "SELECT content_type, content_id, count(*) as count
                  FROM xf_sv_user_activity 
                  WHERE `timestamp` >= ?  AND ({$sql})
                  group by content_type, content_id",
            $args
        );

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['content_type']][$row['content_id']] = $row['count'];
        }

        return $records;
    }

    public function getUsersViewingCount($fetchData)
    {
        $app = $this->app();
        $options = $app->options();
        $start = \XF::$time  - $options->onlineStatusTimeout * 60;
        $start = $start - ($start  % $this->getSampleInterval());
        $end = \XF::$time + 1;

        $credis = $this->getCredis();
        /** @noinspection PhpUndefinedFieldInspection */
        $pruneChance = $options->UA_pruneChance;
        if (!$credis)
        {
            // do not have a fallback
            $onlineRecords = $this->_getUsersViewingCountFallback($fetchData, $start, $end);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && mt_rand() < $pruneChance)
            {
                $this->_garbageCollectActivityFallback([]);
            }
        }
        else
        {
            /** @var Redis $cache */
            $cache = $app->cache();
            $credis = $this->getCredis();
            /** @noinspection PhpUnusedLocalVariableInspection */
            $useLua = $cache->useLua();

            $onlineRecords = [];
            $args = [];
            foreach ($fetchData as $contentType => $list)
            {
                $list = array_filter(array_map('intval', array_unique($list)));
                foreach ($list as $contentId)
                {
                    $args[] = [$contentType, $contentId];
                }
            }

            if (false) //$useLua
            {
                /*
                $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, [$key], [$score, $raw, $onlineStatusTimeout]);
                if ($ret === null)
                {
                    $script = "";
                    $credis->eval($script, [$key], [$score, $raw, $onlineStatusTimeout]);
                }
                */
            }
            else
            {
                $credis->pipeline()->multi();
                foreach ($args as $row)
                {
                    $key = $cache->getNamespacedId("activity_{$row[0]}_{$row[1]}");
                    $credis->zcount($key, $start, $end);
                }
                $ret = $credis->exec();
                foreach ($args as $i => $row)
                {
                    $val = intval($ret[$i]);
                    if ($val)
                    {
                        $onlineRecords[$row[0]][$row[1]] = $val;
                    }
                }
            }
        }

        return $onlineRecords;
    }
}
