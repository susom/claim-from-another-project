<?php
namespace Stanford\ClaimFromAnotherProject;

use REDCap;

require_once "emLoggerTrait.php";

class ClaimFromAnotherProject extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

	public function redcap_module_system_enable( $version ) {

	}


	public function redcap_module_project_enable( $version, $project_id ) {

	}


	public function redcap_module_save_configuration( $project_id ) {

	}

	public function logExit($msg) {
        $this->emDebug($msg);
        REDCap::logEvent("Error",$msg);
        return false;
    }

	public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        $claimLogic = $this->getProjectSetting('claim-logic');
        if (empty($claimLogic)) { return $this->logExit("Missing required claim-logic"); }

        // See if the logic is true to claim
        $eval = REDCap::evaluateLogic($claimLogic,$project_id,$record,$event_id,$repeat_instance,$instrument,$instrument);
        if (!$eval) {
            $this->emDebug("claim logic false");
            return false;
        }

        // Get a record from the external project
        $extProject = $this->getProjectSetting('external-project');
        $extLogic = $this->getProjectSetting('external-logic');
        $extUsedField = $this->getProjectSetting('external-used-field');
        $extDateField = $this->getProjectSetting('external-date-field');

        $params = [
            "project_id" => $extProject,
            "filter_logic" => $extLogic,
            "return_format" => 'json'
        ];
        $q = json_decode(REDCap::getData($params), true);

        if (empty($q)) {
            return $this->logExit("No records are available in project $extProject meeting required logic: $extLogic");
        }

        // Take the first record
        $claimRecord = array_shift($q);

        // Set the current record_id in the claim record
        if (empty($extUsedField)) return $this->logExit("Missing required external allocated field");
        $claimRecord[$extUsedField] = $record;

        // Set the claim date if specified
        if (!empty($extDateField)) {
            if (! isset($claimRecord[$extDateField])) return $this->logExit("Specified external date used field is not present in the cliam project: $extDateField");
            $claimRecord[$extDateField] = date("Y-m-d H:i:s");
        }

        // Update Claim Project
        $q = REDCap::saveData($extProject, 'json', json_encode(array($claimRecord)));
        // $this->emDebug($claimRecord, $q);
        if (!empty($q['errors'])) {
            return $this->logExit("Errors during save to claim project $extProject:\n" .
               json_encode($q['errors']) . "\nwith:\n" .  json_encode($claimRecord));
        }


        // Map fields
        $thisRecord = [];
        $maps = $this->getSubSettings('instance');

        // Get ext metadata
        global $Proj;
        $extProj = new \Project($extProject);
        $claimRecordId = $claimRecord[$extProj->table_pk];


        foreach ($maps as $map) {
            $externalField = $map['external-field'];
            $thisField = $map['this-field'];
            $thisEvent = $map['this-event'];
            if(empty($Proj->eventInfo[$thisEvent])) return $this->logExit("Specified event $thisEvent does not exist in this project");
            if(empty($Proj->metadata[$thisField])) return $this->logExit("Specified field $thisField does not exist in this project");
            if(empty($extProj->metadata[$externalField])) return $this->logExit("Specified field $externalField does not exist in external project $extProject");

            if (!isset($thisRecord[$thisEvent])) $thisRecord[$thisEvent] = [];

            if ($extProj->metadata[$externalField]['element_type'] == "file") {
                // We have a file - let's copy it
                $edocId = $claimRecord[$externalField];
                $newEdocId = copyFile($edocId, $project_id);
                $thisRecord[$thisEvent][$thisField] = $newEdocId;
                $this->emDebug("$externalField is a file - copied $edocId to $newEdocId");
            } else {
                // Just copy the other data as-is
                $thisRecord[$thisEvent][$thisField] = $claimRecord[$externalField];
            }
        }

        // Save to current project
        // Had to use record method to include file id...
        //$q = REDCap::saveData('array', array($record => $thisRecord));
        $params = [
            0 => $project_id,
            1 => 'array',
            2 => array($record => $thisRecord),
            3 => 'normal',
            4 => 'YMD',
            5 => 'flat',
            6 => null,
            7 => true,
            8 => true,
            9 => true,
            10 => false,
            11 => true,
            12 => [],
            13 => false,
            14 => false // THIS IS WHAT WE NEED TO OVERRIDE FOR FILES TO BE 'SAVABLE'
        ];
        $q = call_user_func_array(array("\Records", "saveData"), $params);

        // $project_id = $args[0];
			// $dataFormat = (isset($args[1])) ? strToLower($args[1]) : 'array';
			// $data = (isset($args[2])) ? $args[2] : "";
			// $overwriteBehavior = (isset($args[3])) ? strToLower($args[3]) : 'normal';
			// $dateFormat = (isset($args[4])) ? strToUpper($args[4]) : 'YMD';
			// $type = (isset($args[5])) ? strToLower($args[5]) : 'flat';
			// $group_id = (isset($args[6])) ? $args[6] : null;
			// $dataLogging = (isset($args[7])) ? $args[7] : true;
			// $performAutoCalc = (isset($args[8])) ? $args[8] : true;
			// $commitData = (isset($args[9])) ? $args[9] : true;
			// $logAsAutoCalculations = (isset($args[10])) ? $args[10] : false;
			// $skipCalcFields = (isset($args[11])) ? $args[11] : true;
			// $changeReasons = (isset($args[12])) ? $args[12] : array();
			// $returnDataComparisonArray = (isset($args[13])) ? $args[13] : false;
			// $skipFileUploadFields = (isset($args[14])) ? $args[14] : true;
			// $removeLockedFields = (isset($args[15])) ? $args[15] : false;
			// $addingAutoNumberedRecords = (isset($args[16])) ? $args[16] : false;
			// $bypassPromisCheck = (isset($args[17])) ? $args[17] : false;

        // $this->emDebug($thisRecord, $q);

        if (!empty($q['errors'])) {
            return $this->logExit("Errors during save to this project: " . json_encode($q['errors']));
        }

        $this->emDebug("Claimed $claimRecordId from $extProject");
        REDCap::logEvent("Claimed External Record" ,$this->PREFIX . " claimed record " .
            $claimRecordId . " from $extProject");

        // Done
        return true;
	}



}
