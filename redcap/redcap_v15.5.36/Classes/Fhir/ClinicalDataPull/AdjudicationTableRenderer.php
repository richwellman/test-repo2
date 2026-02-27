<?php namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull;

use Project;
use Records;
use Language;
use DateTimeRC;
use DynamicDataPull;

class AdjudicationTableRenderer {
    /**
     *
     * @var Project
     */
    private $project;

    /**
     *
     * @var array
     */
    private $field_mappings;

    /**
     *
     * @var array
     */
    private $lang;

    private $record;
    private $event_id;
    private $day_offset;
    private $day_offset_plusminus;
    private $data_array_src;
    private $data_array_rc;
    private $form_data;
    private $output_html;
    private $record_exists;
    private $show_excluded;
    private $instance;
    private $repeat_instrument;
    private $isAjax;
    private $longitudinal;

    public function __construct(
        Project $project,
        $record,
        $params = [
        'event_id' => null,
        'day_offset' => 0,
        'day_offset_plusminus' => '+-',
        'data_array_src' => [],
        'data_array_rc' => [],
        'form_data' => [],
        'output_html' => false,
        'record_exists' => null,
        'show_excluded' => null,
        'instance' => 1,
        'repeat_instrument' => '',
        'isAjax' => null,
        'longitudinal' => null,
    ]) {
        // Initialize class variables based on the provided parameters
        $this->project = $project;
        $project_language = $this->project->project['project_language'] ?? null;
        $this->lang = Language::getLanguage($project_language);
        $this->record = $record;
        $this->event_id = $params['event_id'];
        $this->day_offset = $params['day_offset'];
        $this->day_offset_plusminus = $params['day_offset_plusminus'];
        $this->data_array_src = $params['data_array_src'];
        $this->data_array_rc = $params['data_array_rc'];
        $this->form_data = $params['form_data'];
        $this->output_html = $params['output_html'];
        $this->record_exists = $params['record_exists'];
        $this->show_excluded = $params['show_excluded'];
        $this->instance = $params['instance'];
        $this->repeat_instrument = $params['repeat_instrument'];
        $this->isAjax = $params['isAjax'];
        $this->longitudinal = $params['longitudinal'];
    }

    public function tt($key) { return $this->lang[$key] ?? "-- no translation available for $key"; }
    public function getRecord() { return $this->record; }
    public function getProject() { return $this->project; }
    public function getProjectID() { return $this->project->project_id; }
    public function getEventId() { return $this->event_id; }
    public function getDayOffset() { return $this->day_offset; }
    public function getDayOffsetPlusminus() { return $this->day_offset_plusminus; }
    public function getSourceDataArray() { return $this->data_array_src; }
    public function getREDCapDataArray() { return $this->data_array_rc; }
    public function getFormData() { return $this->form_data; }
    public function getOutputHtml() { return $this->output_html; }
    public function getRecordExists() { return $this->record_exists; }
    public function getShowExcluded() { return $this->show_excluded; }
    public function getInstance() { return $this->instance; }
    public function getRepeatInstrument() { return $this->repeat_instrument; }
    public function getIsAjax() { return $this->isAjax; }
    public function getLongitudinal() { return $this->longitudinal; }

