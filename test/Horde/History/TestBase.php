<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Michael J Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @package    History
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_History_TestBase extends Horde_Test_Case
{
    protected static $history;

    public function testMethodLogHasPostConditionThatTimestampAndActorAreAlwaysRecorded()
    {
        self::$history->log('test_uid', array('who' => 'me', 'action' => 'test_action'));
        $this->assertTrue(self::$history->getActionTimestamp('test_uid', 'test_action') > 0);
        $data = self::$history->getHistory('test_uid');
        $this->assertTrue(isset($data[0]['who']));
    }

    public function testMethodLogHasPostConditionThatTheGivenEventHasBeenRecorded()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        $this->assertEquals(1000, self::$history->getActionTimestamp('test_uid', 'test_action'));
    }

    public function testMethodLogHasParameterStringGuid()
    {
        $this->expectException('InvalidArgumentException');
        self::$history->log(array());
    }

    public function testMethodLogHasArrayParameterBooleanReplaceaction()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'), false);
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'));
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'), true);

        $expect = array(
            1 => array(
                'action' => 'test_action',
                'desc'   => null,
                'who'    => 'me',
                'ts'     => 1000,
            ),
            2 => array(
                'action' => 'test_action',
                'desc'   => null,
                'who'    => 'me',
                'ts'     => 1000,
            ),
            4 => array(
                'action' => 'yours_action',
                'desc'   => '',
                'who'    => 'you',
                'ts'     => 2000,
            ),
        );
        $data = self::$history->getHistory('test_uid');
        foreach ($data as $log) {
            foreach ($expect[$log['modseq']] as $key => $value) {
                $this->assertEquals($value, $log[$key]);
            }
        }
    }

    public function testMethodGethistoryHasParameterStringGuid()
    {
        $this->expectException('InvalidArgumentException');
        self::$history->getHistory(array());
    }

    public function testMethodGethistoryHasResultHordehistorylogRepresentingTheHistoryLogMatchingTheGivenGuid()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action', 'extra' => array('a' => 'a')));
        $expect = array(
            1 => array(
                'action' => 'test_action',
                'desc'   => '',
                'who'    => 'me',
                'ts'     => 1000,
            ),
            2 => array(
                'action' => 'yours_action',
                'desc'   => '',
                'who'    => 'you',
                'ts'     => 2000,
                'extra'  => array('a' => 'a'),
            ),
        );
        $data = self::$history->getHistory('test_uid');
        foreach ($data as $log) {
            foreach ($expect[$log['modseq']] as $key => $value) {
                $this->assertEquals($value, $log[$key]);
            }
        }
    }

    public function testMethodGetbytimestampHasParameterStringCmp()
    {
        $this->expectException('InvalidArgumentException');
        self::$history->getByTimestamp(array(), 1);
    }

    public function testMethodGetbytimestampHasParameterIntegerTs()
    {
        $this->expectException('InvalidArgumentException');
        self::$history->getByTimestamp('>', 'hello');
    }

    public function testMethodGetbytimestampHasParameterArrayFilters()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action', 'extra' => array('a' => 'a')));
        $result = self::$history->getByTimestamp('>', 1, array(array('field' => 'who', 'op' => '=', 'value' => 'you')));
        // History ID is not required to be numeric.
        // $this->assertEquals(array('test_uid' => 2), $result);
        $this->assertArrayHasKey('test_uid', $result);
    }

    public function testMethodGetbytimestampHasParameterStringParent()
    {
        self::$history->log('test_uid:a_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid:b_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'));
        self::$history->log('yours_uid', array('who' => 'you', 'ts' => 3000, 'action' => 'yours_action'));
        $result = self::$history->getByTimestamp('>', 1, array(), 'test_uid');

        // History ID is not required to be numeric.
        //$this->assertEquals(array('test_uid:a_uid' => 1, 'test_uid:b_uid' => 2), $result);
        $this->assertArrayHasKey('test_uid:a_uid', $result);
        $this->assertArrayHasKey('test_uid:b_uid', $result);
    }

    public function testMethodGetbytimestampHasResultArrayContainingTheMatchingEventIds()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action', 'extra' => array('a' => 'a')));

        $result = self::$history->getByTimestamp('<=', 1000);
        // History ID is not required to be numeric.
        //$this->assertEquals(array('test_uid' => 2), $result);
        $this->assertArrayHasKey('test_uid', $result);

        $result = self::$history->getByTimestamp('<', 1001);
        // History ID is not required to be numeric.
        //$this->assertEquals(array('test_uid' => 2), $result);
        $this->assertArrayHasKey('test_uid', $result);

        $result = self::$history->getByTimestamp('>', 1001);
        // History ID is not required to be numeric.
        //$this->assertEquals(array('test_uid' => 3), $result);
        $this->assertArrayHasKey('test_uid', $result);

        $result = self::$history->getByTimestamp('>=', 2000);
        // History ID is not required to be numeric.
        //$this->assertEquals(array('test_uid' => 3), $result);
        $this->assertArrayHasKey('test_uid', $result);

        $result = self::$history->getByTimestamp('=', 2000);
        // History ID is not required to be numeric.
        //$this->assertEquals(array('test_uid' => 3), $result);
        $this->assertArrayHasKey('test_uid', $result);

        $result = self::$history->getByTimestamp('>', 2000);
        $this->assertEquals(array(), $result);
    }

    public function testMethodGetactiontimestampHasParameterStringGuid()
    {
        $this->expectException('InvalidArgumentException');
        self::$history->getActionTimestamp(array(), 'test');
    }

    public function testMethodGetactiontimestampHasParameterStringAction()
    {
        $this->expectException('InvalidArgumentException');
        self::$history->getActionTimestamp('test', array());
    }

    public function testMethodGetactiontimestampHasResultIntegerZeroIfGethistoryReturnsAnError()
    {
        $this->assertEquals(0, self::$history->getActionTimestamp('test', 'test'));
    }

    public function testMethodGetactiontimestampHasResultIntegerZeroIfThereIsNoMatchingRecord()
    {
        $this->assertEquals(0, self::$history->getActionTimestamp('test', 'test'));
    }

    public function testMethodGetactiontimestampHasResultIntegerTimestampOfTheMatchingRecord()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 2, 'action' => 'test_action'));
        $this->assertEquals(2, self::$history->getActionTimestamp('test_uid', 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 3, 'action' => 'test_action'));
        $this->assertEquals(3, self::$history->getActionTimestamp('test_uid', 'test_action'));
    }

    public function testMethodRemovebynamesHasPostconditionThatAllNamedRevordsHaveBeenRemoved()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'), false);
        self::$history->log('yours_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'));
        self::$history->log('yours_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'), true);
        self::$history->removeByNames(array('test_uid'));
        $data = self::$history->getHistory('test_uid');
        $this->assertEquals(0, count($data));
        $data = self::$history->getHistory('yours_uid');
        $expect = array(
            'action' => 'yours_action',
            'desc'   => null,
            'who'    => 'you',
            'ts'     => 2000,
            'modseq' => 4
        );
        // Remove ID, since it can not be determined beforehand.
        $this->assertEquals($expect, array_diff_key($data[0], array('id' => 1)));

        self::$history->removeByNames(array('yours_uid'));
        $data = self::$history->getHistory('yours_uid');
        $this->assertEquals(0, count($data));

    }

    public function testMethodRemovebynamesHasParameterArrayNames()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'), false);
        self::$history->log('yours_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'));
        self::$history->log('yours_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'), true);
        self::$history->removeByNames(array('test_uid', 'yours_uid'));
        $data = self::$history->getHistory('test_uid');
        $this->assertEquals(0, count($data));
        $data = self::$history->getHistory('yours_uid');
        $this->assertEquals(0, count($data));
    }

    public function testMethodRemovebynamesSucceedsIfParameterNamesIsEmpty()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'), false);
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'));
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action'), true);
        self::$history->removeByNames(array());

        $this->markTestIncomplete();
    }

    public function testConditionThatEmptyHistoryReturnsAFalseHighestModSeq()
    {
        $this->assertFalse(self::$history->getHighestModSeq());
    }

    public function testModSeqMethodsHavePostConditionThatMaxModSeqIncrements()
    {
        self::$history->log('test_uid', array('who' => 'me', 'action' => 'test_action'));
        $this->assertEquals(self::$history->getHighestModSeq(), 1);
        self::$history->log('test_uid', array('who' => 'me', 'action' => 'test_other_action'));
        $this->assertEquals(self::$history->getHighestModSeq(), 2);
    }

    public function testMethodLogHasPostConditionThatModSeqIsRecorded()
    {
        self::$history->log('test_uid', array('who' => 'me', 'action' => 'test_action'));
        $data = self::$history->getHistory('test_uid');
        $this->assertEquals($data[0]['modseq'], 1);
        self::$history->log('test_uid', array('who' => 'you', 'action' => 'your_action'));
        $expect = array(
            1 => array(
                'who' => 'me',
                'action' => 'test_action'
            ),
            2 => array(
                'who' => 'you',
                'action' => 'your_action'
            )
        );
        $data = self::$history->getHistory('test_uid');
        foreach ($data as $log) {
            foreach ($expect[$log['modseq']] as $key => $value) {
                $this->assertEquals($value, $log[$key]);
            }
        }
    }

    public function testMethodLogHasPostConditionThatModSeqIsRecordedWhenLogIsOverwritten()
    {
        self::$history->log('test_uid', array('who' => 'me', 'action' => 'test_action'));
        $data = self::$history->getHistory('test_uid');
        $this->assertEquals($data[0]['modseq'], 1);
        self::$history->log('test_uid', array('who' => 'me', 'action' => 'test_action'), true);
        $data = self::$history->getHistory('test_uid');
        $this->assertEquals($data[0]['modseq'], 2);
        $this->assertTrue(empty($data[1]));
    }

    public function testMethodGetActionModSeqHasResultMatchingRequestedEntry()
    {
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 1, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 2, 'action' => 'test_action'));
        self::$history->log('test_uid', array('who' => 'me', 'ts' => 3, 'action' => 'test_otheraction'));
        $this->assertEquals(self::$history->getActionModSeq('test_uid', 'test_action'), 2);
        self::$history->log('test_otheruid', array('who' => 'me', 'ts' => 3, 'action' => 'test_action'));
        $this->assertEquals(self::$history->getActionModSeq('test_uid', 'test_action'), 2);
        self::$history->log('test_uid', array('who' => 'you', 'ts' => 5, 'action' => 'test_action'), true);
        $this->assertEquals(self::$history->getActionModSeq('test_uid', 'test_action'), 5);
    }

    public function testMethodGetbymodseqHasResultArrayContainingTheMatchingEventIds()
    {
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action')); // 1
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1001, 'action' => 'test_action')); // 2
        self::$history->log('apptwo:test_uid', array('who' => 'you', 'ts' => 1002, 'action' => 'test_special_action')); // 3
        self::$history->log('apptwo:test_uid', array('who' => 'me', 'ts' => 1003, 'action' => 'test_action')); // 4
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1004, 'action' => 'test_action')); // 5
        self::$history->log('appone:test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action', 'extra' => array('a' => 'a'))); // 6
        self::$history->log('appone:test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action', 'extra' => array('a' => 'a'))); // 7
        self::$history->log('appone:test_uid', array('who' => 'you', 'ts' => 2000, 'action' => 'yours_action', 'extra' => array('a' => 'a'))); // 8

        // Only have two unique UIDS.
        $result = self::$history->getByModSeq(0, 5);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('appone:test_uid', $result);
        $this->assertArrayHasKey('apptwo:test_uid', $result);

        $result = self::$history->getByModSeq(4, 8);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('appone:test_uid', $result);

        // Test using action filter.
        $filter = array(array('op' => '=', 'field' => 'action', 'value' => 'test_special_action'));
        $result = self::$history->getByModSeq(0, 5, $filter);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('apptwo:test_uid', $result);

        // Test using parent
        $result = self::$history->getByModSeq(0, 5, array(), 'apptwo');
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('apptwo:test_uid', $result);

        // Test parent AND filter
        $result = self::$history->getByModSeq(0, 5, $filter, 'apptwo');
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('apptwo:test_uid', $result);
    }

    public function testConditionThatHigestModSeqPersistsAfterLogDeletion()
    {
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1001, 'action' => 'test_action'));
        $this->assertEquals(2, self::$history->getHighestModSeq());

        // Removes all entries
        self::$history->removeByNames(array('appone:test_uid'));
        $this->assertEquals(2, self::$history->getHighestModSeq());

        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1001, 'action' => 'test_action'));
        self::$history->log('appone:test_uid2', array('who' => 'me', 'ts' => 1002, 'action' => 'test_action'));
        $this->assertEquals(4, self::$history->getHighestModSeq());

        // Remove the highest modseq entry
        // @todo Currently fails if we delete the existing highest modseq
        //  the modseq is incremented properly on the next addition though.
        //self::$history->removeByNames(array('appone:test_uid2'));
        //$this->assertEquals(4, self::$history->getHighestModSeq());
    }

    public function testMethodGetHighestModSeqWithParentFilterReturnsFilteredResults()
    {
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1001, 'action' => 'test_action'));
        self::$history->log('apptwo:test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        $this->assertEquals(2, self::$history->getHighestModSeq('appone'));
        $this->assertEquals(3, self::$history->getHighestModSeq('apptwo'));
        $this->assertEquals(3, self::$history->getHighestModSeq());
    }

}
