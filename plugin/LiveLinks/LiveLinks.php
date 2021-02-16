<?php

global $global;
require_once $global['systemRootPath'] . 'plugin/Plugin.abstract.php';
require_once $global['systemRootPath'] . 'plugin/LiveLinks/Objects/LiveLinksTable.php';
require_once $global['systemRootPath'] . 'plugin/Live/Live.php';


class LiveLinks extends PluginAbstract {

    public function getTags() {
        return array(
            PluginTags::$LIVE,
            PluginTags::$FREE,
            PluginTags::$PLAYER,
        );
    }

    public function getDescription() {
        $desc = "Register Livestreams external Links from any HLS provider, Wowza and others";
        $desc .= $this->isReadyLabel(array('Live'));
        $help = "<br><small><a href='https://github.com/WWBN/AVideo/wiki/LiveLinks-Plugin' target='__blank'><i class='fas fa-question-circle'></i> Help</a></small>";

        return $desc.$help;
    }

    public function getName() {
        return "LiveLinks";
    }

    public function getEmptyDataObject() {
        global $global;
        $obj = new stdClass();
        $obj->onlyAdminCanAddLinks = true;
        $obj->buttonTitle = "Add a Live Link";
        $obj->disableGifThumbs = false;
        $obj->disableLiveThumbs = false;
        $obj->doNotShowLiveLinksLabel = false;
        $obj->disableProxy = false;
        return $obj;
    }

    public function getUUID() {
        return "39d3b5fe-9702-4f1d-9ffd-fe1cd22a4dc7";
    }

    public function getPluginVersion() {
        return "3.2";   
    }

    public function canAddLinks() {
        $obj = $this->getDataObject();
        if (!User::isLogged()) {
            return false;
        }
        if ($obj->onlyAdminCanAddLinks && !User::isAdmin()) {
            return false;
        }
        return User::canStream();
    }

    public function getHTMLMenuRight() {
        global $global;
        $obj = $this->getDataObject();
        if (!$this->canAddLinks()) {
            return '';
        }

        include $global['systemRootPath'] . 'plugin/LiveLinks/view/menuRight.php';
    }

    static function getAllActive() {
        global $global;
        $sql = "SELECT * FROM  LiveLinks WHERE status='a' AND start_date <= now() AND end_date >= now() ORDER BY start_date ";
        $res = $global['mysqli']->query($sql);
        $rows = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        } else {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return $rows;
    }
    
    
    static function getImage($id) {
        global $global;
        return "{$global['webSiteRootURL']}plugin/LiveLinks/getImage.php?id={$id}&format=jpg";
    }
    
    static function getImageGif($id) {
        global $global;
        return "{$global['webSiteRootURL']}plugin/LiveLinks/getImage.php?id={$id}&format=gif";
    }