    public function render() {
        $sourceData = $this->getSourceDataArray();
        // Check for data or error with web service and display an error message if needed
        $error = $this->displayErrorMessage($sourceData);
        if($error) return $error;

        // Get mappings from external source to REDCap
        $mappings = $this->getMappings();

        // Loop through all mapped fields to get fields/event_ids needed for REDCap data pull
        list($fields, $temporalFields, $events, $mappingIDs, $simplifiedMappings ) = $this->getMappedData($mappings);

        // add status to fields in repeating forms
        $fields = $this->includeFormStatusForRepeatingForms($fields);

        // Retrieve arrays of md_ids for non-adjudicated, adjudicated, and excluded values for the given record
        $nonAdjudicatedValues = $this->getNonAdjudicatedValues($mappingIDs);
        $adjudicatedValues = $this->getAdjudicatedValues($mappingIDs);
        // $excludedValues = $this->getExcludedValues($record, $mappingIDs);

        // Loop through mapped REDCap fields and prepare data for multiple-choice fields
        $fieldsChoices = $this->prepareChoiceFieldsData($fields);

        // get existing data
        $existingData = $this->getExistingData($fields, $temporalFields, $events);

        // Collect form_name/event_ids that are locked for this record
        $lockedFormEvents = $this->getLockedFormsEvents();

        // Convert the source data into another array with src_field as the key
        list($transformedData, $md_ids_event_ids, $md_ids_fields) = $this->transformSourceData($sourceData);

        // Merge all REDCap field info and data with all Source field info and data
        $mergedData = $this->initREDCapSourceData($sourceData, $existingData, $temporalFields);

        // update the structure of the merged data
        $normalizedMergedData = $this->addCachedData($mergedData, $md_ids_event_ids, $md_ids_fields, $md_ids_out_of_range);

        // Handle field validation
        $validationFields = $this->validateFields($fields);

        // Set up HTML labels for various scenarios
        list(
        'invalidSrcValue' => $invalidSrcValueLabel,
        'sameSingleSrcValue' => $sameSingleSrcValueLabel,
        'lockedSrcValue' => $lockedSrcValueLabel,
        'outOfRangeSrcValue' => $outOfRangeSrcValueLabel,
        ) = $this->prepareLabels();

        list($external_id_rc_event, $external_id_rc_field, $external_id_value) = $this->getExternalSystemIdField($mappings, $existingData);

        // Sort all the data about to be displayed in the table
        $sortedData = $this->sortDataForDisplay($normalizedMergedData);

        // Loop through all REDCap fields to create each as a table row
        $this->generateTableRows($sortedData);

        // Compile and return the final HTML table
        return $this->finalizeTable();
    }

  private function displayErrorMessage($data) {
        // TODO: Implement the display of an error message if there's an issue with the web service data
        		// If no data or error with web service, then display error message
		if ($data === false || empty($data)) {
            $html = '
                <div class="darkgreen" style="padding:10px;max-width:100%;">
                    <img src="accept.png" />'.$this->tt('ws_140').
                '</div>
            ';
			return 	array(0, $html);
		}
        return false;
    }

    private function initFieldMappings() {
        $project = $this->getProject();
        // Put fields in array
        $this->field_mappings = [];
        // Query table
        $sql = "SELECT * FROM redcap_ddp_mapping WHERE project_id = ?
                ORDER BY is_record_identifier DESC, external_source_field_name, event_id, field_name, temporal_field";
        $q = db_query($sql, [$this->getProjectID()]);
        while ($row = db_fetch_assoc($q))
        {
            // If event_id is orphaned, then skip it
            if (!isset($project->eventInfo[$row['event_id']])) continue;
            // If field is orphaned, then skip it
            if (!isset($project->metadata[$row['field_name']])) continue;
            // Initialize sub-array, if not initialized
            if (!isset($this->field_mappings[$row['external_source_field_name']])) {
                $this->field_mappings[$row['external_source_field_name']] = [];
            }
            // Add to array
            $this->field_mappings[$row['external_source_field_name']][$row['event_id']][$row['field_name']] = [
                'map_id' => $row['map_id'],
                'is_record_identifier' => $row['is_record_identifier'],
                'temporal_field' => $row['temporal_field'],
                'preselect' => $row['preselect']
            ];
        }
    }

    /**
     * Get existing REDCap data for a given record and merges it with additional form data if present.
     *
     * This function retrieves saved REDCap data for the specified record, fields, and events.
     * If additional form data is provided and the event ID is numeric, it merges this data
     * into the fetched data. This is especially relevant for capturing non-saved values
     * currently on a form, including support for repeating instruments and events.
     *
     * @param string $record The record ID for which data is being fetched.
     * @param array $rc_mapped_fields Array of REDCap mapped fields.
     * @param array $temporal_fields Array of temporal fields.
     * @param array $rc_mapped_events Array of REDCap mapped events.
     * @param array $form_data Additional form data to merge with existing data.
     * @param int|string $event_id The current event ID.
     * @param int $instance Instance number of the repeating instrument or event.
     * @param string $repeat_instrument Name of the repeating instrument, if applicable.
     * @return array The merged array of existing REDCap data and additional form data.
     */
    private function getExistingData($mapped_fields, $temporal_fields, $mapped_events) {
        $event_id = $this->getEventId();
        $instance = $this->getInstance();
        $repeat_instrument = $this->getRepeatInstrument();
        $form_data = $this->getFormData();
        // Pull saved REDCap data for this record for the mapped fields
        $existingData = Records::getData($this->getProjectID(), 'array', $this->getRecord(), array_merge($mapped_fields, $temporal_fields), $mapped_events);

        // Merge with form data if available
        if (!empty($form_data) && is_numeric($event_id)) {
            foreach ($form_data as $key => $val) {
                if ($instance < 1) {
                    $existingData[$this->getRecord()][$event_id][$key] = $val;
                } else {
                    // Add in repeating instrument format
                    $existingData[$this->getRecord()]['repeat_instances'][$event_id][$repeat_instrument][$instance][$key] = $val;
                }
            }
        }
        return $existingData;
    }


