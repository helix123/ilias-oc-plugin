<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */
// Prevent a general redirect to the login screen for anonymous users.
// The checker will show an error page with login link instead
// (see ilInitialisation::InitILIAS() for details)
$_GET["baseClass"] = "ilStartUpGUI";

$basename = "/Customizing/global/plugins/Services/Repository/RepositoryObject/Matterhorn/MHData";

// Define a pseudo module to get a correct ILIAS_HTTP_PATH 
// (needed for links on the error page).
// "data" is assumed to be the ILIAS_WEB_DIR
// (see ilInitialisation::buildHTTPPath() for details)
define("ILIAS_MODULE", substr($_SERVER['PHP_SELF'],
					   strpos($_SERVER['PHP_SELF'], $basename) + strlen($basename)+1));

// Define the cookie path to prevent a different session created for web access
// (see ilInitialisation::setCookieParams() for details)
$GLOBALS['COOKIE_PATH'] = substr($_SERVER['PHP_SELF'], 0,
						  strpos($_SERVER['PHP_SELF'], $basename));

// Remember if the initial session was empty
// Then a new session record should not be written
// (see ilSession::_writeData for details)
$GLOBALS['WEB_ACCESS_WITHOUT_SESSION'] = (session_id() == "");

include_once "Services/Context/classes/class.ilContext.php";
ilContext::init(ilContext::CONTEXT_WEB_ACCESS_CHECK);

// Now the ILIAS header can be included
require_once "./include/inc.header.php";
require_once "./Services/Utilities/classes/class.ilUtil.php";
require_once "./Services/Object/classes/class.ilObject.php";
require_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";


/**
* Class ilMatterhornSendfile
*
* Checks if a user may access the Matterhorn-Object and sends files using sendfile
* Based on the WebAccessChecker
*
* @auther Per Pascal Grube <pascal.grube@tik.uni-stuttgart.de>
* @author Fred Neumann <fred.neumann@fim.uni-erlangen.de>
* @version $Id: class.ilWebAccessChecker.php 50013 2014-05-13 16:20:01Z akill $
*
*/

class ilMatterhornSendfile
{
	var $lng;
	var $ilAccess;

	/**
	* relative file path from ilias directory (without leading /)
	* @var string
	* @access private
	*/
	var $subpath;

	/**
	 * the id of the matterhorn object
	 * @var string
	 * @access private
	 */
	var $obj_id; 
	
	/**
	 * the id of the matterhorn episode
	 * @var string
	 * @access private
	 */
	var $episode_id;
	
	
	/**
	* absolute path in file system
	* @var string
	* @access private
	*/
	var $file;	

	/**
	 * Stores if this is a request for an episode.
	 * @var boolean
	 * @access private
	 */
	var $episodeRequest;
	
	
	/**
	 * The mimetype to be sent
	 * will be determined if null
	 * @var string
	 * @access private
	 */
	var $mimetype = null;
	
	
	/**
	* errorcode for sendError
	* @var integer
	* @access private
	*/
	var $errorcode;

	    
	/**
	* errortext for sendError
	* @var integer
	* @access private
	*/
	var $errortext;


	/**
	 * the configuration for the matterhorn object
	 * @var ilMatterhornConfig
	 * @access private
	 */
	var $configObject;
	
