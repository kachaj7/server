<?php
/**
 * @package api
 * @subpackage filters
 */
class KalturaServerNodeFilter extends KalturaServerNodeBaseFilter
{
	/* (non-PHPdoc)
	 * @see KalturaFilter::getCoreFilter()
	 */
	protected function getCoreFilter()
	{
		return new ServerNodeFilter();
	}
	
	public function getTypeListResponse(KalturaFilterPager $pager, KalturaDetachedResponseProfile $responseProfile = null, $type = null)
	{
		list($list, $totalCount) = $this->doGetListResponse($pager, $type);
		$response = new KalturaServerNodeListResponse();
		$response->objects = KalturaServerNodeArray::fromDbArray($list, $responseProfile);
		$response->totalCount = $totalCount;
	
		return $response;
	}
	
	protected function doGetListResponse(KalturaFilterPager $pager, $type = null)
	{
		$c = KalturaCriteria::create(ServerNodePeer::OM_CLASS);
			
		if($type)
			$c->add(ServerNodePeer::TYPE, $type);
			
		$serverNodeFilter = $this->toObject();
		$serverNodeFilter->attachToCriteria($c);
		$pager->attachToCriteria($c);
			
		$list = ServerNodePeer::doSelect($c);
	
		return array($list, $c->getRecordsCount());
	}
	/* (non-PHPdoc)
	 * @see KalturaRelatedFilter::getListResponse()
	 */
	public function getListResponse(KalturaFilterPager $pager, KalturaDetachedResponseProfile $responseProfile = null)
	{
		return $this->getTypeListResponse($pager, $responseProfile);
	}
}
