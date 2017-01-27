<?php
/**
 * Created by PhpStorm.
 * User: milos.pejanovic
 * Date: 1/23/2017
 * Time: 1:38 PM
 */

namespace Dns;

class Repository {

	/**
	 * @var array
	 */
	private $defaultNameservers;

	/**
	 * Dns constructor.
	 * @param array $defaultNameservers
	 */
	public function __construct(array $defaultNameservers = array('8.8.8.8', '8.8.4.4')) {
		$this->defaultNameservers = $defaultNameservers;
	}

	/**
	 * @param $domain
	 * @param $type
	 * @param array $nameservers
	 * @return \Net_DNS2_RR[]
	 * @throws \Exception
	 */
	public function getRecord($domain, $type, array $nameservers = null) {
		if(is_null($nameservers)) {
			$nameservers = $this->defaultNameservers;
		}
		try {
			$resolver = new \Net_DNS2_Resolver(array('nameservers' => $nameservers));
			/** @var \Net_DNS2_Packet $result */
			$result = $resolver->query($domain, $type);
		}
		catch (\Net_DNS2_Exception $ex) {
			if ($ex->getCode() == 3) {
				throw new \Exception('Non-existent domain given: ' . $domain); //NXDOMAIN
			}
			throw $ex; //something else
		}

		$records = [];
		foreach ($result->authority as $record) {
			/** @var \Net_DNS2_RR $record */
			if(strtolower($record->name) == strtolower($domain) && $record->type == $type) {
				$records[] = $record;
			}
		}
		foreach ($result->answer as $record) {
			/** @var \Net_DNS2_RR $record */
			if(strtolower($record->name) == strtolower($domain) && $record->type == $type) {
				$records[] = $record;
			}
		}
		return $records;
	}
}