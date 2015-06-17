<?php
/* 
SimplePay API PHP example (for PHP 5)
https://simplepay.pro/
*/

/*
Для корректной работы требуются расширения PHP:
simplexml
curl - в случае использования совершения платежа в режиме прямого взаимодействия
*/

// URL для совершения платежа в обычном режиме
define("SP_Payment_URL","http://api.simplepay.pro/sp/payment");
define("SP_Payment_URL_Secure","https://api.simplepay.pro/sp/payment");

// URL для совершения платежа в режиме прямого взаимодействия
define("SP_Payment_URL_Direct","http://api.simplepay.pro/sp/init_payment");
define("SP_Payment_URL_Direct_Secure","https://api.simplepay.pro/sp/init_payment");

// URL для совершения рекуррентного платежа
define("SP_Recurring_Payment_URL","http://api.simplepay.pro/sp/make_recurring_payment");
define("SP_Recurring_Payment_URL_Secure","https://api.simplepay.pro/sp/make_recurring_payment");

/* интерфейсный класс инициализации платежа */
class SimplePay_Payment{
	public 
		$amount,						// сумма платежа
		$client_name,						// имя плательщика
		$client_email,						// e-mail плательщика
		$client_phone,						// телефон плательщика
		$client_ip,						// IP плательщика (нужно передавать для прямой переадресации на страницу ПС)
		$description,						// описание покупки
		$order_id,						// ID заказа в системе мерчанта
		$recurrent_start = false,				// Если нужно инициализировать рекуррентный профиль - поставить true
		$lifetime = 86400,					// Срок действия транзакции - сутки
		$user_validation_code = NULL,				// Код SMS-подтверждения, отправленного пользователю (если проверка номера телефона происходит на стороне ТСП)
		$user_validation_date = NULL,				// Дата отправки кода SMS-подтверждения ю (если проверка номера телефона происходит на стороне ТСП)
		$user_params = NULL;
	public 
		$payment_system = 'TEST';				// ID платежной системы (по умолчению - тестовая система)
}

/* Клиентский класс торговой точки для проведения платежей через SimplePay */ 

class SimplePay{
	private
		$outlet_id = 'ID торговой точки',			// ID торговой точки в системе SimplePay
		$secret_key = 'ВАШ КЛЮЧ',				// секретный ключ торговой точки
		$result_script_name = 'result.php'; 			// имя файла (basename) от Result URL на сервере мерчанта (result.php для http://yoursite.ru/sp/result.php)
			
	public 
		$test_mode = false;					// режим тестовых платежей
	
	public function process_success($order_id, $details){
		// ваш обработчик успешного зачисления средств	
		//echo "Order ID $order_id success";
	}
	
	public function process_fail($order_id, $details){
		// ваш обработчик при отказе в транзакции
		//echo "Order ID $order_id failed";
	}
	
	// запрос на совершение платежа с переадресацией клиента на ПС
	function init_payment(SimplePay_Payment $payment){
		
		$arrReq = array();
		
		/* Обязательные параметры */
		$arrReq['sp_outlet_id'] = $this->outlet_id;		// Идентификатор магазина
		
		$arrReq['sp_order_id']    	= $payment->order_id;		// Идентификатор заказа в системе магазина
		$arrReq['sp_amount']      	= $payment->amount;		// Сумма заказа
		$arrReq['sp_lifetime']    	= $payment->lifetime;		// Время жизни счёта (в секундах)
		$arrReq['sp_description'] 	= $payment->description; 	// Описание заказа (показывается в Платёжной системе)
		$arrReq['sp_user_name'] 	= $payment->client_name;
		$arrReq['sp_user_contact_email'] = $payment->client_email;
		$arrReq['sp_user_params'] 	= $payment->$user_params;
		
		// если надо инициализировать рекурентный профиль
		if($payment->recurrent_start)
		$arrReq['sp_recurring_start'] = 1; 			// Инициализовать рекуррентный профиль
		
		// Название ПС из справочника ПС. Задаётся, если не требуется выбор ПС. Если не задано, выбор будет предложен пользователю на сайте simplepay.pro
		$arrReq['sp_payment_system'] = $payment->payment_system;
		
		// Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
		$arrReq['sp_salt'] = rand(21,43433);
		
		// убираем пустые элементы
		$arrReq = array_filter($arrReq);
		
		// подписываем запрос
		$arrReq['sp_sig'] = sp_Signature::make('payment', $arrReq, $this->secret_key);
		$query = http_build_query($arrReq);
		
		// перенаправляем пользователя
		header("Location: ".SP_Payment_URL_Secure."?$query");
		exit;
	}
	