    /**
     * 
     * @return type array(array("key"=>'live key', "users"=>false, "name"=>$userName, "user"=>$user, "photo"=>$photo, "UserPhoto"=>$UserPhoto, "title"=>''));
     */
    public function getLiveApplicationArray() {
        global $global;
        $obj = $this->getDataObject();
        $filename = $global['systemRootPath'] . 'plugin/LiveLinks/view/menuItem.html';
        $filenameExtra = $global['systemRootPath'] . 'plugin/LiveLinks/view/extraItem.html';
        $filenameExtraVideoPage = $global['systemRootPath'] . 'plugin/LiveLinks/view/extraItemVideoPage.html';
        $row = LiveLinks::getAllActive();
        $array = array();
        $search = array(
            '_unique_id_',
            '_user_photo_',
            '_title_',
            '_user_identification_',
            '_description_',
            '_link_',
            '_imgJPG_',
            '_imgGIF_',
            '_class_',
            '_total_on_live_links_id_'
        );
        $content = file_get_contents($filename);
        $contentExtra = file_get_contents($filenameExtra);
        $contentExtraVideoPage = file_get_contents($filenameExtraVideoPage);
        
        $liveUsers = AVideoPlugin::isEnabledByName('LiveUsers');
        
        foreach ($row as $value) {
            
            if($value['type']=='unlisted'){
                continue;
            }
            if($value['type']=='logged_only'){
                if(!User::isLogged()){
                    continue;
                }
            }
            $UserPhoto = User::getPhoto($value['users_id']);   
            $name = User::getNameIdentificationById($value['users_id']);
            $replace = array(
                $value['id'],
                $UserPhoto,
                $value['title'],
                $name,
                str_replace('"', "", $value['description']),
                self::getLink($value['id']),
                '<img src="'."{$global['webSiteRootURL']}plugin/LiveLinks/getImage.php?id={$value['id']}&format=jpg".'" class="thumbsJPG img-responsive" height="130">',
                empty($obj->disableGifThumbs)?('<img src="'."{$global['webSiteRootURL']}plugin/LiveLinks/getImage.php?id={$value['id']}&format=gif".'" style="position: absolute; top: 0px; height: 0px; width: 0px; display: none;" class="thumbsGIF img-responsive" height="130">'):"",
                (isVideo())?"col-xs-6":"col-lg-2 col-md-4 col-sm-4 col-xs-6",
                ($liveUsers?getLiveUsersLabelLiveLinks($value['id']):'')
            );

            $newContent = str_replace($search, $replace, $content);
            $newContentExtra = str_replace($search, $replace, $contentExtra);
            $newContentExtraVideoPage = str_replace($search, $replace, $contentExtraVideoPage);
            $array[] = array(
                "type" => "LiveLink",
                "html" => $newContent,
                "htmlExtra" => $newContentExtra,
                "htmlExtraVideoPage" => $newContentExtraVideoPage,
                "UserPhoto" => $UserPhoto,
                "title" => $value['title'],
                "users_id" => $value['users_id'],
                "name" => $name,
                "poster" => self::getPosterToLiveFromId($value['id']),
                "link" => self::getLinkToLiveFromId($value['id'], true)
            );
        }

        return $array;
    }
    
    
    public function updateScript() {
        global $global;        
        if (AVideoPlugin::compareVersion($this->getName(), "2") < 0) {
            $sqls = file_get_contents($global['systemRootPath'] . 'plugin/LiveLinks/install/updateV2.0.sql');
            $sqlParts = explode(";", $sqls);
            foreach ($sqlParts as $value) {
                sqlDal::writeSql(trim($value));
            }
        }
        if (AVideoPlugin::compareVersion($this->getName(), "3.1") < 0) {
            $sqls = file_get_contents($global['systemRootPath'] . 'plugin/LiveLinks/install/updateV3.0.sql');
            $sqlParts = explode(";", $sqls);
            foreach ($sqlParts as $value) {
                sqlDal::writeSql(trim($value));
            }
        }
        
        return true;
    }
    
    
    public function getHeadCode() {
        global $global;
        $obj = $this->getDataObject();
        // preload image
        $js = "";
        $css = '';
        if(!empty($obj->doNotShowLiveLinksLabel)){
            $css .= '<style>.livelinksLabel{display: none;}</style>';
        }
        
        return $js.$css;
    }
    
    public static function getLinkToLiveFromId($id, $embed=false){
        return self::getLink($id, $embed);
    }
    
    public static function getLink($id, $embed=false){
        global $global;
        //return "{$global['webSiteRootURL']}plugin/LiveLinks/view/Live.php?link={$id}".($embed?"&embed=1":"");
        $ll = new LiveLinksTable($id);
        if(!$embed){
            return "{$global['webSiteRootURL']}liveLink/{$id}/". urlencode(cleanURLName($ll->getTitle()));
        }else{
            return "{$global['webSiteRootURL']}liveLinkEmbed/{$id}/". urlencode(cleanURLName($ll->getTitle()));
        }
    }

    public function getPosterToLiveFromId($id){
        global $global;
        return "{$global['webSiteRootURL']}plugin/LiveLinks/getImage.php?id={$id}&format=jpg";
    }
    
    public static function isLiveThumbsDisabled(){
        $obj = AVideoPlugin::getDataObject("LiveLinks");
        if(!empty($obj->disableLiveThumbs)){
            return true;
        }
        return false;
    }

    public function getPosterThumbsImage($users_id, $live_servers_id) {
        global $global;
        $file = Live::_getPosterThumbsImage($users_id, $live_servers_id);

        if (!file_exists($global['systemRootPath'] . $file)) {
            $file = "plugin/Live/view/OnAir.jpg";
        }

        return $file;
    }
    
    public function getUploadMenuButton() {
        global $global;
        if (!$this->canAddLinks()) {
            return '';
        }
        $obj = $this->getDataObject();
        $buttonTitle = $obj->buttonTitle;
        //include $global['systemRootPath'] . 'plugin/LiveLinks/getUploadMenuButton.php';
    }
    
    

