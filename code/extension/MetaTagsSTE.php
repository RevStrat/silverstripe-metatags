<?php
/**
*
* @Author Nicolaas Francken
* adding meta tag functionality to the SiteTree Model Classes.
*
*
*
**/

class MetaTagsSTE extends SiteTreeExtension {

	/**
	 * length of auto-generated meta descriptions in header
	 * @var Int
	 */
	private static $meta_desc_length = 24;

	/**
	 * standard SS method
	 * @var Array
	 **/
	private static $db = array(
		'AutomateMetatags' => 'Boolean'
	);

	/**
	 * standard SS method
	 * @var Array
	 **/
	private static $defaults = array(
		'AutomateMetatags' => true
	);

	/**
	 * standard SS method
	 * @var Array
	 **/
	public function updateCMSFields(FieldList $fields) {
		$automatedFields =  $this->updatedFieldsArray();
		if(count($automatedFields)) {
			$updatedFieldString = " ("
				._t("MetaManager.UPDATED_EXTERNALLY", "the following fields will be automatically updated")
				.": <i>"
				.implode("</i>, <i>", $automatedFields)
				."</i>).";
			$fields->addFieldsToTab('Root.Main.Metadata',
				array(
					$allowField2 = new LiteralField('AutomateMetatags_explanation',"<p><em>$updatedFieldString</em></p>"),
					$allowField1 = new CheckboxField('AutomateMetatags', _t('MetaManager.UPDATEMETA','Allow Meta (Search Engine) Fields to be updated automatically? '))
				)
			);
			if($this->owner->AutomateMetatags) {
				foreach($automatedFields as $fieldName => $fieldTitle) {
					$oldField = $fields->dataFieldByName($fieldName);
					if($oldField) {
						$newField = $oldField->performReadonlyTransformation();
						//$newField->setTitle($newField->Title());
						$newField->setRightTitle(_t("MetaTags.AUTOMATICALLY_UPDATED", "Automatically updated when you save this page (see metadata settings)."));
						$fields->replaceField($fieldName, $newField);
					}
				}
			}
		}
		$fields->removeByName('ExtraMeta');
		$linkToManager = Config::inst()->get("MetaTagCMSControlPages", "url_segment") . '/';
		$fields->addFieldToTab('Root.Main.Metadata', new LiteralField("LinkToManagerHeader", "<p>Open the Meta Tag Manager to <a href=\"$linkToManager\" target=\"_blank\">Review and Edit</a> the Meta Data for all pages on this site. Also make sure to review the general <a href=\"/admin/show/root/\">settings for Search Engines</a>.</p>"));
		if($this->owner->URLSegment == RootURLController::get_default_homepage_link()) {
			$newField = $fields->dataFieldByName('URLSegment');
			$newField->setRightTitle("Careful: changing the URL from 'home' to anything else means that this page will no longer be the home page");
			$fields->replaceField('URLSegment', $newField);
		}
		return $fields;
	}

	/**
	 * Update Metadata fields function
	 */
	public function onBeforeWrite() {
		$siteConfig = SiteConfig::current_site_config();
		// if UpdateMeta checkbox is checked, update metadata based on content and title
		// we only update this from the CMS to limit slow-downs in programatic updates
		if(isset($_REQUEST['AutomateMetatags']) && $_REQUEST['AutomateMetatags']){
			if($siteConfig->UpdateMenuTitle){
				// Empty MenuTitle
				$this->owner->MenuTitle = '';
				// Check for Content, to prevent errors
				if($this->owner->Title){
					$this->owner->MenuTitle = $this->cleanInput($this->owner->Title, 0);
				}
			}
			if($siteConfig->UpdateMetaDescription && Config::inst()->get("MetaTagsSTE", "meta_desc_length")){
				// Empty MetaDescription
				// Check for Content, to prevent errors

				if($this->owner->Content){
					//added a few hacks here
					$contentField = DBField::create_field("Text", $this->owner->Content, "MetaDescription");
					$flex = ceil(MetaTagsSTE::$meta_desc_length / 2) + 5;
					$summary = $contentField->Summary(MetaTagsSTE::$meta_desc_length, $flex);
					$summary = str_replace("<br />", " ", $summary);
					$this->owner->MetaDescription = strip_tags($summary);
				}
			}
		}

 	}

	private function updatedFieldsArray(){
		$config = SiteConfig::current_site_config();
		$fields = array();
		if($config->UpdateMenuTitle) {
			$fields['MenuTitle'] = _t('SiteTree.MENUTITLE', 'Menu Title ');
		}
		if($config->UpdateMetaDescription) {
			$fields['MetaDescription'] = _t('SiteTree.METADESCRIPTION', 'Description ');
		}
		return $fields;
	}

	function populateDefaults() {
		$this->owner->AutomateMetatags = true;
	}

	private function cleanInput($string, $numberOfWords = 0) {
		$newString = str_replace("&nbsp;", "", $string);
		$newString = str_replace("&amp;", " and ", $newString);
		$newString = str_replace("&ndash;", " - ", $newString);
		$newString = strip_tags(str_replace('<', ' <', $newString));
		if($numberOfWords) {
			$textFieldObject = Text::create("Text", $newString);
			if($textFieldObject) {
				$newString = strip_tags($textFieldObject->LimitWordCountXML($numberOfWords));
			}
		}
		$newString = html_entity_decode($newString, ENT_QUOTES);
		$newString = html_entity_decode($newString, ENT_QUOTES);
		return $newString;
	}

	function requireDefaultRecords(){
		$folder = SSViewer::current_theme();
		if($folder) {
			$destinationFile = Director::baseFolder()."/themes/".$folder."/css/editor.css";
			$baseFile = Director::baseFolder(). "/".SS_METATAGS_DIR."/css/editor.css";
			if(!file_exists($destinationFile) && file_exists($baseFile)) {
				copy($baseFile, $destinationFile);
			}
		}
		$folder = SSViewer::current_theme();
		if($folder) {
			$destinationFile = Director::baseFolder()."/themes/".$folder."/css/reset.css";
			$baseFile = Director::baseFolder(). "/".SS_METATAGS_DIR."/css/reset.css";
			if(!file_exists($destinationFile) && file_exists($baseFile)) {
				copy($baseFile, $destinationFile);
			}
		}
	}
}