	// запрос на совершение платежа с получением ссылки для переадресации
	function init_payment_direct(SimplePay_Payment $payment){
		$arrReq = array();
		
		/* Обязательные параметры */
		$arrReq['sp_outlet_id'] = $this->outlet_id;		// Идентификатор магазина
		$arrReq['sp_order_id']    = $payment->order_id;		// Идентификатор заказа в системе магазина
		$arrReq['sp_amount']      = $payment->amount;		// Сумма заказа
		$arrReq['sp_lifetime']    = $payment->lifetime;		// Время жизни счёта (в секундах)
		$arrReq['sp_description'] = $payment->description; 	// Описание заказа (показывается в Платёжной системе)
		$arrReq['sp_user_phone'] = $payment->client_phone;
		$arrReq['sp_user_ip'] = $payment->client_ip;
		$arrReq['sp_user_name'] = $payment->client_name;
		$arrReq['sp_user_contact_email'] = $payment->client_email;
		
		// если ТСП осуществляет валидацию номера телефона на своей стороне, 
		// он должен передать код SMS-подтверждения, который был отправлен клиенту, а также дату подтверждения кода.
		if(!empty($payment->user_validation_date))
			$arrReq['sp_user_validation_date'] = $payment->user_validation_date;
		if(!empty($payment->user_validation_code))
			$arrReq['sp_user_validation_code'] = $payment->user_validation_code;
		
		// Стартовать рекуррентный профиль или нет?
		if($payment->recurrent_start)
		$arrReq['sp_recurring_start'] = 1; 			// Инициализовать рекуррентный профиль

		// Название ПС из справочника ПС. Задаётся, если не требуется выбор ПС. Если не задано, выбор будет предложен пользователю на сайте simplepay.pro
		$arrReq['sp_payment_system'] = $payment->payment_system;
		
		// Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
		$arrReq['sp_salt'] = rand(21,43433);
		
		// убираем пустые элементы
		$arrReq = array_filter($arrReq);
		
		$arrReq['sp_sig'] = sp_Signature::make('init_payment', $arrReq, $this->secret_key);
		$query = http_build_query($arrReq);
		
		// Получим ответ в XML
		$answer = $this->curl_get(SP_Payment_URL_Direct_Secure."?".$query);
		$unpack = $this->unpack_xml($answer);
		
		// вернем распакованный ответ
		return $unpack;
	}
	
	// запрос на совершение платежа по рекуррентному профилю
	function make_recurring_payment(SimplePay_Payment $payment, $recurring_profile_id){
		
		/* Обязательные параметры */
		$arrReq['sp_recurring_profile'] = abs(intval($recurring_profile_id));	// ID профиля рекурентов
		$arrReq['sp_outlet_id'] = $this->outlet_id;		// Идентификатор магазина
		$arrReq['sp_order_id']    = $payment->order_id;		// Идентификатор заказа в системе магазина
		$arrReq['sp_amount']      = $payment->amount;		// Сумма заказа
		$arrReq['sp_lifetime']    = $payment->lifetime;		// Время жизни счёта (в секундах)
		$arrReq['sp_description'] = $payment->description; 	// Описание заказа (показывается в Платёжной системе)
		
		// Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
		$arrReq['sp_salt'] = rand(21,43433);
		
		// убираем пустые элементы
		$arrReq = array_filter($arrReq);
		
		$arrReq['sp_sig'] = sp_Signature::make('make_recurring_payment', $arrReq, $this->secret_key);
		$query = http_build_query($arrReq);
		
		//echo "To: ".SP_Recurring_Payment_URL_Secure."?".$query;
		// Получим ответ в XML
		$answer = $this->curl_get(SP_Recurring_Payment_URL_Secure."?".$query);
			
		$unpack = $this->unpack_xml($answer);
		// вернем распакованный ответ
		return $unpack;
	}
	
	// запрос статуса платежа по ID платежа в SimplePay
	function get_payment_status_by_transaction_id($payment_id){
		// Обязательные параметры
		$arrReq['sp_merchant_id'] = $this->merchant_id;		// Идентификатор магазина
		$arrReq['sp_outlet_id'] = $this->outlet_id;		// Идентификатор магазина
		$arrReq['sp_payment_id']    = $payment_id;		// Идентификатор заказа в системе SP
		
		// Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
		$arrReq['sp_salt'] = rand(21,43433);
		
		// убираем пустые элементы
		$arrReq = array_filter($arrReq);
		
		$arrReq['sp_sig'] = sp_Signature::make('get_status', $arrReq, $this->secret_key);
		$query = http_build_query($arrReq);
		
		// получаем ответ в XML
		$answer = $this->curl_get('https://api.simplepay.pro/sp/get_status?'.$query);
		$unpack = $this->unpack_xml($answer);
		// вернем распакованный ответ
		return $unpack;
	}
	
