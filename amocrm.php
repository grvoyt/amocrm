<?php
class Amocrm {
	private $auth;
	private $user = array();
	private $url;
	private $amocrm_map;
	private $contacts_map;
	private $voronka_id;
	private $tags;
	private $lead_id;
	private $debug = false;
	private $subdomain;

    /**
     * Amocrm constructor.
     * @param $login login
     * @param $hash hash
     * @param $subdomain subdomain
     * @param bool $debug  debug
     */
	public function __construct($login,$hash,$subdomain,$debug = false) {
        $this->debug = $debug;
		$this->user = array(
			'USER_LOGIN' => $login,
			'USER_HASH' => $hash
		);
        $this->url = 'https://'. $subdomain .'.amocrm.ru';
		$this->subdomain = $subdomain;
		if($this->debug) $this->log('Created params ==> ',[$this->user,$this->url] );
		return $this;
	}

	// информация об аккаунте
	public function getInfo() {
		$link = $this->url.'accounts/current?'.http_build_query($this->user);
		return file_get_contents($link);
	}

	public function getAccountInfo() {
	    $link = $this->url.'account';
	    var_dump($link);
	    return $this->curlSend($link);
    }

	// вывод информации об аккаунте
	public function getInfoPrint() {
		print_r($this->getInfo());
	}

	// установка кастомных полей сделки
	public function setMap($data) {
		$this->amocrm_map = $data;
	}

	// установка полей контактов
    public function setMapContacts($data) {
	    $this->contacts_map = $data;
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
		if($method == 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookie.txt');
		curl_setopt($curl, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookie.txt');
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

		$out = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $code=(int)$code;
        $errors=array(
            301=>'Moved permanently',
            400=>'Bad request',
            401=>'Unauthorized',
            403=>'Forbidden',
            404=>'Not found',
            500=>'Internal server error',
            502=>'Bad gateway',
            503=>'Service unavailable'
        );
        try
        {
            #Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
            if($code!=200 && $code!=204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error',$code);
            }

        }
        catch(Exception $E)
        {
            if($this->debug) $this->log($E->getMessage(),$out);
            $this->error($E->getMessage(),$out);
            die('Ошибка: '.$E->getMessage().PHP_EOL.'Код ошибки: '.$E->getCode());
        }
		curl_close($curl);
		return $out;
	}

	// подключение к амо
	public function auth() {
		$link = $this->url.'/private/api/auth.php?type=json';
		$response = $this->curlSend($link,$this->user);
		$authRes = json_decode($response,true);
		if($authRes['response']['error']) {
		    $this->error(__FUNCTION__,$authRes['response']['error']);
        }
		if($this->debug) $this->log('AUTH ==> ',$authRes);
		$this->auth = true;
		return $response;
	}

	// установка лида
	public function leadSet($data = array()) {
        $leads = [];
        $leads['request']['leads']['add'] = array(
			array(
				'name' => $data['lead_name'],
				"created_at" => time(),
			)
		);
		if( $this->voronka_id ) {
            $leads['request']['leads']['add'][0]['status_id'] = $this->voronka_id;
		}

		if( $this->amocrm_map ) {
            $custom_fields = [];
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
            $this->log('map',$custom_fields);
            $leads['request']['leads']['add'][0]['custom_fields'] = $custom_fields;
		} else {
            $leads['request']['leads']['add'][0]['custom_fields'] = array();
		}

		if( $this->tags ) {
            $leads['request']['leads']['add'][0]['tags'] = $this->tags;
		}

        if($this->debug) {
            $this->log('Lead data ==> ',$leads);
        }
		$link = $this->url.'/api/v2/leads/set';
		$res = $this->curlSend($link,$leads); // отправка лида
		$jsonRes = json_decode( $res, true);
		if($this->debug) $this->log('answer server ==> ',$jsonRes);
		if(isset($jsonRes['response']['error'])) {
            $this->error(__FUNCTION__,$jsonRes['response']['error']);
            return 0;
        }
		$lead_id = $jsonRes['response']['leads']['add'][0]['id']; // получение id сделки
		$this->lead_id = $lead_id;
		return true;
	}

    // установка лида

    /**
     * @param $lead_id lead_id
     * @param $voronka_id voronka_id
     * @param $name Deal name
     * @return boolean
     */
    public function leadUpdateStatusId($lead_id,$voronka_id, $name = '') {
        $leads = [];
        $leads['update'] = [
            [
                'id' => $lead_id,
                "updated_at" => time(),
                "status_id" => $voronka_id
            ]
        ];

        if($name !== '' ) $leads['update'][0]['name'] = $name;

        if($this->debug) {
            $this->log('Lead data ==> ',$leads);
        }

        $link = $this->url.'/api/v2/leads';
        if($this->debug) $this->log('link',$link);
        $res = $this->curlSend($link,$leads); // отправка лида
        $jsonRes = json_decode( $res, true);
        if($this->debug) $this->log('answer server ==> ',$jsonRes);
        if(isset($jsonRes['response']['error']) || isset($jsonRes['response']['leads']['update']['errors'])) {
            $this->error(__FUNCTION__,$jsonRes['response']['error']);
            return false;
        }
        return true;
    }

	public function log($text,$data,$vardump = false) {
		if($vardump) {
		    print_r($text.PHP_EOL);
			var_dump($data);
		} else {
			print_r($text.PHP_EOL.json_encode($data));
		}
		print_r(PHP_EOL.PHP_EOL);
	}

	public function error($text,$message) {
        $dirname = dirname(__FILE__).DIRECTORY_SEPARATOR.'error.txt';
        $today = date("Y-m-d H:i:s");
        try {
            file_put_contents($dirname,$today.PHP_EOL.$text.PHP_EOL.$message.PHP_EOL,FILE_APPEND);
        } catch (Exception $e) {

        }
    }

	// установка контакта
	public function contactSet($data = array()) {
        $contacts = [];
		$custom_contact = [];
		if( !empty($data['phone']) ) {
			$custom_contact = array_merge($custom_contact, array(
				array(
					#Телефоны
					'id' => $this->contacts_map['phone'],
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
					'id' => $this->contacts_map['email'],
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
			'name'=> isset($data['name']) ? $data['name'] : '-', #Имя контакта
			//'last_modified'=>1298904164, //optional
			'linked_leads_id'=> $this->lead_id,
			//'company_name'=>'amoCRM', #Наименование компании
			//'tags' => 'Important, USA', #Теги
			'custom_fields' => $custom_contact,
		  )
		);

		$link = $this->url.'private/api/v2/json/contacts/set';
		return $this->curlSend($link,$contacts);
	}

	// функция добавления заметок или задачи
	public function amocrm_add_one($data=array(), $action='notes'){
	    $data['element_id'] = $this->lead_id;
	    $subdomain = $this->subdomain;
	    # Формируем ссылку для запроса
	    $link = $this->url.'private/api/v2/json/'.$action.'/set?type=json';

	    # Массив с параметрами, которые нужно передать методом POST к API системы
	    $notes['request'][$action]['add']=array(
	        $data
	    );
	    return $this->curlSend($link,$notes);
	    
	} 

}
