<?php
/**
 * @package plugins.podcastDistribution
 * @subpackage lib
 */
class PodcastDistributionProviderType implements IKalturaPluginEnum, DistributionProviderType
{
	const PODCAST = 'PODCAST';
	
	/**
	 * @return PodcastDistributionProviderType
	 */
	public static function get()
	{
		if(!self::$instance)
			self::$instance = new PodcastDistributionProviderType();
			
		return self::$instance;
	}
		
	public static function getAdditionalValues()
	{
		return array(
			'PODCAST' => self::PODCAST,
		);
	}
	
	public function getPluginName()
	{
		return PodcastDistributionPlugin::getPluginName();
	}	
}