	// запрос статуса платежа по ID в системе мерчанта
	// ВНИМАНИЕ! Мерчант сам должен следить за уникальностью этого номера, SimplePay вернет данные по последнему заказу с таким номером
	function get_payment_status_by_order_id($order_id){
		// Обязательные параметры
		$arrReq['sp_outlet_id'] = $this->outlet_id;		// Идентификатор магазина
		$arrReq['sp_order_id']    = $order_id;			// Идентификатор заказа в системе SP
		
		// Параметры безопасности сообщения. Необходима генерация sp_salt и подписи сообщения.
		$arrReq['sp_salt'] = rand(21,43433);
		
		// убираем пустые элементы
		$arrReq = array_filter($arrReq);
		
		$arrReq['sp_sig'] = sp_Signature::make('get_status', $arrReq, $this->secret_key);
		$query = http_build_query($arrReq);
		
		// получаем ответ в XML
		$answer = $this->curl_get('https://api.simplepay.pro/sp/get_status?'.$query);
		
		$unpack = $this->unpack_xml($answer);
		// вернем распакованный ответ
		return $unpack;
	}
	
	// обработка уведомления от сервера SimplePay
	// автоматически распознает метод отправки запроса
	function process_request_result(){	
		
		$REQUEST_PARAMS  = array();
		
		// 1. нужно распознать, является ли запрос корректным результатом SimplePay и определить тип взаимодействия
		
		// XML
		if(!empty($_POST['sp_xml'])){
			$xml = $_POST['sp_xml'];
			// надо распарсить XML в массив	
			$array = $this->unpack_xml($xml);
			
			$REQUEST_PARAMS = $array;
		}
		else if(!empty($_GET['sp_sig'])) $REQUEST_PARAMS = $_GET;
		else if(!empty($_POST['sp_sig'])) $REQUEST_PARAMS = $_POST;
		else die("Некорректные параметры запроса");
			
			// проверяем подпись
			if ( !sp_Signature::check($REQUEST_PARAMS['sp_sig'], $this->result_script_name, $REQUEST_PARAMS, $this->secret_key) )
				die("Некорректная подпись запроса");
				
			$order_id = $REQUEST_PARAMS['sp_order_id'];
			if ( $REQUEST_PARAMS['sp_result'] == 1 ) {
				// обрабатываем случай успешной оплаты заказа с номером $order_id
				$this->process_success($order_id, $REQUEST_PARAMS);
			}
			else {
				// заказ с номером $order_id не будет оплачен.
				$this->process_fail($order_id, $REQUEST_PARAMS);
			}
			
			// теперь нужно ответить SimplePay
			$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
			$xml->addChild('sp_salt', $REQUEST_PARAMS['sp_salt']);
			$xml->addChild('sp_status', 'ok');
			
			if($REQUEST_PARAMS['sp_result'] == 1)
			$xml->addChild('sp_description', "Оплата принята");
			else
			$xml->addChild('sp_description', "Платеж отменен");
			
			$xml->addChild('sp_sig', sp_Signature::makeXML($this->result_script_name, $xml, $this->secret_key));
			
			header('Content-type: text/xml');
			print $xml->asXML();
	}
	//
	// вспомогательные методы (utilities functions)
	//
	
	
	private function unpack_xml($request){
		$dom = new DOMDocument;
		$dom->loadXML($request);
		$parsed_xml = $s = simplexml_import_dom($dom);
		
		$as_array = (array) $parsed_xml;
		return array_filter($as_array);
	}

	private function curl_post($url, $params, $https = false){
		if( $curl = curl_init() ) {
			$query = http_build_query($params);
			if($https) curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
			$out = curl_exec($curl);
			curl_close($curl);
			return $out;
	  }
	  else return false;
	}

	private function curl_get($url, $ssl = true){
		if( $curl = curl_init() ) {
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			$out = curl_exec($curl);
			curl_close($curl);
			return $out;
	  }
	  else return false;
	}

}

/* Класс для формирования и проверки контрольной подписи запросов */

class SP_Signature {

	/**
	 * Get script name from URL (for use as parameter in self::make, self::check, etc.)
	 *
	 * @param string $url
	 * @return string
	 */
	public static function getScriptNameFromUrl ( $url )
	{
		$path = parse_url($url, PHP_URL_PATH);
		$len  = strlen($path);
		if ( $len == 0  ||  '/' == $path{$len-1} ) {
			return "";
		}
		return basename($path);
	}
	
