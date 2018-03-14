<?php
/**
 * @package plugins.reach
 */
class kReachManager implements kObjectChangedEventConsumer, kObjectCreatedEventConsumer, kObjectAddedEventConsumer
{
	/**
	 * @param BaseObject $object
	 * @param BatchJob $raisedJob
	 * @return bool true if the consumer should handle the event
	 */
	public function shouldConsumeAddedEvent(BaseObject $object)
	{
		if($object instanceof categoryEntry)
			return true;
	}
	
	/* (non-PHPdoc)
	 * @see kObjectAddedEventConsumer::shouldConsumeAddedEvent()
	 */
	public function shouldConsumeCreatedEvent(BaseObject $object)
	{
		if($object instanceof EntryVendorTask && $object->getStatus() == EntryVendorTaskStatus::PENDING)
			return true;
		
		return false;
	}
	
	/* (non-PHPdoc)
	 * @see kObjectChangedEventConsumer::shouldConsumeChangedEvent()
	*/
	public function shouldConsumeChangedEvent(BaseObject $object, array $modifiedColumns)
	{
		if($object instanceof EntryVendorTask 
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns) 
			&& $object->getStatus() == EntryVendorTaskStatus::PENDING 
			&& $object->getColumnsOldValue(EntryVendorTaskPeer::STATUS) == EntryVendorTaskStatus::PENDING_MODERATION
		)
			return true;
		
		if($object instanceof EntryVendorTask
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::ERROR
		)
			return true;
		
		if($object instanceof entry && $object->getType() == entryType::MEDIA_CLIP &&
			in_array(entryPeer::LENGTH_IN_MSECS, $modifiedColumns)
		)
			return true;
		
		if($object instanceof categoryEntry && $object->getStatus() == CategoryEntryStatus::ACTIVE)
			return true;
		
		if($object instanceof entry && in_array(entryPeer::STATUS, $modifiedColumns) && $object->getStatus() == entryStatus::READY)
			return true;
		
