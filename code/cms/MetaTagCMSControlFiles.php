<?php

class MetaTagCMSControlFiles extends Controller {

	private static $recycling_bin_name = 'ZzzeRecyclingBin';
		static function set_recycling_bin_name($s){self::$recycling_bin_name = $s;}
		static function get_recycling_bin_name(){return self::$recycling_bin_name;}

	private static $url_segment = 'metatagmanagementfiles';
		static function set_url_segment($s){self::$url_segment = $s;}
		static function get_url_segment(){return self::$url_segment;}

	private static $records_per_page = 10;
		static function set_records_per_page($i){self::$records_per_page = $i;}
		static function get_records_per_page(){return self::$records_per_page;}

	protected $ParentID = 0;

	protected $updatableFields = array(
		"Title",
		"Content"
	);

	/**
	 * First table is main table - e.g. $this->tableArray[0] should work
	 *
	 **/
	protected $tableArray = array("File");

	function init(){
		parent::init();
		$member = Member::currentMember();
			// Default security check for LeftAndMain sub-class permissions
		if(!Permission::checkMember($member, "CMS_ACCESS_LeftAndMain")) {
			return Security::permissionFailure($this);
		}
		Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::block(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::javascript(Director::protocol()."ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js");
		Requirements::javascript("sapphire/thirdparty/jquery-form/jquery.form.js");
		Requirements::javascript("metatags/javascript/MetaTagCMSControl.js");
		Requirements::themedCSS("MetaTagCMSControl");
		if($parentID = intval($this->request->getVar("childrenof"))) {
			$this->ParentID = $parentID;
		}
	}

	/***************************************************
	 * ACTIONS                                         *
	 *                                                 *
	 ***************************************************/

	function index() {
		return $this->renderWith("MetaTagCMSControlFiles");
	}

	function cleanupfolders(){
		return $this->redirect(MetaTagCMSFixImageLocations::my_link());
	}

	function childrenof($request) {
		$id = intval($request->param("ID"));
		if($id) {
			$this->ParentID = $id;
		}
		return array();
	}

	function lowercase($request) {
		if($fieldName = $request->param("ID")) {
			if(in_array($fieldName, $this->updatableFields)) {
				foreach($this->tableArray as $table) {
					$items = DataObject::get($table, "BINARY \"$fieldName\" <> LOWER(\"$fieldName\")", "\"LastEdited\" ASC", null, 100);
					if($items && $items->count()) {
						$item->$fieldName = strtolower($item->$fieldName);
						$item->write();
					}
				}
				Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.UPDATEDTOLOWERCASE", $items->count()." records updated to <i>lower case</i>, please repeat if you have more than 100 records."));
				return $this->returnAjaxOrRedirectBack();
			}
		}
		Session::set("MetaTagCMSControlMessage", _t("MetaTagCMSControl.NOTUPDATEDTOLOWERCASE", "Records could not be updated <i>to lower case</i>."));
		return $this->returnAjaxOrRedirectBack();
	}

	function titlecase($request){
		if($fieldName = $request->param("ID")) {
			if(in_array($fieldName, $this->updatableFields)) {
				foreach($this->tableArray as $table) {
					$items = DataObject::get($table, null, "\"LastEdited\" ASC", null, 100);
					if($items && $items->count()) {
						$newValue = Convert::raw2sql($this->convert2TitleCase($item->$fieldName));
						$item->$fieldName = $newValue;
						$item->write();
					}
				}
				Session::set("MetaTagCMSControlMessage", _t("MetaTagCMSControl.UPDATEDTOTITLECASE", $items->count()." records updated to <i>title case</i>, please repeat if you have more than 100 records."));
				return $this->returnAjaxOrRedirectBack();
			}
		}
		Session::set("MetaTagCMSControlMessage", _t("MetaTagCMSControl.NOTUPDATEDTOTITLECASE", "Records could not be updated to <i>title case</i>."));
		return $this->returnAjaxOrRedirectBack();
	}


	function upgradefilenames($request) {
		if($folderID = intval($request->param("ID"))) {
			$verbose = Director::is_ajax() ? false : true;
			if($count = MetaTagCMSControlFileUse::upgrade_file_names($folderID, $verbose)) {
				Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.NAMESUPDATED", "Updated $count file names."));
				return $this->returnAjaxOrRedirectBack($verbose);
			}
		}
		Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.NAMESNOTUPDATED", "ERROR: Did not update any file names"));
		return $this->returnAjaxOrRedirectBack();
	}

	function recyclefolder($request) {
		if($folderID = intval($request->param("ID"))) {
			$verbose = Director::is_ajax() ? false : true;
			if($count = MetaTagCMSControlFileUse::recycle_folder($folderID, $verbose)) {
				Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.RECYCLED_FILES", "Recycled unused files in this folder."));
				return $this->returnAjaxOrRedirectBack($verbose);
			}
		}
		Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.DID_NOT_RECYCLE_FILES", "ERROR: Could not recycle all files"));
		return $this->returnAjaxOrRedirectBack();
	}


	function copyfromtitle($request){
		if($fieldName = $request->param("ID")) {
			if(in_array($fieldName, $this->updatableFields)) {
				foreach($this->tableArray as $table) {
					$items = DataObject::get($table, "\"$fieldName\ <> \"Title\"", "\"LastEdited\" ASC", null, 100);
					if($items && $items->count()) {
						$item->$fieldName = $item->Title;
						$item->write();
					}
				}
				Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.COPIEDFROMTITLE", $items->count()." records <i>copied from title</i>, please repeat if you have more than 100 records."));
				return $this->returnAjaxOrRedirectBack();
			}
		}
		Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.NOTCOPIEDFROMTITLE", "Copy not successful"));
		return $this->returnAjaxOrRedirectBack();
	}

	function update(){
		if(isset($_GET["fieldName"])) {
			$fieldNameString = $_GET["fieldName"];
			$fieldNameArray = explode("_", $fieldNameString);
			$fieldName = $fieldNameArray[0];
			if(in_array($fieldName, $this->updatableFields)) {
				if(!isset($_GET[$fieldNameString])) {
					$value = 0;
				}
				else {
					$value = Convert::raw2sql($_GET[$fieldNameString]);
				}
				$recordID = intval($fieldNameArray[1]);
				foreach($this->tableArray as $table) {
					$record = DataObject::get_by_id($table, $recordID);
					$extension = '';
					if(!($record instanceOf Folder)) {
						$extension = ".".$record->getExtension();
					}
					if($record) {
						$record->$fieldName = $value;
						//echo $fieldName.$record->ID.$value;
						if($fieldName == "Title") {
							$record->setName($value.$extension);
						}
						$record->write();
					}
					else {
						return  _t("MetaTagCMSControl.COULDNOTFINDRECORD", "Could not find record");
					}
				}
				return  _t("MetaTagCMSControl.UPDATE", "Updated <i>".$record->Title."</i>");
			}
			else {
				return  _t("MetaTagCMSControl.NOTALLOWEDTOUPDATEFIELD", "You are not allowed to update this field.");
			}
		}
		return _t("MetaTagCMSControl.NOTUPDATE", "Record could not be updated.");
	}

	function recycle($request) {
		$id = intval($request->param("ID"));
		if($id) {
			$file = DataObject::get_by_id("File", $id);
			if(MetaTagCMSControlFileUse_RecyclingRecord::recycle($file)) {
				Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.FILERECYCLED", "File &quot;".$file->Title."&quot; has been recycled."));
				return $this->returnAjaxOrRedirectBack();
			}
		}
		Session::set("MetaTagCMSControlMessage",  _t("MetaTagCMSControl.FILENOTRECYCLED", "ERROR: File &quot;".$file->Title."&quot; could NOT be recycled."));
		return $this->returnAjaxOrRedirectBack();
	}

	protected function returnAjaxOrRedirectBack($verbose = false){
		if(Director::is_ajax()) {
			return $this->renderWith("MetaTagCMSControlFilesAjax");
		}
		else {
			if(!$verbose) {
				Director::redirect($this->Link());
			}
			return array();
		}
	}

	/***************************************************
	 * CONTROLS                                        *
	 *                                                 *
	 ***************************************************/


	function MyRecords() {
		//Filesystem::sync($this->ParentID);
		$files = DataObject::get($this->tableArray[0], "\"ParentID\" = ".$this->ParentID, "IF(\"ClassName\" = 'Folder', 0, 1) ASC, \"Name\" ASC ", '', $this->myRecordsLimit());
		$dos = null;
		if($files) {
			foreach($files as $file) {
				$file->ChildrenLink = '';
				if(!$file->canView() ) {
					$file->Error = "YOU DO NOT HAVE PERMISSION TO VIEW THIS FILE.";
				}
				if(!file_exists($file->getFullPath())) {
					$file->Error = "FILE CAN NOT BE FOUND.";
				}
				if(DataObject::get_one($this->tableArray[0], "ParentID = ".$file->ID)) {
					$file->ChildrenLink = $this->createLevelLink($file->ID);
				}
				$file->UsageCount = MetaTagCMSControlFileUse::file_usage_count($file, false, $saveListOfPlaces = true);
				if($file instanceOf Folder) {
					$file->Type == "Folder";
					$file->Icon == "metatags/images/Folder.png";
				}
				elseif($file->UsageCount) {
					$file->ListOfPlaces = MetaTagCMSControlFileUse::retrieve_list_of_places($file->ID);
				}
				if(!$file->ListOfPlaces) {
					unset($file->ListOfPlaces);
				}
				$file->GoOneUpLink = $this->GoOneUpLink();
				$file->RecycleLink = $this->makeRecycleLink($file->ID);
				$dos[$file->ID] = new DataObjectSet();
				$segmentArray = array();
				$item = $file;
				$segmentArray[] = array("URLSegment" => $item->Name, "ID" => $item->ID, "ClassName" => $item->ClassName, "Title" => $item->Title, "Link" => "/".$item->Filename);
				$x = 0;
				while($item && $item->ParentID && $x < 10) {
					$x++;
					$item = DataObject::get_by_id($this->tableArray[0], $item->ParentID);
					if($item) {
						$segmentArray[] = array("URLSegment" => $item->Name, "ID" => $item->ID, "ClassName" => $item->ClassName, "Title" => $item->Title, "Link" => $this->createLevelLink(intval($item->ParentID)-0));
					}
				}
				$segmentArray = array_reverse($segmentArray);
				foreach($segmentArray as $segment) {
					$dos[$file->ID]->push(new ArrayData($segment));
				}
				$file->ParentSegments = $dos[$file->ID] ;
				$dos = null;
			}
		}
		return $files;
	}


	function FormAction() {
		return Director::absoluteBaseURL().$this->Link("update");
	}

	function Link($action = '') {
		if($action) {
			$action .= "/";
		}
		return  "/" . self::$url_segment . "/" . $action;
	}

	function GoOneUpLink() {
		if( $this->ParentID ) {
			$oneUpPage = DataObject::get_by_id($this->tableArray[0], $this->ParentID);
			if($oneUpPage) {
				return $this->createLevelLink($oneUpPage->ParentID);
			}
		}
	}

	protected function makeRecycleLink($id) {
		if(!isset($_GET["start"])) {
			$start = 0;
		}
		else {
			$start = intval($_GET["start"]);
		}
		return $this->Link("recycle").$id."/?childrenof=".$this->ParentID."&amp;start=".$start;
	}

	function Message() {
		$msg = Session::get("MetaTagCMSControlMessage");
		Session::clear("MetaTagCMSControlMessage", "");
		return $msg;
	}


	/***************************************************
	 * PROTECTED                                       *
	 *                                                 *
	 ***************************************************/

	protected function createLevelLink ($id) {
		return $this->Link("childrenof") .  $id . "/";
	}

	protected function mySiteConfig() {
		return SiteConfig::current_site_config();
	}

	protected function myRecordsLimit(){
		$start = 0;
		if(isset($_GET["start"])) {
			$start = intval($_GET["start"]);
		}
		return "$start, ".self::get_records_per_page();
	}


}