	/**
	 * Get name of currently executed script (need to check signature of incoming message using self::check)
	 *
	 * @return string
	 */
	public static function getOurScriptName ()
	{
		return self::getScriptNameFromUrl( $_SERVER['PHP_SELF'] );
	}

	/**
	 * Creates a signature
	 *
	 * @param array $arrParams  associative array of parameters for the signature
	 * @param string $strSecretKey
	 * @return string
	 */
	public static function make ( $strScriptName, $arrParams, $strSecretKey )
	{
		$arrFlatParams = self::makeFlatParamsArray($arrParams);
		return md5( self::makeSigStr($strScriptName, $arrFlatParams, $strSecretKey) );
	}

	/**
	 * Verifies the signature
	 *
	 * @param string $signature
	 * @param array $arrParams  associative array of parameters for the signature
	 * @param string $strSecretKey
	 * @return bool
	 */
	public static function check ( $signature, $strScriptName, $arrParams, $strSecretKey )
	{
		return (string)$signature === self::make($strScriptName, $arrParams, $strSecretKey);
	}


	/**
	 * Returns a string, a hash of which coincide with the result of the make() method.
	 * WARNING: This method can be used only for debugging purposes!
	 *
	 * @param array $arrParams  associative array of parameters for the signature
	 * @param string $strSecretKey
	 * @return string
	 */
	static function debug_only_SigStr ( $strScriptName, $arrParams, $strSecretKey ) {
		return self::makeSigStr($strScriptName, $arrParams, $strSecretKey);
	}


	private static function makeSigStr ( $strScriptName, array $arrParams, $strSecretKey ) {
		unset($arrParams['sp_sig']);
		
		ksort($arrParams);

		array_unshift($arrParams, $strScriptName);
		array_push   ($arrParams, $strSecretKey);

		return join(';', $arrParams);
	}
	
	private static function makeFlatParamsArray ( $arrParams, $parent_name = '' )
	{
		$arrFlatParams = array();
		$i = 0;
		foreach ( $arrParams as $key => $val ) {
			
			$i++;
			if ( 'sp_sig' == $key )
				continue;
				
			/**
			 * Имя делаем вида tag001subtag001
			 * Чтобы можно было потом нормально отсортировать и вложенные узлы не запутались при сортировке
			 */
			$name = $parent_name . $key . sprintf('%03d', $i);

			if (is_array($val) ) {
				$arrFlatParams = array_merge($arrFlatParams, self::makeFlatParamsArray($val, $name));
				continue;
			}

			$arrFlatParams += array($name => (string)$val);
		}

		return $arrFlatParams;
	}

	/********************** singing XML ***********************/
	public static function makeXML ( $strScriptName, $xml, $strSecretKey )
	{
		$arrFlatParams = self::makeFlatParamsXML($xml);
		return self::make($strScriptName, $arrFlatParams, $strSecretKey);
	}

	/**
	 * Verifies the signature of XML
	 */
	public static function checkXML ( $strScriptName, $xml, $strSecretKey )
	{
		if ( ! $xml instanceof SimpleXMLElement ) {
			$xml = new SimpleXMLElement($xml);
		}
		$arrFlatParams = self::makeFlatParamsXML($xml);
		return self::check((string)$xml->sp_sig, $strScriptName, $arrFlatParams, $strSecretKey);
	}

	/**
	 * Returns a string, a hash of which coincide with the result of the makeXML() method.
	 * WARNING: This method can be used only for debugging purposes!
	 */
	public static function debug_only_SigStrXML ( $strScriptName, $xml, $strSecretKey )
	{
		$arrFlatParams = self::makeFlatParamsXML($xml);
		return self::makeSigStr($strScriptName, $arrFlatParams, $strSecretKey);
	}

	/**
	 * Returns flat array of XML params
	 */
	private static function makeFlatParamsXML ( $xml, $parent_name = '' )
	{
		if ( ! $xml instanceof SimpleXMLElement ) {
			$xml = new SimpleXMLElement($xml);
		}

		$arrParams = array();
		$i = 0;
		foreach ( $xml->children() as $tag ) {
			
			$i++;
			if ( 'sp_sig' == $tag->getName() )
				continue;
				
			/**
			 * Имя делаем вида tag001subtag001
			 * Чтобы можно было потом нормально отсортировать и вложенные узлы не запутались при сортировке
			 */
			$name = $parent_name . $tag->getName().sprintf('%03d', $i);

			if ( $tag->children()->count() > 0 ) {
				$arrParams = array_merge($arrParams, self::makeFlatParamsXML($tag, $name));
				continue;
			}

			$arrParams += array($name => (string)$tag);
		}

		return $arrParams;
	}
}

?>