	/**
	* Constructor
	* @access	public
	*/
	function ilMatterhornSendfile()
	{
		global  $ilAccess, $lng;
		
		$this->lng =& $lng;
		$this->ilAccess =& $ilAccess;
		$this->params = array();
		$this->episodeRequest = false;
		
		// get the requested file and its type
		$uri = parse_url($_SERVER["REQUEST_URI"]);
		parse_str($uri["query"], $this->params);		

		global $basename;				
		
		// check if it is a request for an episode
		if(0 == strcmp(substr($uri["path"],0,strpos($_SERVER["PHP_SELF"],"/sendfile.php"))."/episode.json", $uri["path"])){
			$this->episodeRequest = true;
			if (!preg_match('/^[0-9]+\/[A-Za-z0-9]+/', $this->params['id'])) {
				$this->errorcode = 404;
				$this->errortext = $this->lng->txt("no_such_episode");
				return false;				
			}

			list($this->obj_id,$this->episode_id) = explode('/', $this->params['id']);
			
		} else {
                        $this->episodeRequest = false;
			$client_start = strpos($_SERVER['PHP_SELF'], $basename."/") + strlen($basename)+1;
			$pattern = substr($_SERVER['REQUEST_URI'], $client_start+strlen(CLIENT_ID));
			$this->subpath = urldecode(substr($uri["path"], strpos($uri["path"], $pattern)+1));
			$this->file = realpath(ILIAS_ABSOLUTE_PATH . "/". $this->subpath);
		
			// build url path for virtual function
			$this->virtual_path = str_replace($pattern, "virtual-" . $pattern, $uri["path"]);
			$this->obj_id = substr($this->subpath,0,strpos($this->subpath,'/'));


		}
		include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/Matterhorn/classes/class.ilMatterhornConfig.php");
		$this->configObject = new ilMatterhornConfig(); 
		// debugging
/*		echo "<pre>";
		var_dump($uri);
		echo "REQUEST_URI:         ". $_SERVER["REQUEST_URI"]. "\n";
		echo "Parsed URI:          ". $uri["path"]. "\n";
		echo "DOCUMENT_ROOT:       ". $_SERVER["DOCUMENT_ROOT"]. "\n";
		echo "PHP_SELF:            ". $_SERVER["PHP_SELF"]. "\n";
		echo "SCRIPT_NAME:         ". $_SERVER["SCRIPT_NAME"]. "\n";
		echo "SCRIPT_FILENAME:     ". $_SERVER["SCRIPT_FILENAME"]. "\n";
		echo "PATH_TRANSLATED:     ". $_SERVER["PATH_TRANSLATED"]. "\n";
		echo "ILIAS_WEB_DIR:       ". ILIAS_WEB_DIR. "\n";
		echo "ILIAS_HTTP_PATH:     ". ILIAS_HTTP_PATH. "\n";
		echo "ILIAS_ABSOLUTE_PATH: ". ILIAS_ABSOLUTE_PATH. "\n";
		echo "ILIAS_MODULE:        ". ILIAS_MODULE. "\n";
		echo "CLIENT_ID:           ". CLIENT_ID. "\n";
		echo "CLIENT_WEB_DIR:      ". CLIENT_WEB_DIR. "\n";
		echo "subpath:             ". $this->subpath. "\n";
		echo "file:                ". $this->file. "\n";
		echo "disposition:         ". $this->disposition. "\n";
		echo "ckeck_ip:            ". $this->check_ip. "\n";
		echo "send_mimetype:       ". $this->send_mimetype. "\n";
		echo "</pre>";
*/
		#		echo phpinfo();
#		exit;
		
		
		/*
		if (!file_exists($this->file))
		{
			$this->errorcode = 404;
			$this->errortext = $this->lng->txt("url_not_found");
			return false;
		}
		*/
	}

	/** 
	 * Check if request is for episode.
	 * @access public
	 */
	public function isEpisodeRequest(){
		return $this->episodeRequest;
	}
	
	/**
	 * Determine the current user(s)
	 */
	public function determineUser()
	{
		global $ilUser;
		
	    // a valid user session is found 
	    if ($_SESSION["AccountId"])
	    {
	    	$this->check_users = array($_SESSION["AccountId"]);	
	    	return;
	    }	   
	    else
	    {
	    	$this->check_users = array(ANONYMOUS_USER_ID);
		$_SESSION["AccountId"] = ANONYMOUS_USER_ID;
			$ilUser->setId(ANONYMOUS_USER_ID);
			$ilUser->read();	
			return;
	    }
	}

