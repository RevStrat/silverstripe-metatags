<?php

/**
 * adds meta tag functionality to the Page_Controller
 *
 *
 *
 */
class MetaTagsContentControllerEXT extends Extension
{

    /**
     * the twitter handle used by the site
     * do not include @ sign.
     * @var string
     */
    private static $favicon_sizes = array(
        "16",
        "32",
        //"57",
        //"72",
        //"76",
        //"96",
        //"114",
        //"120",
        "128",
        "144",
        //"152",
        //"180",
        //"192",
        "310"
    );

    /**
     * the twitter handle used by the site
     * do not include @ sign.
     * @var string
     */
    private static $twitter_handle = "";

    /**
     * dont show users basic instructions for SEO
     * @var bool
     */
    private static $no_search_engine_instructions = false;

    /**
     * allow user to enter a separate meta title?
     * @var bool
     */
    private static $use_separate_metatitle = false;

    /**
     * stop users from automating the menu title
     * @var bool
     */
    private static $no_automated_menu_title = false;

    /**
     * stop users from automating meta descriptions
     * @var bool
     */
    private static $no_automated_meta_description = false;

    /**
     * stop users from automating custom meta settings (custom tags, country, date, etc...)
     * @var bool
     */
    private static $no_additional_meta_settings = false;

    /**
     * length of auto-generated meta descriptions in header
     * @var Int
     */
    private static $meta_desc_length = 24;

    /**
     * what should be included on every page?
     * @var Array
     */
    private static $default_css = array(
        'reset' =>  null,
        'typography' => null,
        'layout' => null,
        'form' => null,
        'menu' => null,
        'individualPages' => null,
        'responsive' => null,
        'print' => 'print'
    );

    /**
     * specify location for jquery CDN location
     * @var Array
     */
    private static $jquery_cdn_location = "";

    /**
     * what should be included on every page?
     * @var Array
     */
    private static $default_js = array(
        "framework/thirdparty/jquery/jquery.js"
    );

    /**
     * what should be included on every page?
     * @var String
     */
    private static $alternative_jquery_location = "";

    /**
     * @var String
     * folder where the combined css / js files will be stored
     * if they are combined.
     */
    private static $folder_for_combined_files = 'assets';

    /**
     * @var String
     * viewport setting
     */
    private static $viewport_setting = 'width=device-width,initial-scale=1';


    /**
     * map Page types and methods for use in the
     * facebook open graph.
     * e.g.MyProductPage: ProductImage
     *
     * @var Array
     **/
    private static $og_image_method_map = array();

    /**
     * google fonts to be used
     * @var Array
     **/
    private static $google_font_collection = array();

    /**
     * combine css files into one?
     * @var Boolean
     */
    private static $combine_css_files_into_one = false;

    /**
     * combine js files into one?
     * @var Boolean
     */
    private static $combine_js_files_into_one = false;

    /**
     * add all the basic js and css files - call from Page::init()
     * @var Array
     */
    private static $_metatags_building_completed = array();

    /**
     * add Jquery
     *
     */
    public function onBeforeInit()
    {
        $jQueryCDNLocation = Config::inst()->get("MetaTagsContentControllerEXT", "jquery_cdn_location");
        Requirements::block("framework/thirdparty/jquery/jquery.js");
        if ($jQueryCDNLocation) {
            Requirements::block("framework/thirdparty/jquery/jquery.js");
            Requirements::javascript($jQueryCDNLocation);
        } else {
            Requirements::javascript("framework/thirdparty/jquery/jquery.js");
        }
    }

