<?php
/**
 * Created by PhpStorm.
 * User: milos.pejanovic
 * Date: 1/23/2017
 * Time: 1:38 PM
 */

namespace Dns;

class Service {

	/**
	 * @var Repository
	 */
	private $repository;

	/**
	 * Service constructor.
	 * @param Repository $repository
	 */
	public function __construct($repository) {
		$this->repository = $repository;
	}

	/**
	 * @param $domain
	 * @param array|null $nameservers
	 * @return string
	 * @throws \Exception
	 * @throws \Net_DNS2_Exception
	 */
	public function findParent($domain, array $nameservers = null) {
		$check = false;
		$parent = $this->removeLabel($domain);
		$records = $this->repository->getRecord($parent, 'SOA', $nameservers);
		if($parent == '.' || !empty($records)) {
			$check = true;
		}
		if(!$check) {
			$parent = $this->findParent($parent);
		}
		return $parent;
	}

	/**
	 * @param $domain
	 * @param array|null $nameservers
	 * @return array
	 * @throws \Exception
	 * @throws \Net_DNS2_Exception
	 */
	public function findNameservers($domain, array $nameservers = null) {
		/** @var \Net_DNS2_RR_NS[] $records */
		$records = $this->repository->getRecord($domain, 'NS', $nameservers);
		if(empty($records)) {
			throw new \Exception('No NS records found for host ' . $domain);
		}
		$nsArray = [];
		foreach ($records as $record) {
			$nsArray[] = $record->nsdname;
		}
		return $nsArray;
	}

	/**
	 * @param $host
	 * @param array|null $nameservers
	 * @return string
	 * @throws \Exception
	 * @throws \Net_DNS2_Exception
	 */
	public function resolveHost($host, array $nameservers = null) {
		/** @var \Net_DNS2_RR_A[] $records */
		$records = $this->repository->getRecord($host, 'A', $nameservers);

		if(!isset($records[0]->address)) {
			throw new \Exception('Could not resolve host ' . $host);
		}
		return $records[0]->address;
	}

	/**
	 * @param $domain
	 * @param array $nameservers
	 * @return array
	 * @throws \Exception
	 * @throws \Net_DNS2_Exception
	 */
	public function findDelegationNameservers($domain, array $nameservers = null) {
		/** @var \Net_DNS2_RR_NS[] $records */
		$records = $this->repository->getRecord($domain, 'NS', $nameservers);
		$delegationNS = [];
		foreach($records as $record) {
			$delegationNS[] = $record->nsdname;
		}
		if(empty($delegationNS)) {
			throw new \Exception('Domain ' . $domain . ' not delegated/registered.');
		}
		return $delegationNS;
	}

	/**
	 * @param string $domain
	 * @param string $serial
	 * @param array $nameservers
	 * @throws \Exception
	 * @throws \Net_DNS2_Exception
	 */
	public function checkDomainSerial($domain, $serial, array $nameservers = null) {
		/** @var \Net_DNS2_RR_SOA[] $records */
		$soaRecords = $this->repository->getRecord($domain, 'SOA', $nameservers);
		if(isset($soaRecords[0]->serial) && $soaRecords[0]->serial != $serial) {
			throw new \Exception('Local SOA serial number for domain ' . $domain . ' does not match the given serial.');
		}
	}

	/**
	 * @param $domain
	 * @param array $nameservers
	 * @return bool
	 * @throws \Exception
	 */
	public function areNameserversDelegated($domain, array $nameservers) {
		if(empty($nameservers)) {
			throw new \Exception('No nameservers given');
		}
		$result = true;
		$parent = $this->findParent($domain);
		$parentNameservers = $this->findNameservers($parent);
		$parentNsIps = [];
		foreach($parentNameservers as $parentNameserver) {
			$parentNsIps[] = $this->resolveHost($parentNameserver);
		}
		$delegationNameservers = $this->findDelegationNameservers($domain, $parentNsIps);
		foreach($nameservers as $nameserver) {
			if(!in_array($nameserver, $delegationNameservers)) {
				$result = false;
				break;
			}
		}
		return $result;
	}

	protected function removeLabel($domain) {
		$domainPieces = explode(".", $domain, 2);
		$parent = '.';
		if(count($domainPieces) > 1) {
			$parent = $domainPieces[1];
		}
		return $parent;
	}
}