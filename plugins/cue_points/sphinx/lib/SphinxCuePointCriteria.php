<?php

class SphinxCuePointCriteria extends SphinxCriteria
{
	public static $sphinxFields = array(
		CuePointPeer::ENTRY_ID => 'entry_id',
		CuePointPeer::NAME => 'name',
		CuePointPeer::TEXT => 'text',
		CuePointPeer::TAGS => 'tags',
		CuePointPeer::ROOTS => 'roots',
		CuePointPeer::INT_ID => 'cue_point_int_id',
		CuePointPeer::PARTNER_ID => 'partner_id',
		CuePointPeer::START_TIME => 'start_time',
		CuePointPeer::END_TIME => 'end_time',
		CuePointPeer::STATUS => 'cue_point_status',
		CuePointPeer::TYPE => 'cue_point_type',
		CuePointPeer::KUSER_ID => 'kuser_id',
		CuePointPeer::PARTNER_SORT_VALUE => 'partner_sort_value',
		CuePointPeer::CREATED_AT => 'created_at',
		CuePointPeer::UPDATED_AT => 'updated_at',
		CuePointPeer::STR_ENTRY_ID => 'str_entry_id',
		CuePointPeer::STR_CUE_POINT_ID => 'str_cue_point_id',
	);
	
	public static $sphinxOrderFields = array(
		CuePointPeer::START_TIME => 'start_time',
		CuePointPeer::END_TIME => 'end_time',
		CuePointPeer::PARTNER_SORT_VALUE => 'partner_sort_value',
		CuePointPeer::CREATED_AT => 'created_at',
		CuePointPeer::UPDATED_AT => 'updated_at',
	);
	
	/**
	 * @return criteriaFilter
	 */
	protected function getDefaultCriteriaFilter()
	{
		return CuePointPeer::getCriteriaFilter();
	}
	
	public function getSphinxOrderFields()
	{
		return self::$sphinxOrderFields;
	}
	
	/**
	 * @return string
	 */
	protected function getSphinxIndexName()
	{
		return kSphinxSearchManager::getSphinxIndexName(CuePointPeer::TABLE_NAME);;
	}
	
	/* (non-PHPdoc)
	 * @see SphinxCriteria::executeSphinx()
	 */
	protected function executeSphinx($index, $wheres, $orderBy, $limit, $maxMatches, $setLimit)
	{
		$sql = "SELECT cue_point_int_id FROM $index $wheres $orderBy LIMIT $limit OPTION max_matches=$maxMatches";
		
		//debug query
		//echo $sql."\n"; die;
		$pdo = DbManager::getSphinxConnection();
		$stmt = $pdo->query($sql);
		if(!$stmt)
		{
			KalturaLog::err("Invalid sphinx query [$sql]");
			return;
		}
		
		$ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 2);
		KalturaLog::log("Found " . count($ids) . " ids");
		
		foreach($this->keyToRemove as $key)
		{
			KalturaLog::log("Removing key [$key] from criteria");
			$this->remove($key);
		}
		
		$this->addAnd(CuePointPeer::INT_ID, $ids, Criteria::IN);
		
		$this->recordsCount = 0;
		
		if(!$this->doCount)
			return;
			
		if($setLimit)
		{
			$this->setOffset(0);
			
			$sql = "show meta";
			$stmt = $pdo->query($sql);
			$meta = $stmt->fetchAll(PDO::FETCH_NAMED);
			if(count($meta))
			{
				foreach($meta as $metaItem)
				{
					KalturaLog::log("Sphinx query " . $metaItem['Variable_name'] . ': ' . $metaItem['Value']);
					if($metaItem['Variable_name'] == 'total_found')
						$this->recordsCount = (int)$metaItem['Value'];
				}
			}
		}
		else
		{
			$c = clone $this;
			$c->setLimit(null);
			$c->setOffset(null);
			$this->recordsCount = CuePointPeer::doCount($c);
		}
	}

	/* (non-PHPdoc)
	 * @see propel/util/Criteria#getNewCriterion()
	 */
	public function getNewCriterion($column, $value, $comparison = null)
	{
		return new SphinxCriterion('SphinxCuePointCriteria', $this, $column, $value, $comparison);
	}

	/* (non-PHPdoc)
	 * @see propel/util/Criteria#add()
	 */
	public function add($p1, $value = null, $comparison = null)
	{
		if ($p1 instanceof Criterion)
		{
			$oc = $this->getCriterion($p1->getColumn());
			if(!is_null($oc) && $oc->getValue() == $p1->getValue() && $oc->getComparison() != $p1->getComparison())
				return $this;
				
			return parent::add($p1);
		}
		
		$nc = new SphinxCriterion('SphinxCuePointCriteria', $this, $p1, $value, $comparison);
		return parent::add($nc);
	}

	/* (non-PHPdoc)
	 * @see propel/util/Criteria#addAnd()
	 */
	public function addAnd($p1, $p2 = null, $p3 = null)
	{
		if (is_null($p3)) 
			return parent::addAnd($p1, $p2, $p3);
			
		// addAnd(column, value, comparison)
		$nc = new SphinxCriterion('SphinxCuePointCriteria', $this, $p1, $p2, $p3);
		$oc = $this->getCriterion($p1);
		
		if ( !is_null($oc) )
		{
			// no need to add again
			if($oc->getValue() != $p2 || $oc->getComparison() != $p3)
				$oc->addAnd($nc);
				
			return $this;
		}
			
		return $this->add($nc);
	}
	
	public function hasSphinxFieldName($fieldName)
	{
		return isset(self::$sphinxFields[$fieldName]);
	}
	
	public function getSphinxFieldName($fieldName)
	{
		if(!isset(self::$sphinxFields[$fieldName]))
			return $fieldName;
			
		return self::$sphinxFields[$fieldName];
	}
	
	public function getSphinxFieldType($fieldName)
	{
		$sphinxTypes = CuePoint::getIndexFieldTypes();
		if(!isset($sphinxTypes[$fieldName]))
			return null;
			
		return $sphinxTypes[$fieldName];
	}
	
	public function hasMatchableField($fieldName)
	{
		return in_array($fieldName, array(
			'entry_id', 
			'name', 
			'text', 
			'tags',  
			'roots', 
		));
	}
}