    /**
     * Puts together all the requirements.
     *
     * @param array $additionalJS (foo.js, bar.js)
     * @param array $additionalCSS (name => media type)
     * @param Boolean $force - run it again
     *
     */
    public function addBasicMetatagRequirements($additionalJS = array(), $additionalCSS = array(), $force = false)
    {
        if (!isset(self::$_metatags_building_completed[$this->owner->dataRecord->ID]) || $force) {
            $combineJS = Config::inst()->get("MetaTagsContentControllerEXT", "combine_js_files_into_one");
            $combineCSS = Config::inst()->get("MetaTagsContentControllerEXT", "combine_css_files_into_one");
            if ($combineJS || $combineCSS) {
                $folderForCombinedFiles = Config::inst()->get("MetaTagsContentControllerEXT", "folder_for_combined_files");
                $folderForCombinedFilesWithBase = Director::baseFolder()."/".$folderForCombinedFiles;
                if ($combineJS) {
                    $jsFile = $folderForCombinedFiles."/MetaTagAutomation.js";
                }
                if ($combineCSS) {
                    $cssFile = $folderForCombinedFiles."/MetaTagAutomation.css";
                }
            }
            $jQueryCDNLocation = Config::inst()->get("MetaTagsContentControllerEXT", "jquery_cdn_location");
            $cssArray = Config::inst()->get("MetaTagsContentControllerEXT", "default_css");
            $jsArray = Config::inst()->get("MetaTagsContentControllerEXT", "default_js");
            // if(Director::isLive() && 1 == 2) {
            //     foreach($cssArray as $tempKey => $tempValue) {
            //         $newArray = array();
            //         if(strpos('.css', $tempKey)) {
            //             $newKey = str_replace('.css', '.min.css', $tempKey);
            //         } else {
            //             $newKey = $tempKey.'.min';
            //         }
            //         $newArray[$newKey] = $tempValue;
            //     }
            //     $cssArray = $newArray;
            //     foreach($jsArray as $tempKey => $tempValue) {
            //         $jsArray[$tempKey] = str_replace('.js', '.min.js', $tempValue);
            //     }
            // }
            $jsArray = array_unique(array_merge($jsArray, $additionalJS));

            //javascript
            if ($combineJS && file_exists($folderForCombinedFilesWithBase.$jsFile)) {
                Requirements::javascript($jsFile);
            } else {
                foreach ($jsArray as $key => $js) {
                    if (strpos($js, "framework/thirdparty/jquery/jquery.js") !== false) {
                        //remove, as already included
                        unset($jsArray[$key]);
                    } else {
                        if (!isset($alreadyDone[$js])) {
                            Requirements::javascript($js);
                            $alreadyDone[$js] = 1;
                        }
                    }
                }
            }
            //put jQuery back in, if needed.
            if (!$jQueryCDNLocation) {
                array_unshift($jsArray, "framework/thirdparty/jquery/jquery.js");
            }
            if ($combineJS) {
                Requirements::combine_files($jsFile, $jsArray);
            }

            //css
            if ($combineCSS && file_exists($folderForCombinedFilesWithBase.$cssFile)) {
                Requirements::css($cssFile);
            } else {
                $themeFolder = SSViewer::get_theme_folder();
                $cssArrayLocationOnly = array();
                $expendadCSSArray = array();
                foreach ($cssArray  as $name => $media) {
                    if (strpos($name, '.css')) {
                        $expendadCSSArray[] = array("media" => $media, "location" => $name);
                    } else {
                        $expendadCSSArray[] = array("media" => $media, "location" => $themeFolder.'/css/'.$name.'.css');
                    }
                }
                $expendadCSSArray = array_merge($expendadCSSArray, $additionalCSS);
                foreach ($expendadCSSArray as $cssArraySub) {
                    Requirements::css($cssArraySub["location"], $cssArraySub["media"]);
                    $cssArrayLocationOnly[] = $cssArraySub["location"];
                }
                if ($combineCSS) {
                    Requirements::combine_files($cssFile, $cssArrayLocationOnly);
                }
            }

            //google font
            $googleFontArray = Config::inst()->get('MetaTagsContentControllerEXT', 'google_font_collection');
            if (is_array($googleFontArray) && count($googleFontArray)) {
                $protocol = Director::protocol();
                $fonts = implode('|', $googleFontArray);
                $fonts = str_replace(' ', '+', $fonts);
                Requirements::insertHeadTags('
                <link href="' . $protocol . 'fonts.googleapis.com/css?family=' . $fonts . '" rel="stylesheet" type="text/css" />');
            }

            //ie header...
            if (isset($_SERVER['HTTP_USER_AGENT']) &&  (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
                header('X-UA-Compatible: IE=edge,chrome=1');
            }
            self::$_metatags_building_completed[$this->owner->dataRecord->ID] = true;
        }
    }


    /**
     * this function will add more metatags to your template -
     * make sure to add it at the start of your metatags
     * We leave the / closing tags here, but they are not needed
     * yet not invalid in html5
     * @param Boolean $includeTitle - include the title tag
     * @param Boolean $addExtraSearchEngineData - add extra tags describing the page
     * @return String (HTML)
     */
    public function ExtendedMetatags($includeTitle = true, $addExtraSearchEngineData = true)
    {
        $base = Director::absoluteBaseURL();
        if (!isset($_SERVER["REQUEST_URI"])) {
            $_SERVER["REQUEST_URI"] = '';
        }
        $siteConfig = SiteConfig::current_site_config();
        $this->addBasicMetatagRequirements();
        $cacheKey =
                'ExtendedMetaTags_'
                .abs($this->owner->ID).'_'
                .strtotime($this->owner->LastEdited).'_'
                .strtotime($siteConfig->LastEdited).'_'
                .preg_replace(
                    "/[^a-z0-9]/i",
                    "_",
                    $base.'_'.$_SERVER["REQUEST_URI"]
                );
        if ($this->owner->hasMethod('metatagsCacheKey')) {
            $add = $this->owner->metatagsCacheKey();
            if ($add === false) {
                $cacheKey = null;
            }
            $cacheKey .= $add;
        }
        $cache = SS_Cache::factory('metatags');
        $tags = $cache->load($cacheKey);
        if ($tags && $cacheKey) {
            //do nothing
        } else {
            $themeFolder = SSViewer::get_theme_folder() . '/';
            $tags = "";
            $page = $this->owner;
            $siteConfig = SiteConfig::current_site_config();
            $title = $this->MetaTagsMetaTitle();
            //base tag
            $base = Director::absoluteBaseURL();
            $tags .= "<base href=\"$base\" />";
            if ($this->owner->hasMethod('CanonicalLink')) {
                $canonicalLink = $this->owner->CanonicalLink();
                if ($canonicalLink) {
                    $tags .= "
            <link rel=\"canonical\" href=\"".$canonicalLink."\" />";
                }
            }
            //these go first - for some reason ...
            if ($addExtraSearchEngineData) {
                $tags .= '
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <meta name="viewport" content="'.Config::inst()->get("MetaTagsContentControllerEXT", "viewport_setting").'" />';
            }

            if ($page->MetaDescription) {
                $description = '
            <meta name="description" content="'.Convert::raw2att($page->MetaDescription).'" />';
                $noopd = '';
            } else {
                $noopd = "NOODP, ";
                $description = '';
            }
            $lastEdited = new SS_Datetime();
            $lastEdited->value = $page->LastEdited;

            //use base url rather than / so that sites that aren't a run from the root directory can have a favicon
            $faviconBase = $base;
            $faviconFileBase = "";
            if ($includeTitle) {
                $titleTag = '
            <title>'.trim(Convert::raw2att($siteConfig->PrependToMetaTitle.' '.$title.' '.$siteConfig->AppendToMetaTitle)).'</title>';
            } else {
                $titleTag = '';
            }
            $tags .= '
            <meta charset="utf-8" />'.
                $titleTag;
            $hasBaseFolderFavicon = false;
            if (file_exists(Director::baseFolder().'/'.$faviconFileBase.'favicon.ico')) {
                $hasBaseFolderFavicon = true;
                //ie only...
                $tags .= '
            <link rel="SHORTCUT ICON" href="'.$faviconBase.'favicon.ico" />';
            } else {
                if (file_exists(Director::baseFolder().'favicon.ico')) {
                    $hasBaseFolderFavicon = true;
                    //ie only...
                    $tags .= '
                <link rel="SHORTCUT ICON" href="'.$faviconBase.'favicon.ico" />';
                }
            }
            if (!$page->ExtraMeta && $siteConfig->ExtraMeta) {
                $page->ExtraMeta = $siteConfig->ExtraMeta;
            }
            if (!$siteConfig->MetaDataCopyright) {
                $siteConfig->MetaDataCopyright = $siteConfig->Title;
            }
            if ($addExtraSearchEngineData) {
                $tags .= '
            <meta name="robots" content="'.$noopd.'all, index, follow" />
            <meta name="googlebot" content="'.$noopd.'all, index, follow" />
            <meta name="rights" content="'.Convert::raw2att($siteConfig->MetaDataCopyright).'" />
            <meta name="created" content="'.$lastEdited->Format("Ymd").'" />
            <!--[if lt IE 9]>
                <script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>
            <![endif]-->
                '.$page->ExtraMeta.
                $description;
            }
            $tags .= $this->OGTags();
            $tags .= $this->TwitterTags();
            $tags .= $this->iconTags($faviconBase, $hasBaseFolderFavicon);
            if ($cacheKey) {
                $cache->save($tags, $cacheKey);
            }
        }
        return $tags;
    }

    /**
     * open graph protocol
     * @see: http://ogp.me/
     * @return String (HTML)
     */
    protected function OGTags()
    {
        $title = $this->MetaTagsMetaTitle();
        $array = array(
            "title" => Convert::raw2att($title),
            "type" => "website",
            "url" => Convert::raw2att($this->owner->AbsoluteLink()),
            "site_name" => Convert::raw2att($this->owner->SiteConfig()->Title),
            "description" => Convert::raw2att($this->owner->MetaDescription)
        );
        $html = "";
        if ($shareImage = $this->shareImage()) {
            $array["image"] = Convert::raw2att($shareImage->getAbsoluteURL());
        }
        foreach ($array as $key => $value) {
            $html .= "
            <meta property=\"og:$key\" content=\"$value\" />";
        }
        return $html;
    }

    /**
     * twitter version of open graph protocol
     * twitter is only added if you set a handle in the configs:
     *
     *     MetaTagsContentControllerEXT:
     *       twitter_handle: "relevant_twitter_handle"
     *
     * @return String (HTML)
     */
    protected function TwitterTags()
    {
        if ($handle = Config::inst()->get("MetaTagsContentControllerEXT", "twitter_handle")) {
            $html = "";
            $array = array(
                "title" => Convert::raw2att($this->owner->Title),
                "description" => Convert::raw2att($this->owner->MetaDescription),
                "url" => Convert::raw2att($this->owner->AbsoluteLink()),
                "site" => "@".$handle
            );
            if ($shareImage = $this->shareImage()) {
                $array["card"] = Convert::raw2att("summary_large_image");
                $array["image"] = Convert::raw2att($shareImage->getAbsoluteURL());
            } else {
                $array["card"] = Convert::raw2att("summary");
            }
            foreach ($array as $key => $value) {
                $html .= "
                <meta name=\"twitter:$key\" content=\"$value\" />";
            }
            return $html;
        }
    }

    private $_shareImage = null;
    /**
     *
     * @return image | null
     */
    private function shareImage()
    {
        if ($this->_shareImage === null) {
            $this->_shareImage = false;
            if ($this->owner->ShareOnFacebookImageID) {
                $this->_shareImage = $this->owner->ShareOnFacebookImage();
            } else {
                $og_image_method_map = Config::inst()->get("MetaTagsContentControllerEXT", "og_image_method_map");
                if (is_array($og_image_method_map)) {
                    foreach ($og_image_method_map as $className => $method) {
                        if ($this->owner->dataRecord instanceof $className) {
                            $variable = $method."ID";
                            if (!empty($this->owner->dataRecord->$variable)) {
                                if ($this->owner->hasMethod($method)) {
                                    $this->_shareImage = $this->owner->$method();
                                }
                            }
                        }
                    }
                }
            }
            //clean up any stuff...
            if ($this->_shareImage && $this->_shareImage->exists()) {
            } else {
                $this->_shareImage = false;
            }
        }
        return $this->_shareImage;
    }

    protected function iconTags($baseURL = "", $hasBaseFolderFavicon = false)
    {
        $favicon = null;
        if (!$baseURL) {
            $baseURL = Director::absoluteBaseURL();
        }
        $cacheKey =
            'iconTags_'
            .preg_replace(
                "/[^a-z0-9]/i",
                '_',
                $baseURL
            );
        $baseURL = rtrim($baseURL, "/");
        $cache = SS_Cache::factory('metatags');
        $html = $cache->load($cacheKey);
        if (!$html) {
            $sizes =  Config::inst()->get("MetaTagsContentControllerEXT", "favicon_sizes");
            if ($hasBaseFolderFavicon) {
                if (is_array($sizes)) {
                    $sizes = array_diff($sizes, array(16));
                }
            }
            $html = '';
            foreach ($sizes as $size) {
                $themeFolder = SSViewer::get_theme_folder();
                $file = "/".$themeFolder.'/icons/'.'icon-'.$size.'x'.$size.'.png';
                if (file_exists(Director::baseFolder().$file)) {
                    $html .= '
<link rel="icon" type="image/png" sizes="'.$size.'x'.$size.'"  href="'.$baseURL.$file.'" />
<link rel="apple-touch-icon" type="image/png" sizes="'.$size.'x'.$size.'"  href="'.$baseURL.$file.'" />';
                } elseif ($this->owner->getSiteConfig()->FaviconID) {
                    if ($favicon = $this->owner->getSiteConfig()->Favicon()) {
                        if ($favicon->exists() && $favicon instanceof Image) {
                            $generatedImage = $favicon->setWidth($size);
                            if ($generatedImage) {
                                $html .= '
<link rel="icon" type="image/png" sizes="'.$size.'x'.$size.'"  href="'.$baseURL.$generatedImage->Link().'" />
<link rel="apple-touch-icon" type="image/png" sizes="'.$size.'x'.$size.'"  href="'.$baseURL.$generatedImage->Link().'" />';
                            } else {
                                $favicon = null;
                            }
                        } else {
                            $favicon = null;
                        }
                    }
                }
            }
            if ($hasBaseFolderFavicon) {
                //do nothing
            } else {
                $faviconLink = "";
                $themeFolder = SSViewer::get_theme_folder();
                $faviconLocation = "/".$themeFolder.'/icons/favicon.ico';
                if (file_exists(Director::baseFolder().$faviconLocation)) {
                    $faviconLink = $baseURL.$faviconLocation;
                } elseif ($favicon) {
                    $generatedImage = $favicon->setWidth(16);
                    $faviconLink = $baseURL.$generatedImage->Link();
                }
                if ($faviconLink) {
                    $html .= '
<link rel="SHORTCUT ICON" href="'.$faviconLink.'" />';
                }
            }
            $cache->save($html, $cacheKey);
        }

        return $html;
    }

    protected function MetaTagsMetaTitle()
    {
        $title = "";
        $page = $this->owner;
        if (Config::inst()->get("MetaTagsContentControllerEXT", "use_separate_metatitle")) {
            if(!empty($page->MetaTitle)) {
                $title = $page->MetaTitle;
            }
        }
        if (! $title) {
            $title = $page->Title;
            if (! $title) {
                $title = $page->MenuTitle;
            }
        }
        return $title;
    }
}