    /**
     * Get list of fields already mapped to external source fields
     *
     * @return array    fields with external source field as 1st level key, REDCap event_id as 2nd level key,
     *                  REDCap field name as 3rd level key,and sub-array of attributes (temporal_field, is_record_identifier).
     */
    private function getMappings() {
		// If class variable is null, then create mapped field array
		if ($this->field_mappings === null) $this->initFieldMappings();
		// Return the array of field mappings
		return $this->field_mappings;
    }

    /**
     * Loop through all mapped fields to get fields/event_ids needed for REDCap data pull
     *
     * @param array $mappings
     * @return array [REDCap mapped fields, event IDs, mapping IDs]
     */
    private function getMappedData($mappings) {
		$rc_mapped_fields = $rc_mapped_events = $map_ids = $map_id_list = $temporal_fields = [];
		foreach ($mappings as $src_field=>$event_attr) {
			foreach ($event_attr as $this_event_id=>$field_attr) {
				// Add event_id
				$rc_mapped_events[] = $this_event_id;
				// Loop through fields
				foreach ($field_attr as $rc_field=>$attr) {
					// Add field
					$rc_mapped_fields[] = $rc_field;
					// Segregate map_id's into separate array with event_id-field as keys
					$map_ids[$src_field][$this_event_id][$rc_field] = $attr['map_id'];
					// Put all map_ids in an array
					$map_id_list[] = $attr['map_id'];
					// Add temporary field to array
					if ($attr['temporal_field'] != '') {
						$temporal_fields[$rc_field] = $attr['temporal_field'];
					}
				}
			}
		}
		$rc_mapped_events = array_unique($rc_mapped_events);
		$rc_mapped_fields = array_unique($rc_mapped_fields);
        return [
            $rc_mapped_fields, // REDCap fields
            $temporal_fields, // REDCap temporal fields
            $rc_mapped_events, // REDCap event IDs
            $map_id_list, // IDs of mappings
            $map_ids, // simplified version of the mappings array
        ];
    }

    /**
     * Includes form status fields for each mapped field in projects with repeating forms.
     *
     * This function checks if the project is using repeating forms and events. If so,
     * it adds the form status field ('_complete') for each mapped field. This ensures
     * that every instance of that form is included when retrieving data using getData().
     *
     * @param array $fields An array of REDCap mapped fields.
     * @return void Modifies the $fields array to include form status fields.
     */
    private function includeFormStatusForRepeatingForms($fields) {
		if ($this->getProject()->hasRepeatingFormsEvents()) {
			$mapped_fields_form_status = array();
			foreach ($fields as $this_field) {
				$mapped_fields_form_status[] = $this->getProject()->metadata[$this_field]['form_name']."_complete";
			}
			$fields = array_merge($fields, array_unique($mapped_fields_form_status));
		}
        return $fields;
      }

    /**
     * get data from the ddp cache table
     *
     * @param string $record
     * @param array $map_id_list
     * @param bool $adjudicated
     * @return array
     */
    private function getCachedData($map_id_list, $adjudicated=null) {
        $list = [];
        $uniqueMapIDs = array_unique($map_id_list);
		if (!empty($uniqueMapIDs)) {
			$placeholders = dbQueryGeneratePlaceholdersForArray($uniqueMapIDs);
            $params = array_merge($uniqueMapIDs, [$this->getRecord(), $this->getProjectID()]);
			$sql = "SELECT d.md_id FROM redcap_ddp_records r, redcap_ddp_records_data d
					WHERE d.mr_id = r.mr_id AND d.map_id IN ($placeholders)
					AND r.record = ? AND r.project_id = ?";
            if(!is_null($adjudicated)) {
                $sql .= " AND adjudicated = ?";
                $params[] = boolval($adjudicated) ? 1 : 0;
            }
			$q = db_query($sql, $params);
			while ($row = db_fetch_assoc($q)) {
				$list[$row['md_id']] = true;
			}
		}
		return $list;
    }

    /**
     * get non-adjudicated values from the ddp cache table
     *
     * @param string $record
     * @param array $map_id_list
     * @return array
     */
    private function getAdjudicatedValues($map_id_list) {
		return $this->getCachedData($map_id_list, $adjudicated=true);
    }

