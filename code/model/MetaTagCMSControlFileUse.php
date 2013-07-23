<?php


class MetaTagCMSControlFileUse extends DataObject {

	private static $debug = false;

	private static $file_usage_array = array();

	private static $excluded_classes = array();

	//database
	public static $db = array(
		"DataObjectClassName" => "Varchar(255)",
		"DataObjectFieldName" => "Varchar(255)",
		"FileClassName" => "Varchar(255)",
		"IsLiveVersion" => "Boolean",
		"ConnectionType" => "Enum('DB,HAS_ONE,HAS_MANY,MANY_MANY,BELONGS_MANY_MANY')"
	);

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		//start again
		DB::query("DELETE FROM \"MetaTagCMSControlFileUse\";");
		//get all classes
		$allClasses = ClassInfo::subclassesFor("DataObject");
		$siteTreeSubclasses = ClassInfo::subclassesFor("SiteTree");
		// files can have files attached to them so we have commented out the line below
		//$allClassesExceptFiles = array_diff($allClasses, $fileClasses);
		//lets go through class
		foreach($allClasses as $class) {

			if(!in_array($class, $siteTreeSubclasses)) {
				//DB
				$dbArray = null;
				//get the has_one fields
				$newItems = (array) Object::uninherited_static($class, 'db');

				// Validate the data
				//do we need this?
				$dbArray = $newItems; //isset($hasOneArray) ? array_merge($newItems, (array)$hasOneArray) : $newItems;
				//lets inspect
				if($dbArray && count($dbArray)) {
					foreach($dbArray as $fieldName => $fieldType) {
						if($fieldType == "HTMLText") {
							$this->createNewRecord($class, $fieldName, "", "DB");
						}
					}
				}
			}

			//HAS_ONE
			$hasOneArray = null;
			//get the has_one fields
			$newItems = (array) Object::uninherited_static($class, 'has_one');
			// Validate the data
			//do we need this?
			$hasOneArray = $newItems; //isset($hasOneArray) ? array_merge($newItems, (array)$hasOneArray) : $newItems;
			//lets inspect
			if($hasOneArray && count($hasOneArray)) {
				foreach($hasOneArray as $fieldName => $hasOneClass) {
					$this->createNewRecord($class, $fieldName, $hasOneClass, "HAS_ONE");
				}
			}

			//HAS_MANY
			$hasManyArray = null;
			$newItems = (array) Object::uninherited_static($class, 'has_many');
			// Validate the data
			$hasManyArray = $newItems; //isset($hasManyArray) ? array_merge($newItems, (array)$hasManyArray) : $newItems;
			if($hasManyArray && count($hasManyArray)) {
				foreach($hasManyArray as $fieldName => $hasManyClass) {
					$this->createNewRecord($hasManyClass, $fieldName, $class, "HAS_MANY");
				}
			}
			//many many
			$manyManyArray = null;
			$newItems = (array) Object::uninherited_static($class, 'many_many');
			$manyManyArray = $newItems;
			//belongs many many
			$newItems = (array) Object::uninherited_static($class, 'belongs_many_many');
			$manyManyArray = isset($manyManyArray) ? array_merge($newItems, $manyManyArray) : $newItems;
			//do both
			if($manyManyArray && count($manyManyArray)) {
				foreach($manyManyArray as $fieldName => $manyManyClass) {
					$this->createNewRecord($class, $fieldName, $manyManyClass, "MANY_MANY");
					$this->createNewRecord($manyManyClass, $fieldName, $class, "BELONGS_MANY_MANY");
				}
			}
		}
	}

	private function createNewRecord($dataObjectClassName, $dataObjectFieldName, $fileClassName, $connectionType) {
		if(in_array($dataObjectClassName, self::$excluded_classes)  || in_array($fileClassName, self::$excluded_classes)) {
			return;
		}
		//get all file classes
		$fileClasses = ClassInfo::subclassesFor("File");
		if( ! in_array($fileClassName, $fileClasses) && $connectionType != "DB") {
			return;
		}
		if($dataObjectFieldName == "ImageTracking") {
			return;
		}
		if( ! DB::query("
			SELECT COUNT(*)
			FROM \"MetaTagCMSControlFileUse\"
			WHERE \"DataObjectClassName\" = '$dataObjectClassName' AND  \"DataObjectFieldName\" = '$dataObjectFieldName' AND \"FileClassName\" = '$fileClassName'
		")->value()) {
			$obj = new MetaTagCMSControlFileUse();
			$obj->DataObjectClassName = $dataObjectClassName;
			$obj->DataObjectFieldName = $dataObjectFieldName;
			$obj->FileClassName = $fileClassName;
			$obj->ConnectionType = $connectionType;
			$obj->IsLiveVersion = 0;
			$obj->write();
			if(ClassInfo::is_subclass_of($dataObjectClassName, "SiteTree")) {
				$obj = new MetaTagCMSControlFileUse();
				$obj->DataObjectClassName = $dataObjectClassName."_Live";
				$obj->DataObjectFieldName = $dataObjectFieldName;
				$obj->FileClassName = $fileClassName;
				$obj->ConnectionType = $connectionType;
				$obj->IsLiveVersion = 1;
				$obj->write();
			}
			DB::alteration_message("creating new MetaTagCMSControlFileUse: $dataObjectClassName, $dataObjectFieldName, $fileClassName, $connectionType");
		}
	}

	public static function file_usage_count($file, $quickBooleanCheck = false) {
		$fileID = $file->ID;
		if(!isset(self::$file_usage_array[$fileID])) {
			self::$file_usage_array[$fileID] = 0;
			//check for self-referencing (folders)
			$sql = "SELECT COUNT(ID) FROM \"File\" WHERE \"ParentID\" = {$fileID};";
			$result = DB::query($sql, false);
			$childCount = $result->value();
			if($childCount) {
				self::$file_usage_array[$fileID] = $childCount;
				return self::$file_usage_array[$fileID];
			}


			//check for SiteTree_ImageTracking
			$sql = "SELECT COUNT(ID) FROM \"SiteTree_ImageTracking\" WHERE \"FileID\" = {$fileID};";
			$result = DB::query($sql, false);
			$childCount = $result->value();
			if($childCount) {
				self::$file_usage_array[$fileID] = $childCount;
			}
			$checks = DataObject::get("MetaTagCMSControlFileUse");
			if($checks) {
				foreach($checks as $check) {
					$sql = "";
					switch ($check->ConnectionType) {
						case "DB":
							$fileName = $file->Name;
							$sql = "
								SELECT IF(LOCATE('$fileName', \"{$check->DataObjectClassName}\".\"{$check->DataObjectFieldName}\") > 0, 1, 0) AS C
								FROM \"{$check->DataObjectClassName}\"
								ORDER BY C DESC
								LIMIT 1;
							";
							break;
						case "HAS_ONE":
							$sql = "
								SELECT COUNT(\"{$check->DataObjectClassName}\".\"ID\")
								FROM \"{$check->DataObjectClassName}\"
								WHERE \"{$check->DataObjectFieldName}ID\" = {$fileID};
							";
							break;
						case "HAS_MANY":
							$sql = "
								SELECT COUNT(\"{$check->DataObjectClassName}\".\"ID\")
								FROM \"{$check->DataObjectClassName}\"
									INNER JOIN  {$check->FileClassName}
										ON \"{$check->DataObjectClassName}\".\"{$check->FileClassName}ID\" = \"{$check->FileClassName}\".\"ID\"
								WHERE \"{$check->DataObjectClassName}\".\"ID\" = {$fileID};
							";
							break;
						case "MANY_MANY":
							$sql = "
								SELECT COUNT(\"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\".\"ID\")
								FROM \"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\"
								WHERE \"{$check->FileClassName}ID\" = $fileID;
							";
							break;
					}
					$result = DB::query($sql, false);
					$count = $result->value();
					if($count) {
						if($quickBooleanCheck) {
							return true;
						}
						else {
							self::$file_usage_array[$fileID] += $count;
						}
					}
				}
			}
		}
		return self::$file_usage_array[$fileID];
	}

	private static $file_sub_string = array(
		".jpg",
		".png",
		".jpeg",
		".gif",
		".JPG",
		".PNG",
		".JPEG",
		".GIF"
	);

	public static function upgrade_file_names(){
		set_time_limit(60*10); // 10 minutes
		$whereArray = array();
		$whereArray[] = "\"Title\" = \"Name\"";
		foreach(self::$file_sub_string as $subString) {
			$whereArray[] = "LOCATE('$subString', \"Title\") > 0";
		}
		$whereString =  "\"ClassName\" <> 'Folder' AND ( ".implode (" OR ", $whereArray)." )";
		$files = DataObject::get("File", $whereString);
		if($files && $files->count()) {
			foreach($files as $file) {
				self::upgrade_file_name($file);
			}
		}
		else {
			DB::alteration_message("All files have proper names", "created");
		}
	}

	private static function upgrade_file_name(File $file) {
		$fileID = $file->ID;
		if(self::file_usage_count($file, true)) {
			$checks = DataObject::get("MetaTagCMSControlFileUse");
			if($checks && $checks->count()) {
				foreach($checks as $check) {
					if(!$check->IsLiveVersion) {
						switch ($check->ConnectionType) {
							case "HAS_ONE":
								$objName = $check->DataObjectClassName;
								$where = "\"{$check->DataObjectFieldName}ID\" = {$fileID}";
								$innerJoinTable = "";
								$innerJoinJoin = "";
								break;
							case "HAS_MANY":
								$objName = $check->DataObjectClassName;
								$where = "\"{$check->DataObjectClassName}\".\"ID\" = {$fileID}";
								$innerJoinTable = "$check->FileClassName";
								$innerJoinJoin = "\"{$check->DataObjectClassName}\".\"{$check->FileClassName}ID\" = \"{$check->FileClassName}\".\"ID\"";
								break;
							case "BELONGS_MANY_MANY":
								$objName = "";
								$where = "";
								$innerJoinTable = "";
								$innerJoinJoin = "";
								break;
							case "MANY_MANY":
								$objName = $check->DataObjectClassName;
								$where = "\"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\".\"{$check->FileClassName}ID\" = $fileID";
								$innerJoinTable = "{$check->DataObjectClassName}_{$check->DataObjectFieldName}";
								$innerJoinJoin = "\"{$check->DataObjectClassName}\".\"ID\" = \"{$check->DataObjectClassName}_{$check->DataObjectFieldName}\".\"ID\"";
								break;
						}
						$join = "";
						if($innerJoinTable && $innerJoinJoin) {
							$join = " INNER JOIN $innerJoinTable ON $innerJoinJoin ";
						}
						if($objName) {
							$sort = null;
							$limit = 1;
							if(self::$debug) {
								echo "<hr />";
								echo "TYPE: ".$check->ConnectionType."<br />";
								echo "CLASS: ".$objName."<br />";
								echo "WHERE: ".$where."<br />";
								echo "SORT: ".$sort."<br />";
								echo "JOIN: ".$join."<br />";
								echo "LIMIT: ".$limit."<br />";
								echo "<hr />";
							}
							$objects = DataObject::get(
								$objName,
								$where,
								$sort,
								$join,
								$limit
							);
							if($objects && $objects->count()) {
								$obj = $objects->First();
								$oldTitle = $file->Title;
								$newTitle =  $obj->getTitle();
								if((substr($newTitle, 0, 1) != "#") || (intval($newTitle) == $newTitle)) {
									$file->Title = $newTitle;
									$file->write();
									DB::alteration_message("Updating ".$file->Name." title from ".$oldTitle." to ".$newTitle, "created");
								}
								else {
									DB::alteration_message("There is no real title for ".$obj->ClassName.": ".$newTitle);
								}
							}
							else {
								echo ".";
							}
						}
					}
					else {
						DB::alteration_message("Skipping Live version <i>".$check->DataObjectClassName."</i>");
					}
				}
			}
			else {
				DB::alteration_message("There are no checks", "deleted");
			}
		}
		else {
			DB::alteration_message("File <i>".$file->Title."</i> is not being used");
		}
		return self::$file_usage_array[$fileID];
	}

}



