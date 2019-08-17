<?php
class KitsuHandler{
	const API_BASE_URL = 'https://kitsu.io/api';
	const IMG_POSTER_URL = 'https://media.kitsu.io/anime/poster_images';
	const IMG_WALLPAPER_URL = 'https://media.kitsu.io/anime/cover_images';
	const TOKEN_LIFE_SPAN = 2592000; //30 days
	const TOKEN_LIFE_MARGIN = 120; //2 mins

	private $credentials;
	public $uid;

	function __construct($credentials = NULL){
		// set headers
		$this->hdrs = array('Content-Type: application/vnd.api+json','Accept: application/vnd.api+json');

		// get credentials and set bearer
		list($this->oauth, $this->hdrs[]) = $this->validateInput($credentials);

		// set user id
		if(isset($this->oauth)){
			$this->uid = $this->getUser(array('filter[self]'=>'true'))->data[0]->id;
		}
	}

	/* CREATE CURL REQUEST */

	private function sendRequest($method, $path, $body = array()){
		$ch = curl_init();
		// setopts
		curl_setopt($ch, CURLOPT_URL, self::API_BASE_URL . $path);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ( ( strpos( $path, 'oauth') !== FALSE ) ? ( isset($this->br_rf) ? ( array( 'Content-Type: multipart/form-data', 'Authorization: Bearer ' . $this->br_rf ) ) : ( array( 'Content-Type: multipart/form-data') ) )  : ( $this->hdrs ) ) );
		(!empty($body) ? curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)) : NULL);
		
		// execute & return
		$response = json_decode(curl_exec($ch)); $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return ((!curl_errno($ch)) ? $response : array('http_code'=>$httpcode,'curl_error_response'=>curl_error($ch),'request_response'=>$response));
	}

	/* PUBLIC METHODS */

	public function seriesInfo($kind, $filters = array()){
		//$filters = array('page[limit]'=>20,'page[offset]'=>0,'filter[id]'=>NULL,'filter[genres]'=>NULL,'sort[popularityRank]'=>NULL)
		return $this->sendRequest('GET', '/edge/' . $kind . $this->buildQuery($filters));
	}

	public function seriesGenres($id, $kind, $filters = array()){
		return $this->sendRequest('GET', '/edge/' . $kind . '/' . $id . '/genres' . $this->buildQuery($filters));
	}

	public function franchiseInfo($filters = array()){
		return $this->sendRequest('GET', '/edge/franchises' . $this->buildQuery($filters));
	}

	public function episodeInfo($filters = array()){
		return $this->sendRequest('GET', '/edge/episodes' . $this->buildQuery($filters));
	}

	public function genreInfo($filters = array()){
		return $this->sendRequest('GET', '/edge/genres' . $this->buildQuery($filters));
	}

	public function mappingInfo($filters = array()){
		return $this->sendRequest('GET', '/edge/mappings' . $this->buildQuery($filters));
	}

	public function mappingInfoMAL($filters = array()){
		$prereq = $this->sendRequest('GET', '/edge/mappings' . $this->buildQuery( $filters ) );
		if( !isset($prereq->data[0]->id) ) return NULL;
		return $this->sendRequest('GET', '/edge/mappings/' . $prereq->data[0]->id . '/item');
	}

	public function streamerInfo($filters = array()){
		return $this->sendRequest('GET', '/edge/streamers' . $this->buildQuery($filters));
	}

	public function streamingLinkInfo($filters = array()){
		return $this->sendRequest('GET', '/edge/streaming-links' . $this->buildQuery($filters));
	}
	
	public function trendingInfo($kind){
		return $this->sendRequest('GET', '/edge/trending/' . $kind);
	}

	public function getUser($filters = array()){
		//$filters = array('filter[self]'=>'true')
		return $this->sendRequest('GET', '/edge/users' . $this->buildQuery($filters));
	}

	public function getUserLibrary($filters = array()){
		//$filter = array('page[limit]'=>500,'page[offset]'=>0,'filter[userId]'=>NULL)
		return $this->sendRequest('GET', '/edge/library-entries?' . $this->buildQuery($filters));
	}

	public function addEntry($publicEntryId, $newAttributes, $kind){
		$data = array( 'data' => array( 'type' => 'library-entries', 'attributes'=>$newAttributes, 'relationships' => array( $kind => array( 'data'=>array( 'type' => $kind, 'id' => $publicEntryId ) ), 'user' => array( 'data' => array( 'type' => 'users', 'id' => $this->uid ) ) ) ) );
		return $this->sendRequest('POST', '/edge/library-entries', $data);
	}

	public function updateEntry($publicEntryId, $newAttributes, $kind){
		$pub2lib = $this->pubToLibSingle($publicEntryId, $kind);
		$data = array( 'data' => array( 'id' => $pub2lib, 'type' => 'library-entries' , 'attributes' => $newAttributes ) );
		return $this->sendRequest('PATCH', '/edge/library-entries/'.$pub2lib, $data);
	}

	public function removeEntry($publicEntryId, $kind){
		$pub2lib = $this->pubToLibSingle($publicEntryId, $kind);
		$data = array( 'data' => array( 'id' => $pub2lib, 'type' => 'library-entries' ) );
		return $this->sendRequest('DELETE', '/edge/library-entries/'.$pub2lib, $data);
	}

	public function pubToLib($filters = array()){
		//$filters = array('page[limit]'=>1,'filter[userId]'=>NULL,'kind'=>'anime', 'animeId'=>1);
		return $this->sendRequest('GET', '/edge/library-entries?' . $this->buildQuery($filters));
	}

	private function pubToLibSingle($publicEntryId, $kind){
		return $this->pubToLib( array( 'page[limit]' => 1, 'filter[userId]' => $this->uid, 'filter[kind]' => $kind, ('filter['.$kind.'Id]') => $publicEntryId ) )->data[0]->id;
	}

	public function importList($xmlFile, $kind = 'my-anime-list-xml', $strat = 'greater'){
		$data = array( 'data' =>array( 'attributes' =>array( 'inputFile' => ('data:text/xml;base64,'.base64_encode($xmlFile)), 'kind' => $kind, 'strategy' => 'greater' ), 'relationships' => array( 'user' => array( 'data' => array( 'type' => 'users', 'id' => $this->uid ) ) ), 'type' => 'list-imports' ) );
		return $this->sendRequest('POST', '/edge/list-imports', $data);
	}

	public function importListStatus($filters = array()){
		//$filters = array('filter[userId]'=>NULL,'filter[id]'=>NULL)
		return $this->sendRequest('GET', '/edge/list-imports' . $this->buildQuery($filters));
	}
	
	public function createAccount($email, $username, $password){
		$data = array( 'data' => array( 'attributes' => array( 'email' => $email, 'name' => $username, 'password' => $password ), 'type' => 'users' ) );
		return $this->sendRequest('POST', '/edge/users', $data);
	}

	public function updateAccountSettings($newAttributes){
		//$newAttributes = array('name'=>'Kintoki','slug'=>'OddJobs','about'=>'allboutdatmoney');
		$data = array( 'data' => array( 'id' => $this->uid, 'type' => 'users', 'attributes' => array( 'name' => 'T35T', 'proTier'=>3 ) ) );
		return $this->sendRequest('PATCH', '/edge/users/' . $this->uid, $data);
	}

	public function deleteAccount(){
		return $this->sendRequest('DELETE', '/edge/users/' . $this->uid);
	}

	public function notificationSettings(){
		return $this->sendRequest('GET', '/edge/notification-settings?filter[userId]='.$this->uid);
	}

	public function updateNotificationSettings($notifyId, $notifyWeb = false, $notifyMobile = false){
		$data = array( 'data' => array( 'id' => $notifyId, 'attributes' => array( 'webEnabled' => $notifyWeb, 'mobileEnabled' => $notifyMobile ), 'type' => 'notification-settings') );
		return $this->sendRequest('PATCH', '/edge/users' . $this->uid, $data);
	}

	/* BUILD QUERY */

	private function buildQuery($query = array()){
		return (((!$query) ? NULL : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
	}

	/* METHODS FOR CHECKING INPUT CREDENTIALS */

	private function getBearerToken($credentials){
		return $this->sendRequest('POST', '/oauth/token' . $this->buildQuery( array_merge( array( 'grant_type'=>'password' ), $credentials ) ) );
	}

	private function refreshBearerToken($refreshToken){
		return $this->sendRequest('POST', '/oauth/token' . $this->buildQuery( array( 'grant_type'=>'refresh_token', 'refresh_token'=>$refreshToken ) ) );
	}

	private function validateInput($input){
		if(file_exists($input) && (pathinfo($input, PATHINFO_EXTENSION) === 'json')){
			try{
				$f = $this->determineCredentialType(json_decode(file_get_contents($input)), $input);
				return array($f, ('Authorization: Bearer ' . $f->access_token) ); 
			}catch (Exception $e){
				echo 'Caught exception: ',  $e->getMessage(), "\n";
			}
		}elseif(is_array($input)){
			try{
				$f = $this->determineCredentialType(((object) $input)); 
				return array($f, ('Authorization: Bearer ' . $f->access_token) ); 
			}catch (Exception $e){
				echo 'Caught exception: ',  $e->getMessage(), "\n";
			}
		}else{
			return NULL;
		}
	}

	private function determineCredentialType($jsonInput, $input = ''){
		if((is_object($jsonInput)) && (strlen($jsonInput->access_token) === 64) && (strlen($jsonInput->refresh_token) === 64) && (strlen($jsonInput->created_at) === 10)){
			if((time() - self::TOKEN_LIFE_MARGIN) > ($jsonInput->created_at + self::TOKEN_LIFE_SPAN)){
				$this->br_rf = $jsonInput->access_token;
				$f = $this->refreshBearerToken($jsonInput->refresh_token);
				unset($this->br_rf);
				file_put_contents($input, json_encode( $f ) );
				echo '<div>OATH credentials refreshed - overwritten json file</div>';
				return $f;
			}
			return $jsonInput;
		}elseif(is_object($jsonInput) && isset($jsonInput->username) && isset($jsonInput->password)){
			return $this->getBearerToken(json_encode($jsonInput));
		}else{
			throw new Exception('"Invalid credentials provided. Credentials can be provided in 2 ways: array format or path of a JSON file containing credentials. The user can either provide an array/file containing OAUTH credentials OR the username and password. In case of OAUTH credentials, the bearer token, refresh token and creation date have to be provided e.g. <b>array(\'access_token\'=>\'ABC...XYZ\',\'refresh_token\'=>\'HIJ...UVW\',\'created_at\'=>\'1557169141\')</b>. In case of USER credentials, the username and password need to be provided e.g. <b>array(\'username\'=>\'kintoki\',\'password\'=>\'m0neym0neym0ney\')</b>"');
		}
	}
}
?>