    /**
     * get adjudicated values from the ddp cache table
     *
     * @param string $record
     * @param array $map_id_list
     * @return array
     */
    private function getNonAdjudicatedValues($map_id_list) {
		return $this->getCachedData($map_id_list, $adjudicated=false);
    }

    /**
     * DO NOT USE THIS FEATURE 
     *
     * @return array
     */
    private function getExcludedValues($map_id_list) { return []; }

    /**
     * filter fields to include only those with multiple choices.
     *
     * @param array $rc_mapped_fields
     * @return array multi-choice fields with matching options
     */
    private function prepareChoiceFieldsData($rc_fields) {
        $list = [];
        $project = $this->getProject();
		foreach ($rc_fields as $this_field) {
			if ($project->isMultipleChoice($this_field)) {
				$list[$this_field] = parseEnum($project->metadata[$this_field]['element_enum']);
			}
		}
        return $list;
    }

    /**
     * Gets locked forms and events for a specific record.
     *
     * This function queries the REDCap locking data to find all forms and events
     * that are locked for a given record. It organizes the data into a structured array
     * where the first level key is the event_id and the second level key is the form_name.
     * It also handles the differentiation between repeating and non-repeating forms/events
     * by setting the instance number appropriately.
     *
     * @param string $record The specific record ID for which locked forms and events are being fetched.
     * @return array An associative array structured with event_id and form_name as keys,
     *               and the instances of each form/event that are locked.
     *               Format: [event_id][form_name][instance] = true
     *
     */
    private function getLockedFormsEvents() {
		$lockedFormsEvents = [];
		$sql = "SELECT event_id, form_name, instance FROM redcap_locking_data 
				WHERE project_id = ? AND record = ?";
		$q = db_query($sql. [$this->getProjectID(), $this->getRecord()]);
        $project = $this->getProject();
		while ($row = db_fetch_assoc($q)) {
			// Set instance to 0 for non-repeating forms
			if (!$project->isRepeatingForm($row['event_id'], $row['form_name']) && !$project->isRepeatingEvent($row['event_id'])) {
				$row['instance'] = 0;
			}
			$lockedFormsEvents[$row['event_id']][$row['form_name']][$row['instance']] = true;
		}
        return $lockedFormsEvents;
    }

    // Convert the source data into other array with src_field as key (for better searching)
    private function transformSourceData($data) {
		$transformedData = $md_ids_event_ids = $md_ids_fields = [];
		foreach ($data as $attr) {
			// Add SRC value and timestamp (if a temporal field)
			if (isset($attr['timestamp'])) {
				// Clean the timestamp in case ends with ".0" or anything else
				$attr['timestamp'] = substr($attr['timestamp'], 0, 19);
				// Add to array
				$transformedData[$attr['field']][] = array('src_value'=>$attr['value'], 'src_timestamp'=>$attr['timestamp'], 'md_id'=>$attr['md_id']);
			} else {
				// Add to array
				$transformedData[$attr['field']][] = array('src_value'=>$attr['value'], 'md_id'=>$attr['md_id']);
			}
			// Map event_id to md_id for filtering later when using multiple events of data, in which some of the same data may be cached in separate events (i.e. duplicates)
			$md_ids_event_ids[$attr['md_id']] = $attr['event_id'];
			// Map field to md_id
			$md_ids_fields[$attr['md_id']] = $attr['rcfield'];
		}
        return [$transformedData, $md_ids_event_ids, $md_ids_fields];
    }

    /**
     * Merge all REDCap field info and data with all Source field info (data added later)
     *
     * @param array $sourceData
     * @param array $existingData
     * @param array $temporal_fields
     * @return array
     */
    private function initREDCapSourceData($sourceData, $existingData, $temporal_fields) {
        $rc_source_data = [];
        $project = $this->getProject();
		foreach ($sourceData as $attr) {
			// If this field is on a repeating form...
			if ($project->isRepeatingEvent($attr['event_id']) || $project->isRepeatingForm($attr['event_id'], $project->metadata[$attr['rc_field']]['form_name'])) {
				$data_array_rc_instances = $existingData[$this->getRecord()]['repeat_instances'][$attr['event_id']] ?? array();
			} else {
				$data_array_rc_instances = array(""=>array(0=>$existingData[$this->getRecord()][$attr['event_id']]));
			}
			foreach ($data_array_rc_instances as $this_repeat_instrument=>$iattr4) {
				foreach ($iattr4 as $this_instance=>$this_instance_data) {
					// Add RC data value, if exists
					$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['rc_value'] = $this_instance_data[$attr['rc_field']];
					// Add RC timestamp (if a temporal field)
					if (($this_instance > 0 ? isset($this_instance_data[$temporal_fields[$attr['rc_field']]]) : isset($attr['timestamp']))) {
						// With multiple instances, we'll have to retrieve the timestamp from the instance data
						$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['rc_timestamp'] = ($this_instance > 0 ? $this_instance_data[$temporal_fields[$attr['rc_field']]] : $attr['timestamp']);
						$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['rc_preselect'] = $attr['preselect'];
					}
					// Add src_field placeholder
					$rc_source_data[$attr['event_id']][$this_repeat_instrument][$this_instance][$attr['rc_field']]['src_fields'][$attr['src_field']] = array();
				}
			}
			unset($data_array_rc_instances);
		}
        return $rc_source_data;
    }