    public static function getAllVideos($status = "", $showOnlyLoggedUserVideos = false, $activeUsersOnly = true) {
        global $global, $config, $advancedCustom;
        if (AVideoPlugin::isEnabledByName("VideoTags")) {
            if (!empty($_GET['tags_id']) && empty($videosArrayId)) {
                TimeLogStart("video::getAllVideos::getAllVideosIdFromTagsId({$_GET['tags_id']})");
                $videosArrayId = VideoTags::getAllVideosIdFromTagsId($_GET['tags_id']);
                TimeLogEnd("video::getAllVideos::getAllVideosIdFromTagsId({$_GET['tags_id']})", __LINE__);
            }
        }
        $status = str_replace("'", "", $status);

        $sql = "SELECT u.*, v.*, c.iconClass, c.name as category, c.clean_name as clean_category,c.description as category_description, v.created as videoCreation, v.modified as videoModified "
                . " FROM LiveLinks as v "
                . " LEFT JOIN categories c ON categories_id = c.id "
                . " LEFT JOIN users u ON v.users_id = u.id "
                . " WHERE 1=1 ";

        if ($showOnlyLoggedUserVideos === true && !Permissions::canModerateVideos()) {
            $uid = intval(User::getId());
            $sql .= " AND v.users_id = '{$uid}'";
        } elseif (!empty($showOnlyLoggedUserVideos)) {
            $uid = intval($showOnlyLoggedUserVideos);
            $sql .= " AND v.users_id = '{$uid}'";
        } elseif (!empty($_GET['channelName'])) {
            $user = User::getChannelOwner($_GET['channelName']);
            $uid = intval($user['id']);
            $sql .= " AND v.users_id = '{$uid}' ";
        }

        if ($activeUsersOnly) {
            $sql .= " AND u.status = 'a' ";
        }

        if ($status == "publicOnly") {
            $sql .= " AND v.`type` = 1 ";
        } elseif (!empty($status)) {
            $sql .= " AND v.`type` = '{$status}'";
        }

        if (!empty($_GET['catName'])) {
            $catName = $global['mysqli']->real_escape_string($_GET['catName']);
            $sql .= " AND (c.clean_name = '{$catName}' OR c.parentId IN (SELECT cs.id from categories cs where cs.clean_name =  '{$catName}' ))";
        }

        if (!empty($_GET['modified'])) {
            $_GET['modified'] = str_replace("'", "", $_GET['modified']);
            $sql .= " AND v.modified >= '{$_GET['modified']}'";
        }

        $sql .= AVideoPlugin::getVideoWhereClause();

        if (strpos(strtolower($sql), 'limit') === false) {
            if (!empty($_GET['limitOnceToOne'])) {
                $sql .= " LIMIT 1";
                unset($_GET['limitOnceToOne']);
            }else{
                $_REQUEST['rowCount'] = getRowCount();
                if (!empty($_REQUEST['rowCount'])) {
                    $sql .= " LIMIT {$_REQUEST['rowCount']}";
                } else {
                    _error_log("getAllVideos without limit " . json_encode(debug_backtrace()));
                    if (empty($global['limitForUnlimitedVideos'])) {
                        $global['limitForUnlimitedVideos'] = 100;
                    }
                    if ($global['limitForUnlimitedVideos'] > 0) {
                        $sql .= " LIMIT {$global['limitForUnlimitedVideos']}";
                    }
                }
            }
        }

        //echo $sql;exit;
        //_error_log("getAllVideos($status, $showOnlyLoggedUserVideos , $ignoreGroup , ". json_encode($videosArrayId).")" . $sql);
        $res = sqlDAL::readSql($sql);
        $fullData = sqlDAL::fetchAllAssoc($res);

        sqlDAL::close($res);
        $videos = array();
        if ($res != false) {
            foreach ($fullData as $row) {
                $row = cleanUpRowFromDatabase($row);
                if (empty($otherInfo)) {
                    $otherInfo = array();
                    $otherInfo['category'] = xss_esc_back($row['category']);
                    $otherInfo['groups'] = UserGroups::getVideoGroups($row['id']);
                    //$otherInfo['title'] = UTF8encode($row['title']);
                    $otherInfo['description'] = UTF8encode($row['description']);
                    $otherInfo['descriptionHTML'] = Video::htmlDescription($otherInfo['description']);
                    $otherInfo['filesize'] = 0;
                }
                
                foreach ($otherInfo as $key => $value) {
                    $row[$key] = $value;
                }
                
                $row['rotation'] = 0;
                $row['filename'] = '';
                $row['type'] = 'livelinks';
                $row['duration'] = '';
                $row['isWatchLater'] = 0;
                $row['isFavorite'] = 0;
                $row['views_count'] = 0;

                $videos[] = $row;
            }
            //$videos = $res->fetch_all(MYSQLI_ASSOC);
        } else {
            $videos = false;
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return $videos;
    }


}
