<?php
/**
 * Template Resource.
 * @uri /template
 * @uri /template/:identifier
 */
class TemplateResource extends TokenResource {

	/**
	 * Retrieves an existing DNS template.
	 *
	 * @access public
	 * @param mixed $request Request parameters
	 * @return Response DNS template data if successful, false with error message otherwise.
	 */
	public function get($request, $identifier) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if (empty($identifier)) {
			$response->code = Response::BADREQUEST;
			$response->error = "Identifier was missing or invalid. Ensure that the identifier is in valid format.";
			return $response;
		}

		return $this->get_template($response, $identifier);
	}

	/**
	 * Create a new DNS template.
	 *
	 * {
	 * 	"identifier": <string>,
	 * 	"entries": 0..n {
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
	public function put($request) {
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

		return $this->create_template($response, $data);
	}

	/**
	 * Update an existing DNS template. This method will overwrite the entire Template.
	 *
	 * {
	 * 	"identifier": <string>,
	 * 	"entries": 0..n {
	 * 		"type": <string>,
	 * 		"value": <string>
	 * 	}
	 * }
	 *
	 * @access public
	 * @param mixed $request Request parameters
	 * @return Response True if request was successful, false with error message otherwise.
	 */
	public function post($request) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if ($data == null) {
			$response->code = Response::BADREQUEST;
			$response->error = "Request body was malformed. Ensure the body is in valid format.";
			return $response;
		}

		if (!isset($data->identifier) || !isset($data->entries) || empty($data->entries)) {
			$response->code = Response::BADREQUEST;
			$response->error = "Identifier and/or entries were missing or invalid. Ensure that the body is in valid format and all required parameters are present.";
			return $response;
		}

		return $response;
	}

	/**
	 * Delete an existing DNS template.
	 *
	 * {
	 * 	"identifier": <string>
	 * }
	 *
	 * @access public
	 * @params mixed $request Request parameters
	 * @return Response True if template was deleted, false with error message otherwise.
	 */
	public function delete($request) {
		$response = new FormattedResponse($request);
		$data = $request->parseData();

		if ($data == null) {
			$response->code = Response::BADREQUEST;
			$response->error = "Request body was malformed. Ensure the body is in valid format.";
			return $response;
		}

		if (!isset($data->identifier)) {
			$response->code = Response::BADREQUEST;
			$response->error = "Identifier and/or entries were missing or invalid. Ensure that the body is in valid format and all required parameters are present.";
			return $response;
		}

		return $response;
	}

	private function get_template($response, $identifier, &$out = null) {
		try {
			$connection = new PDO(PowerDNSConfig::DB_DSN, PowerDNSConfig::DB_USER, PowerDNSConfig::DB_PASS);
		} catch (PDOException $e) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not connect to PowerDNS server.";
			return $response;
		}

		$statement = $connection->prepare(sprintf(
			"SELECT z.id as z_id, z.name as z_name, z.descr as z_descr, r.name as r_name, r.type as r_type, r.content as r_content, r.ttl as r_ttl, r.prio as r_prio
			 FROM `%s` z
			 INNER JOIN `%s` r ON (z.id = r.zone_templ_id)
			 WHERE z.name = :name
			 ORDER BY r.id, r.prio;", PowerDNSConfig::DB_TEMPLATE_TABLE, PowerDNSConfig::DB_TEMPLATE_RECORDS_TABLE)
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
		$output['identifier'] = $identifier;
		$output['entries'] = array();

		while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
			$output['description'] = $row['z_descr'];
			$output['entries'][] = array(
				"name" => $row['r_name'],
				"type" => $row['r_type'],
				"content" => $row['r_content'],
				"ttl" => $row['r_ttl'],
				"priority" => $row['r_prio']
			);
		}

		if (empty($output['entries'])) {
			$response->code = Response::NOTFOUND;
			$response->body = array();
			$out = array();
		} else {
			$response->code = Response::CREATED;
			$response->body = $output;
			$out = $output;
		}

		return $response;
	}

	private function create_template($response, $data) {
		$response = $this->get_template($response, $data->identifier, $out);

		if (!empty($out)) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Resource already exists";
			return $response;
		}

		unset($out);

		try {
			$connection = new PDO(PowerDNSConfig::DB_DSN, PowerDNSConfig::DB_USER, PowerDNSConfig::DB_PASS);
		} catch (PDOException $e) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Could not connect to PowerDNS server.";
			return $response;
		}

		$connection->beginTransaction();

		$insert = $connection->prepare(sprintf("INSERT INTO `%s` (name, descr) VALUES (:name, :descr);", PowerDNSConfig::DB_TEMPLATE_TABLE));

		if ($insert->execute(array(":name" => $data->identifier, ":descr" => $data->description)) === false) {
			$response->code = Response::INTERNALSERVERERROR;
			$response->error = "Rolling back transaction, failed to insert template.";

			$connection->rollback();

			return $response;
		}

		$last_id = $connection->lastInsertId();

		$record = $connection->prepare(sprintf("INSERT INTO `%s` (zone_templ_id, name, type, content, ttl, prio) VALUES (:templ_id, :name, :type, :content, :ttl, :prio);", PowerDNSConfig::DB_TEMPLATE_RECORDS_TABLE));
		$record->bindParam(":templ_id", $r_templ_id);
		$record->bindParam(":name", $r_name);
		$record->bindParam(":type", $r_type);
		$record->bindParam(":content", $r_content);
		$record->bindParam(":ttl", $r_ttl, PDO::PARAM_INT);
		$record->bindParam(":prio", $r_prio, PDO::PARAM_INT);

		$r_templ_id = $last_id;

		foreach ($data->entries as $entry) {
			$r_name = $entry->name;
			$r_type = $entry->type;
			$r_content = $entry->content;
			if (!isset($entry->ttl)) {
				$r_ttl = PowerDNSConfig::DNS_DEFAULT_RECORD_TTL;
			} else {
				$r_ttl = $entry->ttl;
			}
			if (!isset($entry->priority)) {
				$r_prio = PowerDNSConfig::DNS_DEFAULT_RECORD_PRIORITY;
			} else {
				$r_prio = $entry->priority;
			}

			if ($record->execute() === false) {
				$response->code = Response::INTERNALSERVERERROR;
				$response->error = sprintf("Rolling back transaction, failed to insert template record - name: '%s', type: '%s', content: '%s', ttl: '%s', prio: '%s'", $r_name, $r_type, $r_content, $r_ttl, $r_prio);

				$connection->rollback();

				return $response;
			}
		}

		$connection->commit();

		$response->body = true;

		return $response;
	}
}
