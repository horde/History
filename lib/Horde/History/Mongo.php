<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  History
 */

/**
 * Provides a MongoDB implementation of the history driver.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   History
 */
class Horde_History_Mongo extends Horde_History implements Horde_Mongo_Collection_Index
{
    /** Mongo collection name. */
    public const MONGO_DATA = 'horde_history_data';
    public const MONGO_MODSEQ = 'horde_history_modseq';

    /** Mongo field names. */
    public const ACTION = 'action';
    public const DESC = 'desc';
    public const EXTRA = 'extra';
    public const MODSEQ = 'modseq';
    public const TS = 'ts';
    public const UID = 'uid';
    public const WHO = 'who';

    /**
     * MongoDB object used to manage the history.
     *
     * @var MongoDB
     */
    protected $_db;

    /**
     * The list of indices.
     *
     * @var array
     */
    protected $_indices = [
        self::MONGO_DATA => [
            'index_action' => [
                self::ACTION => 1,
            ],
            'index_modseq' => [
                self::MODSEQ => 1,
            ],
            'index_ts' => [
                self::TS => 1,
            ],
            'index_uid' => [
                self::UID => 1,
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters:
     * <ul>
     *  <li>
     *   REQUIRED parameters:
     *   <ul>
     *    <li>
     *     mongo_db: (Horde_Mongo_Client) A MongoDB client object.
     *    </li>
     *   </ul>
     *  </li>
     */
    public function __construct($auth, array $params = [])
    {
        if (!isset($params['mongo_db'])) {
            throw new InvalidArgumentException('Missing mongo_db parameter.');
        }

        parent::__construct($params);

        $this->_db = $params['mongo_db']->selectDB(null);
    }

    /**
     */
    public function getActionTimestamp($guid, $action)
    {
        if (!is_string($guid) || !is_string($action)) {
            throw new InvalidArgumentException(
                '$guid and $action need to be strings!'
            );
        }

        try {
            $cursor = $this->_db->selectCollection(self::MONGO_DATA)->find(
                [
                    self::ACTION => $action,
                    self::UID => $guid,
                ],
                [
                    self::TS => true,
                ]
            )
            ->limit(1)
            ->sort([self::TS => -1]);
            $next = $cursor->getNext();
            return intval($next[self::TS]);
        } catch (MongoException $e) {
            return 0;
        }
    }

    /**
     */
    protected function _log(
        Horde_History_Log $history,
        array $attributes,
        $replaceAction = false
    ) {
        $extra = array_diff_key(
            $attributes,
            array_flip(['action', 'desc', 'ts', 'who'])
        );

        $data = [
            self::DESC => ((isset($attributes['desc']) && strlen($attributes['desc'])) ? $attributes['desc'] : null),
            self::EXTRA => (empty($extra) ? null : serialize($extra)),
            self::MODSEQ => $this->_nextModSeq(),
            self::TS => $attributes['ts'],
            self::WHO => $attributes['who'],
        ];

        if ($replaceAction && !empty($attributes['action'])) {
            foreach ($history as $entry) {
                if (!empty($entry['action'])
                    && ($entry['action'] == $attributes['action'])) {
                    try {
                        $this->_db->selectCollection(self::MONGO_DATA)->update(
                            ['_id' => $entry['id']],
                            ['$set' => $data]
                        );
                    } catch (MongoException $e) {
                        throw new Horde_History_Exception($e);
                    }

                    return;
                }
            }
        }

        /* If we're not replacing by action, or if we didn't find an entry to
         * replace, insert a new row. */
        $data[self::ACTION] = $attributes['action']
            ?? null;
        $data[self::UID] = $history->uid;

        try {
            $this->_db->selectCollection(self::MONGO_DATA)->insert($data);
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }
    }

    /**
     */
    public function _getHistory($guid)
    {
        try {
            $cursor = $this->_db->selectCollection(self::MONGO_DATA)->find(
                [self::UID => $guid]
            );
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }

        return new Horde_History_Log($guid, $this->_cursorToRow($cursor));
    }

    /**
     */
    public function _getByTimestamp(
        $cmp,
        $ts,
        array $filters = [],
        $parent = null
    ) {
        array_unshift($filters, [
            'field' => self::TS,
            'op' => $cmp,
            'value' => $ts,
        ]);

        return $this->_assocQuery([], $filters, $parent);
    }

    /**
     */
    protected function _getByModSeq(
        $start,
        $end,
        $filters = [],
        $parent = null
    ) {
        return $this->_assocQuery(
            [
                self::MODSEQ => [
                    '$gt' => $start,
                    '$lte' => $end,
                ],
            ],
            $filters,
            $parent
        );
    }

    /**
     */
    public function removeByNames(array $names)
    {
        if (!count($names)) {
            return;
        }

        if ($this->_cache) {
            foreach ($names as $name) {
                $this->_cache->expire('horde:history:' . $name);
            }
        }

        try {
            $this->_db->selectCollection(self::MONGO_DATA)->remove([
                self::UID => ['$in' => $names],
            ]);
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }
    }

    /**
     */
    public function getHighestModSeq($parent = null)
    {
        $ops = [];
        if (!empty($parent)) {
            $ops[self::UID] = [
                '$regex' => preg_quote($parent) . ':*',
            ];
        }

        try {
            /* Can't use aggregate() here, since no guarantee we are running
             * MongoDB 2.1+. */
            $cursor = $this->_db->selectCollection(self::MONGO_DATA)->find(
                $ops,
                [self::MODSEQ => true]
            )
            ->sort([
                self::MODSEQ => -1,
            ])
            ->limit(1);

            if ($next = $cursor->getNext()) {
                return $next[self::MODSEQ];
            }

            $cursor = $this->_db->selectCollection(self::MONGO_MODSEQ)->find(
                ['_id' => 'modseq']
            );
            return ($next = $cursor->getNext())
                ? $next[self::MODSEQ]
                : false;
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }
    }

    /**
     */
    protected function _nextModSeq()
    {
        try {
            $res = $this->_db->selectCollection(self::MONGO_MODSEQ)->findAndModify(
                ['_id' => 'modseq'],
                ['$inc' => [self::MODSEQ => 1]],
                [],
                ['new' => true, 'upsert' => true]
            );
            return $res[self::MODSEQ];
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }
    }

    /**
     */
    public function getLatestEntry($guid, $use_ts = false)
    {
        try {
            $cursor = $this->_db->selectCollection(self::MONGO_DATA)->find(
                [self::UID => $guid]
            )
            ->sort([
                ($use_ts ? self::TS : self::MODSEQ) => -1,
            ])
            ->limit(1);

            $log = new Horde_History_Log($guid, $this->_cursorToRow($cursor));
            return $log[0]
                ?? false;
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }
    }

    /* Internal methods. */

    /**
     */
    protected function _assocQuery($query, $filters, $parent)
    {
        foreach ($filters as $val) {
            switch ($val['op']) {
                case '>':
                    $query[$val['field']] = ['$gt' => $val['value']];
                    break;

                case '>=':
                    $query[$val['field']] = ['$gte' => $val['value']];
                    break;

                case '<':
                    $query[$val['field']] = ['$lt' => $val['value']];
                    break;

                case '<=':
                    $query[$val['field']] = ['$lte' => $val['value']];
                    break;

                case '=':
                    $query[$val['field']] = $val['value'];
                    break;
            }
        }

        if ($parent) {
            $query[self::UID] = [
                '$regex' => preg_quote($parent) . ':*',
            ];
        }

        try {
            $cursor = $this->_db->selectCollection(self::MONGO_DATA)->find(
                $query,
                [self::UID => true]
            );

            $out = [];
            foreach ($cursor as $val) {
                $out[$val[self::UID]] = strval($val['_id']);
            }
        } catch (MongoException $e) {
            throw new Horde_History_Exception($e);
        }

        return $out;
    }

    /**
     * @param MongoCursor $cursor
     *
     * @return array
     */
    protected function _cursorToRow(MongoCursor $cursor)
    {
        $mapping = [
            '_id' => 'history_id',
            self::ACTION => 'history_action',
            self::DESC => 'history_desc',
            self::EXTRA => 'history_extra',
            self::MODSEQ => 'history_modseq',
            self::TS => 'history_ts',
            self::WHO => 'history_who',
        ];
        $out = [];

        foreach ($cursor as $val) {
            $row = [];

            foreach ($mapping as $key2 => $val2) {
                $row[$val2] = $val[$key2]
                    ?? null;
            }

            $out[] = $row;
        }

        return $out;
    }

    /* Horde_Mongo_Collection_Index methods. */

    /**
     */
    public function checkMongoIndices()
    {
        foreach ($this->_indices as $key => $val) {
            if (!$this->_db->checkIndices($key, $val)) {
                return false;
            }
        }

        return true;
    }

    /**
     */
    public function createMongoIndices()
    {
        foreach ($this->_indices as $key => $val) {
            $this->_db->createIndices($key, $val);
        }
    }
}
