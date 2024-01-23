<?php
/**
 * Enable FEATURE_EVENT_PLATFORM_PERMISSION to partners that have VIRTUALEVENT_PLUGIN_PERMISSION enabled
 *
 *
 * Examples:
 * php enableEventPlatformPermissionToPartners.php
 * php enableEventPlatformPermissionToPartners.php realrun
 *
 * @package Deployment
 * @subpackage updates
 */

$dryRun = true;
if (in_array('realrun', $argv))
	$dryRun = false;

$countLimitEachLoop = 500;
$offset = $countLimitEachLoop;

const VIRTUALEVENT_PLUGIN_PERMISSION = 'VIRTUALEVENT_PLUGIN_PERMISSION';
const FEATURE_EVENT_PLATFORM_PERMISSION = 'FEATURE_EVENT_PLATFORM_PERMISSION';

//------------------------------------------------------


require_once(__DIR__ . '/../../../deployment/bootstrap.php');

$con = myDbHelper::getConnection(myDbHelper::DB_HELPER_CONN_PROPEL2);
KalturaStatement::setDryRun($dryRun);

$c = new Criteria();
$c->addAscendingOrderByColumn(PartnerPeer::ID);
$c->addAnd(PartnerPeer::ID, 99, Criteria::GREATER_EQUAL);
$c->addAnd(PartnerPeer::STATUS,1, Criteria::EQUAL);
$c->setLimit($countLimitEachLoop);

$partners = PartnerPeer::doSelect($c, $con);

while (count($partners))
{
	foreach ($partners as $partner)
	{
		$existingEventPermission = PermissionPeer::getByNameAndPartner(FEATURE_EVENT_PLATFORM_PERMISSION, $partner->getId());
		if ($existingEventPermission)
		{
			print("Existing Event Platform permission on partner: [" . $partner->getId(). "] with status [" . $existingEventPermission->getStatus(). "] \n");
		}
		
		/* @var $partner Partner */
		$virtualEventPermission = PermissionPeer::getByNameAndPartner(VIRTUALEVENT_PLUGIN_PERMISSION, $partner->getId());
		if (!$virtualEventPermission || $virtualEventPermission->getStatus() != PermissionStatus::ACTIVE)
		{
			continue;
		}
		
		$eventPlatformPermission = PermissionPeer::getByNameAndPartner(FEATURE_EVENT_PLATFORM_PERMISSION, $partner->getId());
		if ($eventPlatformPermission && $eventPlatformPermission->getStatus() == PermissionStatus::ACTIVE)
		{
			print("Permission [" . FEATURE_EVENT_PLATFORM_PERMISSION . "] already set for partner id [". $partner->getId() ."] Skipping\n");
			continue;
		}
		if (!$eventPlatformPermission)
		{
			print("Create new permission [" . FEATURE_EVENT_PLATFORM_PERMISSION . "] for partner id [". $partner->getId() ."]\n");
			$eventPlatformPermission = new Permission();
			$eventPlatformPermission->setType(PermissionType::SPECIAL_FEATURE);
			$eventPlatformPermission->setPartnerId($partner->getId());
			$eventPlatformPermission->setName(FEATURE_EVENT_PLATFORM_PERMISSION);
		}
		
		$eventPlatformPermission->setStatus(PermissionStatus::ACTIVE);
		$eventPlatformPermission->save();
		print("Set permission [" . FEATURE_EVENT_PLATFORM_PERMISSION . "] for partner id [". $partner->getId() ."]\n");
	}
	
	kMemoryManager::clearMemory();
	$c = new Criteria();
	$c->addAscendingOrderByColumn(PartnerPeer::ID);
	$c->addAnd(PartnerPeer::ID, 99, Criteria::GREATER_EQUAL);
	$c->addAnd(PartnerPeer::STATUS,1, Criteria::EQUAL);
	$c->setLimit($countLimitEachLoop);
	$c->setOffset($offset);
	
	$partners = PartnerPeer::doSelect($c, $con);
	$offset +=  $countLimitEachLoop;
}

print("Done\n");
