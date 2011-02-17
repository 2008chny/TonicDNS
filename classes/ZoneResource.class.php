<?php
/**
 * Zone Resource.
 * @uri /zone
 * @uri /zone/:identifier
 */
class ZoneResource extends TokenResource {

	/**
	 * Retrieves an existing DNS zone.
	 *
	 * If no identifier is specified, all zones will be retrieved without records.
	 * If an identifier is specified, one zone will be retrieved with records.
	 *
	 * @access public
	 * @param mixed $request Request parameters
	 * @return Response DNS zone data if successful, false with error message otherwise.
	 */
	public function get($request, $identifier = null) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if (empty($identifier)) {
			return $this->get_all_zones($response);
		} else {
			return $this->get_zone($response, $identifier);
		}
	}

	/**
	 * Create a new DNS zone.
	 *
	 * {
	 * 	"name": <string>,
	 * 	"master": ipv4,
	 * 	"type": master|slave|native,
	 * 	"records": 0..n {
	 * 		"name": <string>,
	 * 		"type": <string>,
	 * 		"content": <string>,
	 * 		"ttl": <int>,
	 * 		"priority": <int>
	 * 	}
	 * }
	 *
	 * @access public
	 * @param mixed $request Request parameters
	 * @return Response True if request was successful, false with error message otherwise.
	 */
	public function put($request, $identifier = null) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if ($data == null) {
			$response->code = Response::BADREQUEST;
			$response->error = "Request body was malformed. Ensure the body is in valid format.";
			return $response;
		}

		if (!isset($data->identifier) || !isset($data->description) || !isset($data->entries) || empty($data->entries)) {
			$response->code = Response::BADREQUEST;
			$response->error = "Identifier and/or entries were missing or invalid. Ensure that the body is in valid format and all required parameters are present.";
			return $response;
		}

		return $this->create_zone($response, $data);
	}

	/**
	 * Update an existing DNS zone. This method will overwrite the entire Zone. Only works for zones, not records.
	 *
	 * {
	 * 	"name": <string>,
	 * 	"master": ipv4,
	 * 	"type": master|slave|native,
	 * }
	 *
	 * @access public
	 * @param mixed $request Request parameters
	 * @return Response True if request was successful, false with error message otherwise.
	 */
	public function post($request, $identifier = null) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if ($data == null) {
			$response->code = Response::BADREQUEST;
			$response->error = "Request body was malformed. Ensure the body is in valid format.";
			return $response;
		}

		if (empty($identifier) || !isset($data->identifier) || !isset($data->entries) || empty($data->entries)) {
			$response->code = Response::BADREQUEST;
			$response->error = "Identifier and/or entries were missing or invalid. Ensure that the body is in valid format and all required parameters are present.";
			return $response;
		}

		return $this->modify_zone($response, $data);
	}

	/**
	 * Delete an existing DNS zone, or delete a record from the zone.
	 *
	 * If an identifier is specified, the entire zone will be deleted.
	 * If a body is specified, but no identifier, the specified entries will be deleted from the zone.
	 *
	 * {
	 * 	"name": <string>,
	 * 	"records": 1..n {
	 * 		"name": <string>,
	 * 		"type": <string>,
	 * 		"priority": <int>,
	 * 	}
	 *
	 * @access public
	 * @params mixed $request Request parameters
	 * @return Response True if zone was deleted, false with error message otherwise.
	 */
	public function delete($request, $identifier = null) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if (empty($identifier)) {
			$response->code = Response::BADREQUEST;
			$response->error = "Identifier and/or entries were missing or invalid. Ensure that the body is in valid format and all required parameters are present.";
			return $response;
		}

		return $this->delete_zone($response, $identifier);
	}

	private function get_all_zones($response, &$out = null) {
		try {
			$connection = new PDO(PowerDNSConfig::DB_DSN, PowerDNSConfig::DB_USER, PowerDNSConfig::DB_PASS);
		} catch (PDOException $e) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not connect to PowerDNS server.";
			return $response;
		}

		$result = $connection->query(sprintf(
			"SELECT z.id as z_id, z.name as z_name, z.master as z_master, z.last_check as z_last_check, z.type as z_type, z.notified_serial as z_notified_serial
			 FROM `%s` z
			 ORDER BY z_name ASC;", PowerDNSConfig::DB_ZONE_TABLE)
		);

		if ($result === false) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not query PowerDNS server.";
			return $response;
		}

		$output = array();
		while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false ) {
			$zone = array();
			$zone['name'] = $row['z_name'];
			$zone['type'] = $row['z_type'];
			if (!empty($row['z_master'])) { $zone['master'] = $row['z_master']; }
			if (!empty($row['z_last_check'])) { $zone['last_check'] = $row['z_last_check']; }
			if (!empty($row['z_notified_serial'])) { $zone['notified_serial'] = $row['z_notified_serial']; }

			$output[] = $zone;
		}

		$response->body = $output;
		$out = $output;
		return $response;
	}

	private function get_zone($response, $identifier, &$out = null) {
		try {
			$connection = new PDO(PowerDNSConfig::DB_DSN, PowerDNSConfig::DB_USER, PowerDNSConfig::DB_PASS);
		} catch (PDOException $e) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not connect to PowerDNS server.";
			return $response;
		}

		$statement = $connection->prepare(sprintf(
			"SELECT z.id as z_id, z.name as z_name, z.master as z_master, z.last_check as z_last_check, z.type as z_type, z.notified_serial as z_notified_serial,
			        r.id as r_id, r.name as r_name, r.type as r_type, r.content as r_content, r.ttl as r_ttl, r.prio as r_prio, r.change_date as r_change_date
			 FROM `%s` z
			 INNER JOIN `%s` r ON (z.id = r.domain_id)
			 WHERE z.name = :name
			 ORDER BY r_name ASC;", PowerDNSConfig::DB_ZONE_TABLE, PowerDNSConfig::DB_RECORD_TABLE)
		);

		if ($statement === false) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not query PowerDNS server.";
			return $response;
		}

		if ($statement->execute(array(":name" => $identifier)) === false) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not query PowerDNS server.";
			return $response;
		}

		$output = array();
		$first = true;
		while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
			if ($first) {
				$output['name'] = $row['z_name'];
				$output['type'] = $row['z_type'];
				if (!empty($row['z_master'])) { $output['master'] = $row['z_master']; }
				if (!empty($row['z_last_check'])) { $output['last_check'] = $row['z_last_check']; }
				if (!empty($row['z_notified_serial'])) { $output['notified_serial'] = $row['z_notified_serial']; }
				$first = false;
			}

			$record = array();
			$record['name'] = $row['r_name'];
			$record['type'] = $row['r_type'];
			$record['content'] = $row['r_content'];
			$record['ttl'] = $row['r_ttl'];
			$record['priority'] = $row['r_prio'];
			if (!empty($row['r_change_date'])) { $record['change_date'] = $row['r_change_date']; }

			$output['records'][] = $record;
		}

		if (empty($output)) {
			$response->code = Response::NOTFOUND;
			$response->body = array();
			$out = array();
		} else {
			$response->code = Response::OK;
			$response->body = $output;
			$out = $output;
		}

		return $response;
	}

	private function create_zone($response, $data, &$out = null) {

	}

	private function create_record($response, $identifier, $data, &$out = null) {

	}

	private function modify_zone($response, $data, &$out = null) {

	}

	private function delete_zone($response, $identifier, &$out = null) {

	}

	private function delete_record($response, $identifier, $records, &$out = null) {

	}
}
