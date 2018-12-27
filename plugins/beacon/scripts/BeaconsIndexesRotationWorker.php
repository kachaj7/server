<?php
/**
 * @package plugins.beacon
 * @subpackage scripts
 */
class BeaconsIndexesRotationWorker extends ElasticIndexRotationWorker
{
	/**
	 * @param $currentSearchingIndices array
	 * @param $aliasesToRemove array
	 * @param $aliasesToAdd array
	 */
	protected function handleCurrentSearchIndices($currentSearchingIndices, &$aliasesToRemove, &$aliasesToAdd)
	{
		//remove old search aliases and old indexes we assume maxNumberOfIndices > 1
		//keep only $maxNumberOfIndices indices with search alias
		$count = 0;
		foreach ($currentSearchingIndices as $index)
		{
			$count++;
			if ($count >= $this->maxNumberOfIndices)
			{
				$aliasesToRemove[] = new ElasticIndexAlias($index, $this->indexPattern . kBeacon::ELASTIC_INDEX_OLD_POSTFIX . ($count - 1));
				$aliasesToRemove[] = new ElasticIndexAlias($index, $this->searchAlias);
			}
		}

		//Add the new old indices
		$count = 0;
		foreach ($currentSearchingIndices as $index)
		{
			$count++;
			if ($count < $this->maxNumberOfIndices)
			{
				$aliasesToAdd[]  = new ElasticIndexAlias($index, $this->indexPattern . kBeacon::ELASTIC_INDEX_OLD_POSTFIX . $count);
			}
		}
	}
}
