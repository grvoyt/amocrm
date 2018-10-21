<?php
final class Amocrm {
	private $auth;
	private $user = array();
	private $subdomain;
	private $amocrm_map;
	private $voronka_id;
	private $tags;
	private $lead_id;

	public $debug = false;

	public function __construct($login,$hash,$subdomain) {
		$this->user = array(
			'USER_LOGIN' => $login,
			'USER_HASH' => $hash
		);
		$this->subdomain = $subdomain;
	}

	// информация об аккаунте
	public function getInfo() {
		$link = 'https://'. $this->subdomain .'.amocrm.ru/private/api/v2/json/accounts/current?'.http_build_query($this->user);
		return file_get_contents($link);
	}

	// вывод информации об аккаунте
	public function getInfoPrint() {
		print_r($this->getInfo());
	}

	// установка кастомных полей
	public function setMap($data = array()) {
		$this->amocrm_map = $data;
	}

	// вывод карты кастомных полей
	public function getMap() {
		print_r( $this->amocrm_map );
	}

	public function getLeadId() {
		return $this->lead_id;
	}

	// установка воронки
	public function setStatus($data = '') {
		$this->voronka_id = $data;
	}

	//установка тегов
	public function setTags($data = '') {
		$this->tags = $data;
	}

	// функция запроса CURL
	public function curlSend($link = '',$data='') {
		$method = is_array($data) ? 'POST' : 'GET';
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
		curl_setopt($curl, CURLOPT_URL, $link);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookie.txt');
		curl_setopt($curl, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookie.txt');
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

		$out = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return $out;
	}

	// подключение к амо
	public function auth() {
		$subdom = $this->subdomain;
		$link = 'https://' . $subdom . '.amocrm.ru/private/api/auth.php?type=json';
		$response = $this->curlSend($link,$this->user);
		if($this->debug) $this->log('AUTH ==> '.$response);
		return $response;
	}

	// установка лида
	public function leadSet($data = array()) {
		
		$leads['request']['leads']['add'] = array(
			array(
				'name' => $data['lead_name'],
				"date_create" => time(),
			)
		);
		if( $this->voronka_id ) {
			$leads['request']['leads']['add'][0]['status_id'] = $this->voronka_id;
		}

		if( $this->amocrm_map ) {
			foreach($data as $k => $v) {
				if(!isset($this->amocrm_map[$k])) continue;
				if(empty($this->amocrm_map[$k]))  continue;
				if(empty($v))  continue;
	            $obj = new stdClass();
	            $obj->id=$this->amocrm_map[$k];
	            $obj->name=$k;
	            $obj->values=array(
	                array("value"=> $v)
	            );
	            $custom_fields[]=$obj;
			}
			$leads['request']['leads']['add'][0]['custom_fields'] = $custom_fields;
		} else {
			$leads['request']['leads']['add'][0]['custom_fields'] = array();
		}

		if( $this->tags ) {
			$leads['request']['leads']['add'][0]['tags'] = $this->tags;
		}

		if($this->debug) {
			$this->log('Lead data ==> '.json_encode($leads));
		}
		$link = 'https://' . $this->subdomain . '.amocrm.ru/private/api/v2/json/leads/set';

		$res = $this->curlSend($link,$leads);
		$lead_id = json_decode( $res, true); // отправка лида
		if($this->debug) $this->log('answer server ==> '.$res);
		$lead_id = $lead_id['response']['leads']['add'][0]['id']; // получение id сделки
		$this->lead_id = $lead_id;
		return true;
	}

	public function log($data,$vardump = false) {
		if($vardump) {
			var_dump($data);
		} else {
			print_r($data);
		}
		print_r(PHP_EOL);
	}

	// установка контакта
	public function contactSet($data = array()) {

		$custom_contact = array();
		if( !empty($data['phone']) ) {
			$custom_contact = array_merge($custom_contact, array(
				array(
					#Телефоны
					'id' => $this->amocrm_map['phone'],
					'values' => array(
						array(
							'value'=>$data['phone'],
							'enum'=>'MOB',
						)
					)

				)
			));
		}

		if( !empty($data['email']) ) {
			$custom_contact = array_merge($custom_contact, array(
				array(
					#Телефоны
					'id' => $this->amocrm_map['email'],
					'values' => array(
						array(
							'value'=>$data['email'],
							'enum'=>'PRIV',
						)
					)

				)
			));
		}

		$contacts['request']['contacts']['add']=array(
		  array(
			'name'=>(isset($data['name']) ? $data['name'] : '-'), #Имя контакта
			//'last_modified'=>1298904164, //optional
			'linked_leads_id'=> $this->lead_id,
			//'company_name'=>'amoCRM', #Наименование компании
			//'tags' => 'Important, USA', #Теги
			'custom_fields' => $custom_contact,
		  )
		);

		$link='https://'. $this->subdomain .'.amocrm.ru/private/api/v2/json/contacts/set';
		return $this->curlSend($link,$contacts);
	}

	// функция добавления заметок или задачи
	public function amocrm_add_one($data=array(), $action='notes'){
	    $data['element_id'] = $this->lead_id;
	    $subdomain = $this->subdomain;
	    # Формируем ссылку для запроса
	    $link='https://'.$subdomain.".amocrm.ru/private/api/v2/json/$action/set?type=json";

	    # Массив с параметрами, которые нужно передать методом POST к API системы
	    $notes['request'][$action]['add']=array(
	        $data
	    );
	    return $this->curlSend($link,$notes);
	    
	} 

	public function checkContact($user) {
		if(!is_array($user)) return 0;

	} 

	public function contact() {
		$link='https://'.$this->subdomain.".amocrm.ru/private/api/v2/json/contacts?type=json";
		return $this->curlSend($link);
	} 

	public function __call($method,$data) {
		$link='https://'.$this->subdomain.".amocrm.ru/private/api/v2/json/$action/set?type=json";
	}

}