	/**
	 * Check access rights of the requested file
	 * @access	public
	 */
	public function checkEpisodeAccess()
	{

                global $ilLog;

		// an error already occurred at class initialisation
		if ($this->errorcode)
		{
			return false;
		}	
		// do this here because ip based checking may be set after construction
		$this->determineUser();
		if ($this->checkAccessObject($this->obj_id))
		{
			return true;
		}
		// none of the checks above gives access
		$this->errorcode = 403;
		$this->errortext = $this->lng->txt('msg_no_perm_read');
		return false;
	}
	
	
	/**
	* Check access rights of the requested file
	* @access	public
	*/
	public function checkFileAccess()
	{
                global $ilLog;

                $ilLog->write("MHSendfile: check access");
		// an error already occurred at class initialisation
		if ($this->errorcode)
		{
	        return false;
	    }

	    // do this here because ip based checking may be set after construction
	    $this->determineUser();

#	echo $this->obj_id;
#	    if (is_numeric($this->obj_id)) {
#	    	echo "is integer\n";
#	    } else {
#	    	echo "is not an integer\n";
#	    }
		$type = 'xmh';
		
		if (!$this->obj_id || $type == 'none')
		{
			$this->errorcode = 404;
			$this->errortext = $this->lng->txt("obj_not_found");
			return false;
		}
		$ilLog->write("MHSendfile: check access");
		if ($this->checkAccessObject($this->obj_id))
		{
			return true;
		}
		$ilLog->write("MHSendfile: no access found");
		// none of the checks above gives access
		$this->errorcode = 403;
		$this->errortext = $this->lng->txt('msg_no_perm_read');
		return false;
	}
	
	
	
	
	/**
	* Check access rights for an object by its object id
	*
	* @param    int     	object id
	* @return   boolean     access given (true/false)
	*/
	private function checkAccessObject($obj_id, $obj_type = '')
	{
	    global $ilAccess;

		if (!$obj_type)
		{
			$obj_type = ilObject::_lookupType($obj_id);
		}	
		$ref_ids  = ilObject::_getAllReferences($obj_id);

		foreach($ref_ids as $ref_id)
		{
			foreach ($this->check_users as $user_id)
			{				
				if ($ilAccess->checkAccessOfUser($user_id, "read", "view", $ref_id, $obj_type, $obj_id))
				{
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Send the requested eposide.json 
	 * @access public
	 */
	public function sendEpisode(){
		global $basename,$ilLog;
		
		$url = $this->configObject->getMatterhornEngageServer().'/search/episode.json?id='.$this->episode_id;
                $ilLog->write("EngageURL: ".$url);
		$curl = curl_init();
                curl_setopt($curl, CURLOPT_URL,$url);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
		curl_setopt($curl, CURLOPT_USERPWD, $this->configObject->getMatterhornUser().':'.$this->configObject->getMatterhornPassword());
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("X-Requested-Auth: Digest","X-Opencast-Matterhorn-Authorization: true"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);	
                $ilLog->write("CurlRespone:".$curl_respone);	
#curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 
                    $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
#                /ilias/Customizing/global/plugins/Services/Repository/RepositoryObject/Matterhorn/MHData/sendfile.php
		echo str_replace(str_replace("/", "\/", $this->configObject->getMatterhornEngageServer())."\/static\/engage-player", $protocol.$_SERVER['HTTP_HOST'].substr($_SERVER["PHP_SELF"],0,-12).CLIENT_ID."/".$this->obj_id,$curl_response);
		curl_close($curl);
	}
	
	/**
	* Send the requested file as if directly delivered from the web server
	* @access	public
	*/
	public function sendFile()
	{

        global $ilLog;
//		header('x-sendfile: '.$this->configObject->getXSendfileBasedir() . substr($this->subpath, strlen($this->obj_id)));
		include_once("./Services/Utilities/classes/class.ilMimeTypeUtil.php");
		$ilLog->write("MHSendfile: ".$this->configObject->getXSendfileBasedir().substr($this->subpath, strlen($this->obj_id)));
		$mime = ilMimeTypeUtil::getMimeType($this->configObject->getXSendfileBasedir().substr($this->subpath, strlen($this->obj_id)));
		header("Content-Type: ".$mime);
#		if (isset($_SERVER['HTTP_RANGE'])) {
#			$ilLog->write("range request".$_SERVER['HTTP_RANGE']);
#		}
		$file = $this->configObject->getXSendfileBasedir().substr($this->subpath, strlen($this->obj_id));
		$fp = @fopen($file, 'rb');
		$size   = filesize($file); // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte

		header("Accept-Ranges: 0-$length");
		if (isset($_SERVER['HTTP_RANGE'])) {
			$c_start = $start;
			$c_end   = $end;

			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if (strpos($range, ',') !== false) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			if ($range == '-') {
				$c_start = $size - substr($range, 1);
			}else{
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			$c_end = ($c_end > $end) ? $end : $c_end;
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}

		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: ".$length);

		$buffer = 1024 * 8;
		while(!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				$buffer = $end - $p + 1;
			}
			set_time_limit(0);
			echo fread($fp, $buffer);
			flush();
		}
		fclose($fp);
	}
	
	
	
	/**
	* Send an error response for the requested file
	* @access	public
	*/
	public function sendError()
	{
		global $ilUser, $tpl, $lng, $tree;

		switch ($this->errorcode)
		{
			case 404:
				header("HTTP/1.0 404 Not Found");
				return;
//				break;
			case 403:
			default:
				header("HTTP/1.0 403 Forbidden");
				return;
				
				//break;
		}
		
		// set the page base to the ILIAS directory
		// to get correct references for images and css files
		$tpl->setCurrentBlock("HeadBaseTag");
		$tpl->setVariable('BASE', ILIAS_HTTP_PATH . '/error.php');
		$tpl->parseCurrentBlock();
		$tpl->addBlockFile("CONTENT", "content", "tpl.error.html");

		// Check if user is logged in
		$anonymous = ($ilUser->getId() == ANONYMOUS_USER_ID);

		if ($anonymous)
		{
			// Provide a link to the login screen for anonymous users

			$tpl->SetCurrentBlock("ErrorLink");
			$tpl->SetVariable("TXT_LINK", $lng->txt('login_to_ilias'));
			$tpl->SetVariable("LINK", ILIAS_HTTP_PATH. '/login.php?cmd=force_login&client_id='.CLIENT_ID);
			$tpl->ParseCurrentBlock();
		}
		else
		{
			// Provide a link to the repository for authentified users

			$nd = $tree->getNodeData(ROOT_FOLDER_ID);
			$txt = $nd['title'] == 'ILIAS' ? $lng->txt('repository') : $nd['title'];

			$tpl->SetCurrentBlock("ErrorLink");
			$tpl->SetVariable("TXT_LINK", $txt);
			$tpl->SetVariable("LINK", ILIAS_HTTP_PATH. '/ilias.php?baseClass=ilRepositoryGUI&amp;client_id='.CLIENT_ID);
			$tpl->ParseCurrentBlock();
		}

		$tpl->setCurrentBlock("content");
		$tpl->setVariable("ERROR_MESSAGE",($this->errortext));
		$tpl->setVariable("SRC_IMAGE", ilUtil::getImagePath("mess_failure.png"));
		$tpl->parseCurrentBlock();

		$tpl->show();
		exit;
	}
	
	/**
	* Get the mime type of the requested file
	* @param    string      default type
	* @return   string      mime type
	* @access	public
	*/
	public function getMimeType($default = 'application/octet-stream')
	{
		// take a previously set mimetype
		if (isset($this->mimetype))
		{
			return $this->mimetype;
		}
		
		$mime = '';

		include_once("./Services/Utilities/classes/class.ilMimeTypeUtil.php");
		$mime = ilMimeTypeUtil::getMimeType($this->file);
		$this->mimetype = $mime ? $mime : $default;
		return $this->mimetype;
	}
}
?>