		return false;
	}
	
	/**
	 * @param BaseObject $object
	 * @param BatchJob $raisedJob
	 * @return bool true if should continue to the next consumer
	 */
	public function objectAdded(BaseObject $object, BatchJob $raisedJob = null)
	{
		if($object instanceof categoryEntry && $object->getStatus() == CategoryEntryStatus::ACTIVE)
			$this->checkAutomaticRules($object);
		
		return true;
	}
	
	/* (non-PHPdoc)
	 * @see kObjectAddedEventConsumer::objectAdded()
	 */
	public function objectCreated(BaseObject $object, BatchJob $raisedJob = null)
	{
		$this->updateReachProfileCreditUsage($object);
		return true;
	}
	
	/* (non-PHPdoc)
	 * @see kObjectChangedEventConsumer::objectChanged()
	 */ 
	public function objectChanged(BaseObject $object, array $modifiedColumns)
	{
		if($object instanceof EntryVendorTask && in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::PENDING
			&& $object->getColumnsOldValue(EntryVendorTaskPeer::STATUS) != EntryVendorTaskStatus::PENDING_MODERATION
		)
			return $this->updateReachProfileCreditUsage($object);
		
		if($object instanceof EntryVendorTask
			&& in_array(EntryVendorTaskPeer::STATUS, $modifiedColumns)
			&& $object->getStatus() == EntryVendorTaskStatus::ERROR
			&& in_array($object->getColumnsOldValue(EntryVendorTaskPeer::STATUS), array(EntryVendorTaskStatus::PENDING, EntryVendorTaskStatus::PROCESSING))
		)
			return $this->handleErrorTask($object);

		if($object instanceof entry && $object->getType() == entryType::MEDIA_CLIP &&
			in_array(entryPeer::LENGTH_IN_MSECS, $modifiedColumns)
		)
			return $this->handleEntryDurationChanged($object);
		
		if($object instanceof categoryEntry && $object->getStatus() == CategoryEntryStatus::ACTIVE)
			return $this->checkAutomaticRules($object);
		
		if($object instanceof entry && in_array(entryPeer::STATUS, $modifiedColumns) && $object->getStatus() == entryStatus::READY)
			return $this->checkAutomaticRules($object, true);
		
		return true;
	}
	
	private function updateReachProfileCreditUsage(EntryVendorTask $entryVendorTask)
	{
		ReachProfilePeer::updateUsedCredit($entryVendorTask->getReachProfileId(), $entryVendorTask->getPrice());
	}
	
	private function handleErrorTask(EntryVendorTask $entryVendorTask)
	{
		ReachProfilePeer::updateUsedCredit($entryVendorTask->getReachProfileId(), -$entryVendorTask->getPrice());
	}

	private function handleEntryDurationChanged(entry $entry)
	{
		$pendingEntryVendorTasks = EntryVendorTaskPeer::retrievePendingByEntryId($entry->getId(), $entry->getPartnerId());
		$addedCostByProfileId = array();
		foreach ($pendingEntryVendorTasks as $pendingEntryVendorTask)
		{
			/* @var $pendingEntryVendorTask EntryVendorTask */
			$oldPrice = $pendingEntryVendorTask->getPrice();
			$newPrice = kReachUtils::calculateTaskPrice($entry, $pendingEntryVendorTask->getCatalogItem());
			$priceDiff = $newPrice - $oldPrice;
			$pendingEntryVendorTask->setPrice($newPrice);
			
			if(!isset($addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()]))
				$addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()] = 0;
			
			if(kReachUtils::checkPriceAddon($pendingEntryVendorTask, $priceDiff))
			{
				$pendingEntryVendorTask->save();
				$addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()] += $priceDiff;
			}
			else
			{
				$pendingEntryVendorTask->setStatus(EntryVendorTaskStatus::ABORTED);
				$pendingEntryVendorTask->setPrice($newPrice);
				$pendingEntryVendorTask->setErrDescription("Current task price exceeded credit allowed, task was aborted");
				$pendingEntryVendorTask->save();
				$addedCostByProfileId[$pendingEntryVendorTask->getReachProfileId()] -= $oldPrice;
			}
		}
		
		foreach($addedCostByProfileId as $reachProfileId => $addedCost)
		{
			ReachProfilePeer::updateUsedCredit($reachProfileId, $addedCost);
		}
		
		return true;
	}
	
	public static function addEntryVendorTaskByObjectIds($entryId, $vendorCatalogItemId, $reachProfileId)
	{
		$entry = entryPeer::retrieveByPK($entryId);
		$reachProfile = ReachProfilePeer::retrieveByPK($reachProfileId);
		$vendorCatalogItem = VendorCatalogItemPeer::retrieveByPK($vendorCatalogItemId);

		$sourceFlavor = assetPeer::retrieveOriginalByEntryId($entry->getId());
		$sourceFlavorVersion = $sourceFlavor != null ? $sourceFlavor->getVersion() : 0;

		if( EntryVendorTaskPeer::retrieveEntryIdAndCatalogItemIdAndEntryVersion($entryId, $vendorCatalogItemId, $entry->getPartnerId(), $sourceFlavorVersion))
		{
			KalturaLog::err("Trying to insert a duplicate entry vendor task for entry [$entryId], catalog item [$vendorCatalogItemId] and entry version [$sourceFlavorVersion]");
			return true;
		}

		if(!kReachUtils::isEnoughCreditLeft($entry, $vendorCatalogItem, $reachProfile))
		{
			KalturaLog::err("Exceeded max credit allowed, Task could not be added for entry [$entryId] and catalog item [$vendorCatalogItemId]");
			return true;
		}
		
		$entryVendorTask = self::addEntryVendorTask($entry, $reachProfile, $vendorCatalogItem, false, $sourceFlavorVersion);
		return $entryVendorTask;
	}
	
	public static function addEntryVendorTask(entry $entry, ReachProfile $reachProfile, VendorCatalogItem $vendorCatalogItem, $validateModeration = true, $version = 0)
	{
		//Create new entry vendor task object
		$entryVendorTask = new EntryVendorTask();
		
		//Assign default parameters
		$entryVendorTask->setEntryId($entry->getId());
		$entryVendorTask->setCatalogItemId($vendorCatalogItem->getId());
		$entryVendorTask->setReachProfileId($reachProfile->getId());
		$entryVendorTask->setPartnerId($entry->getPartnerId());
		$entryVendorTask->setKuserId(kCurrentContext::getCurrentKsKuserId());
		$entryVendorTask->setUserId(kCurrentContext::$ks_uid);
		$entryVendorTask->setVendorPartnerId($vendorCatalogItem->getVendorPartnerId());
		$entryVendorTask->setVersion($version);
		$entryVendorTask->setQueueTime(null);
		$entryVendorTask->setFinishTime(null);
		
		//Set calculated values
		$entryVendorTask->setAccessKey(kReachUtils::generateReachVendorKs($entryVendorTask->getEntryId()));
		$entryVendorTask->setPrice(kReachUtils::calculateTaskPrice($entry, $vendorCatalogItem));
		
		$status = EntryVendorTaskStatus::PENDING;
		if($validateModeration && $reachProfile->shouldModerate($vendorCatalogItem->getServiceType()))
			$status = EntryVendorTaskStatus::PENDING_MODERATION;

		$dictionary = $reachProfile->getDictionaryByLanguage($vendorCatalogItem->getSourceLanguage());
		if ($dictionary)
			$entryVendorTask->setDictionary($dictionary->getData());

		$entryVendorTask->setStatus($status);
		$entryVendorTask->save();
		
		return $entryVendorTask;
	}
	
	private function checkAutomaticRules($object, $checkEmptyRulesOnly = false)
	{
		$scope = new kScope();
		$entryId = $object->getEntryId();
		$scope->setEntryId($entryId);
		$reachProfiles = ReachProfilePeer::retrieveByPartnerId($object->getPartnerId());
		foreach ($reachProfiles as $profile)
		{
			/* @var $profile ReachProfile */
			$fullFieldCatalogItemIds = $profile->fulfillsRules($scope, $checkEmptyRulesOnly);
			$existingCatalogItemIds = EntryVendorTaskPeer::retrieveExistingTasksCatalogItemIds($entryId, $fullFieldCatalogItemIds);
			$catalogItemIdsToAdd = array_unique(array_diff($fullFieldCatalogItemIds, $existingCatalogItemIds));
			
			foreach ($catalogItemIdsToAdd as $catalogItemIdToAdd)
			{
				self::addEntryVendorTaskByObjectIds($entryId, $catalogItemIdToAdd, $profile->getId());
			}
		}
		
		return true;
	}
}