    /**
     * Now loop through the $rc_source_data and pile the source data on top (and also checking if timestamp is in range for that event)
     *
     * @param array $rc_source_data
     * @param array $md_ids_out_of_range Store the md_id's not in range that won't get displayed
     * @return void
     */
    private function addCachedData($rc_source_data, $md_ids_event_ids, $md_ids_fields, &$md_ids_out_of_range = []) {
        $project = $this->getProject();
        $day_offset = $this->getDayOffset();
        $day_offset_plusminus = $this->getDayOffsetPlusminus();

		foreach ($rc_source_data as $this_event_id=>$evt_attr3) {
			foreach ($evt_attr3 as $this_repeat_instrument=>$iattr3) {
				foreach ($iattr3 as $this_instance=>$bttr3) {
					foreach ($bttr3 as $rc_field=>$fld_attr5) {
						foreach (array_keys($fld_attr5['src_fields']) as $src_field) {
							// If src_field exists in $source_data, then add it to $rc_source_data
							if (isset($source_data[$src_field])) {
								// If temporal, then add as array
								if ($project->isCheckbox($rc_field) || isset($source_data[$src_field][0]['src_timestamp'])) {
									// Loop through each timestamp and determine if is in datetime range given the day offset
									foreach ($source_data[$src_field] as $this_src_val_time) {
										// If the md_id of this value does not belong to this_event_id, then skip
										if ($md_ids_event_ids[$this_src_val_time['md_id']] != $this_event_id) continue;
										// If the md_id of this value is not mapped to this field, then skip
										if ($md_ids_fields[$this_src_val_time['md_id']] != $rc_field) continue;
										// Is date in range?
										//print "$this_event_id,$this_instance, $src_field; times: {$this_src_val_time['src_timestamp']}, {$fld_attr5['rc_timestamp']}\n";
										if ($project->isCheckbox($rc_field) || self::dateInRange($this_src_val_time['src_timestamp'], $fld_attr5['rc_timestamp'], $day_offset, $day_offset_plusminus)) {
											// Add temporal value
											$rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field][] = $this_src_val_time;
										} else {
											// Store the md_id's not in range that won't get displayed
											$md_ids_out_of_range[] = $this_src_val_time['md_id'];
										}
									}
								}
								// If not temporal, add just as single value
								else {
									$rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field] = $source_data[$src_field][0];
								}
							}
							// If src_field attribute array is empty, then remove it (didn't have data that was in the date range)
							if (is_array($rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field])
								&& empty($rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field]))
							{
								unset($rc_source_data[$this_event_id][$this_repeat_instrument][$this_instance][$rc_field]['src_fields'][$src_field]);
							}
						}
					}
				}
				// Sort the subarrays by instance #
				$iattr = $rc_source_data[$this_event_id][$this_repeat_instrument];
				ksort($iattr);
				$rc_source_data[$this_event_id][$this_repeat_instrument] = $iattr;
			}
			// Sort the subarrays by repeat instrument name
			$evt_attr = $rc_source_data[$this_event_id];
			ksort($evt_attr);
			$rc_source_data[$this_event_id] = $evt_attr;
		}
        return $rc_source_data;
    }

    /**
	 * Determine if a date[time] falls within a window of time by using a base date[time] +- offset
	 */
	private static function dateInRange($dateToCheck, $dateBase, $dayOffset, $day_offset_plusminus)
	{
		// Convert day_offset to seconds for comparison
		$dayOffsetSeconds = $dayOffset*86400;
		// Check if in range, which is dependent upon offset_plusminus value
		if ($day_offset_plusminus == '+-') {
			$diff = abs(strtotime($dateToCheck) - strtotime($dateBase));
			return ($diff <= $dayOffsetSeconds);
		} elseif ($day_offset_plusminus == '+') {
			$diff = strtotime($dateToCheck) - strtotime($dateBase);
			return ($diff <= $dayOffsetSeconds && $diff >= 0);
		} elseif ($day_offset_plusminus == '-') {
			$diff = strtotime($dateBase) - strtotime($dateToCheck);
			return ($diff <= $dayOffsetSeconds && $diff >= 0);
		} else {
			return false;
		}
	}

    /**
     * Put all fields with field validation into array with field as key
     * and regex_php as value to validate fields later
     *
     * @param array $mappedFields
     * @return array
     */
    private function validateFields($mappedFields) {
		// Obtain an array of all Validation Types (from db table)
		$valTypes = getValTypes();
        $project = $this->getProject();
		$valFields = [];
		foreach ($mappedFields as $this_field)
		{
			// Get field's validation type
			$thisValType = $project->metadata[$this_field]['element_validation_type'] ?? '';
			// Convert legacy validtion types
			if ($thisValType == "") {
				continue;
			} else {
				$thisValType = convertLegacyValidationType(convertDateValidtionToYMD($thisValType));
			}
			$regExpValidation = $valTypes[$thisValType]['regex_php'] ?? false;
			// Add to array
			if($regExpValidation) $valFields[$this_field] = $regExpValidation;
		}
        return $valFields;
    }

    /**
     * Set up HTML labels for various scenarios like invalid source value, locked source value, etc.
     *
     * @return array
     */
    private function prepareLabels() {
        // Set HTML label for INVALID SOURCE VALUE
		$invalidSrcValueLabel = sprintf(
            '<div style="margin-left:2em;text-indent:-2em;color:red;white-space:normal;line-height:8px;">
                <img src="exclamation.png" style="vertical-align:middle;" />
                    <span style="vertical-align:middle;">%s</span>
            </div>', $this->tt('ws_08')
        );
        // Set HTML label for if the REDCap VALUE = SOURCE VALUE when only ONE source value exists
        $sameSingleSrcValueLabel = sprintf(
            '<div style="margin-left:2em;text-indent:-2em;color:green;white-space:normal;line-height:8px;">
                <img src="tick.png" style="vertical-align:middle;" />
                <span style="vertical-align:middle;">%s</span>
            </div>', $this->tt('ws_14')
        );
        // Set HTML label for if the REDCap field is on a LOCKED form/event
        $lockedSrcValueLabel = sprintf(
            '<div style="margin-left:2em;text-indent:-2em;color:#A86700;white-space:normal;line-height:8px;">
                <img src="lock.png" style="vertical-align:middle;" />
                    <span style="vertical-align:middle;">%s</span>
            </div>', $this->tt('esignature_29')
        );
        // Set HTML label for if the REDCap field value is out of range
        $outOfRangeSrcValueLabel = sprintf(
            '<div style="margin-left:2em;text-indent:-2em;color:#A86700;white-space:normal;line-height:8px;">
                <img src="exclamation_orange.png" style="vertical-align:middle;" />
                    <span style="vertical-align:middle;">%s</span>
            </div>', $this->tt('dataqueries_58')
        );
        return [
            'invalidSrcValue' => $invalidSrcValueLabel,
            'sameSingleSrcValue' => $sameSingleSrcValueLabel,
            'lockedSrcValue' => $lockedSrcValueLabel,
            'outOfRangeSrcValue' => $outOfRangeSrcValueLabel,
        ];
    }

    /**
     * Get external system ID field
     *
     * @param array $mappings
     * @param array $existingData
     * @return array
     */
    private function getExternalSystemIdField($mappings, $existingData) {
        
		$external_id_field = DynamicDataPull::getMappedIdFieldExternal($this->getProjectID());
		$ext_id_fields_keys = array_keys($mappings[$external_id_field]);
		$external_id_rc_event = array_pop($ext_id_fields_keys);
		$ext_id_event_keys = array_keys($mappings[$external_id_field][$external_id_rc_event]);
		$external_id_rc_field = array_pop($ext_id_event_keys);
		$external_id_value = $existingData[$this->getRecord()][$external_id_rc_event][$external_id_rc_field];
        return [$external_id_rc_event, $external_id_rc_field, $external_id_value];
    }

    /**
     * SORT ALL THE DATA ABOUT TO BE DISPLAYED IN THE TABLE
     *
     * @param array $sourceData
     * @return array
     */
    private function sortDataForDisplay($sourceData) {
		// If we have more than one event of data, then sort the events in the correct order
        $project = $this->getProject();
		if (count($sourceData) > 1) {
			// Loop through all existing PROJECT events in their proper order
			$rc_source_data_reordered = array();
			foreach (array_keys($project->eventInfo) as $this_event_id) {
				// Does this event_id exist for this record?
				if (isset($rc_source_data[$this_event_id])) {
					// Add this event's data to reordered data array
					$rc_source_data_reordered[$this_event_id] = $rc_source_data[$this_event_id];
				}
			}
			// Set back as new
			$rc_source_data = $rc_source_data_reordered;
			// unset($rc_source_data_reordered);
		}

        // Now sort all fields according to field order within each event
		foreach ($rc_source_data as $this_event_id=>&$evt_attr) {
			foreach ($evt_attr as $this_repeat_instrument=>&$iattr) {
				foreach ($iattr as $this_instance=>&$rc_array) {
					// Gather all field_order values for fields in this event into an array
					$field_orders = array();
					foreach (array_keys($rc_array) as $this_rc_field) {
						$field_orders[] = $project->metadata[$this_rc_field]['field_order'];
					}
					// Now sort $rc_array according to field_order value
					array_multisort($field_orders, SORT_REGULAR, $rc_array);
				}
			}
		}
        return $rc_source_data;
    }

    private function generateTableRows($rc_source_data) {
        $project = $this->getProject();
        $longitudinal = $this->getLongitudinal();
        $output_html = $this->getOutputHtml();
        $event_id = $this->getEventId();
        $instance = $this->getInstance();
        
        // Create array of REDCap fields/events where the RC value matches a SRC value that was returned
		// Array key = "event_id-RC field name"
		$rcFieldsSameValue = $rcAllFieldsEvents = array();

		// Keep count of total exclusions
		$total_exclusions = 0;

		// Keep count of items where all values are excluded
		$total_items_all_excluded = 0;
        $rtws_temporal_fields_displayed = 0;
		$totalUnadjudicatedValues = 0;
		$rcFieldsAdjudicatedValues = [];
        $last_event_id = 0;
        $rows = '';

		foreach ($rc_source_data as $this_event_id=>$evt_attr2)
		{
			// Get event name
			$this_event_name = $project->eventInfo[$this_event_id]['name_ext'];
						
			// Is repeating event?					
			$isRepeatingEvent = $project->isRepeatingEvent($this_event_id);

			// SECTION HEADER: If longitudinal, display the event name as a table header
			if ($longitudinal && $output_html) {
			// Loop through repeat instruments

				// Loop through RC fields in this event

					// SECTION HEADER: If repeating event, display the event name as a table header

					
					// SECTION HEADER: If a repeating form, display the instance number as a table header

					
					// Loop through RC fields in this event

						// Get the RC field's validation type

						// Get the RC field's form_name

						
						// Is the field a checkbox field type?

						// Determine if this field exists on a locked form/event
						
						// Is repeating form?					

						// Set field name/label string

						// If we're on a data entry form, then set a class for the table row for fields NOT on this form/event

						// Format the RC data value if a multiple choice field to display the option label

							// Display raw value

							// Display label and raw value

						
						// Count the non-adjudicated values

								// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.

									// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.
						
						## NON-TEMPORAL (single row)

							// Get source field name and value

							// If value is blank, then don't prompt user to overwrite an existing value with a blank one

							// Determine if the source value has been excluded

								// Count the entire item as excluded if we're not showing exclusions

							// If RC value and SRC value are the same, then add to array

							// Output the row

								// Set bg color for RC value cell if value exists in RC

								// Set html for Exclude link

								// If field is on a locked form/event, then don't display radio button

								// If RC value is same as SRC value, then don't display radio button

									// Don't show exclude link for existing values

									// Set radio button and hidden reset link for cell
									// If a date[time] field that's not in YMD format, then convert to YMD

										// Not a DMY/MDY date[time] field

								// Format the source data value if a multiple choice field to display the option label

									// Display raw value (non-MC field)

									// FIELD VALIDATION: If field has validation and value fails validation, then give error

									// If data type is valid and has range validation, then check if within range

									// MC field: Display label and raw value

									// Source value is NOT valid, so do not allow import and show error
								
								// If non-temporal values have 

								// Display single row

						## TEMPORAL or CHECKBOXES (possibly multiple rows)

							// Set flag noting that temporal fields will be displayed in the popup

							// Set which sub-row we're on, starting with 0

							// Count how many rows we'll need

							// Set flag for if this item contains at least one non-adjudicated value

							// Set array of src_fields concatenated with timestamp and value (so we can loop more easily through them all

									// Add each value's md_id to the array so that we have all md_id's for this item

									// Check if this md_id is a non-adjudicated value. If so, set flag to TRUE.

									// If timestamp is only a date, then append with 00:00

									// Add to array

							// Sort values by timestamp

							## PRE-SELECTION

								// Set preselection option value if field has more than 1 row

								## "NEAR" PRE-SELECT
								// If preselect option is MIN or MAX, then loop through all values first to determine min or max

									// Get RC timestamp

									// Place all numbers into an array


										// Get source field name, timestamp, and value

										// If the value is excluded for this item, then do not preselect it

										// Add values to array and calculate proximity in time of each value


									// Get closest value based on time proximity

									// Remove array

								## MIN/MAX PRE-SELECT
								// If preselect option is MIN or MAX, then loop through all values first to determine min or max

									// Place all numbers into an array


										// Get source field name, timestamp, and value

										// If the value is excluded for this item, then do not preselect it



										// If value is numerical, then add to number array

									// Get the preselected value (min or max) for this item

									// Remove array

								## SAME DAY PRE-SELECT
								// If not value is pre-selected BUT only one value falls on the same day as the REDCap date, then pre-select it

									//print "\n\n$rc_field:";

										// Get source field name, timestamp, and value

										// If the src and RC dates are the same, then pre-select it

											// Get this date's value and increment counter

									// If only one day matched the RC date, then pre-select that one

							// Keep track of number of exclusions for this item

							// PRE-SELECTION: If item should have LAST value pre-selected, then set that value now so we know which it is when we get to it


									// Since there are no exclusions, just get the very last value


									// Since exclusions exist, first remove excluded values before we can determine the last one

							// Add formatting to src timestamp


								// REDCap Date/Time



								// REDCap Date, so add "(00:00)" to show that it is assumed to use midnight as reference point


							// Set flag to note which value is the first non-excluded value for this item.
							// Default as false, then true for first non-excluded value, then Null for any values after being true.

							// Loop through all subrows/values

								// Get source field name, timestamp, and value
								
								// Checkboxes only: Deal with array of values to show if choice is checked for saved project value

								// If RC value and SRC value are the same, then add to array

									// If this item has at least one value that's not been adjudicated (get imported after other values were adjudicated),
									// then show this item so that user has a chance to see the new value (in case they need to import it and
									// overwrite the existing value).

								// If we have already noted the first non-excluded value for this item, then set this to null.

								// Determine if the source value has been excluded

									// Count the entire item as excluded if we're not showing exclusions (only do this for very last value of this item)

								// If this is the first non-excluded value for this item, set flag to true.


									// Set attributes to pre-select field if only has one value returned

										// If ealiest value should be preselected

										// If latest value should be preselected

										// Reset $preselect_value so that it doesn't preselect 2 values if 2 values are identical

									// If max or min or nearest value should be preselected AND this is that value, then preselect it

										// Reset $preselect_value so that it doesn't preselect 2 values if 2 values are identical

									// If existing value matches a src value already, then do NOT preselect an option
									
									//print "hasNonAdjudicatedValues: $hasNonAdjudicatedValues\n";

									// print "\n$rc_field, $preselect, $preselect_value, $src_value == $preselect_value";

									// If this item contains any non-adjudicated values, then make sure nothing gets preselected

									// Add formatting to src timestamp

									// Set html for Exclude link

									// If field is on a locked form/event, then don't display radio button

									// If RC value is same as SRC value, then don't display radio button

										// Don't show exclude link for existing values

										// If this item contains any non-adjudicated values, then add a hidden input with the value so that
										// any non-adjudicated values will get set as adjudicated upon save (otherwise the item will just keep showing up).

										// Set radio button and hidden reset link for cell
										// If a date[time] field that's not in YMD format, then convert to YMD

											// Not a DMY/MDY date[time] field

									// Format the source data value if a multiple choice field to display the option label

										// Display raw value (non-MC field)

										// FIELD VALIDATION: If field has validation and value fails validation, then give error

										// If data type is valid and has range validation, then check if within range

										// Display label and raw value

										// Source value is NOT valid, so do not allow import and show error

									// Make cell for source date green if occurs on same day as REDCap date

									## Display first row (or display the first non-excluded value/row for this item if hiding excluded values)

									## Display non-first rows

							// Increment $rc_field_loop
			}
			$last_event_id = $this_event_id;
		}
	}

    private function finalizeTable() {
        // TODO: Compile the final HTML table
    }
}
