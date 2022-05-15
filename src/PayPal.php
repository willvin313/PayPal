<?php
/**
 * PayPal gateway class
 *
 * @author willvin
 * @copyright MIT
 * @version 0.1
 * @since 2022-05-15
 */

namespace willvin\PayPal;

/**
 *
 * Communication protol version
 * @var double
 */
define('PPL_VERSION', '0.1');

/**
 *
 * PayPal class to PPL system
 * @param string $function Method name
 * @param array $ARG POST parameters
 * $merchantId, $posId, $salt, $testMode = false
 * @return array array(INT Error code, ARRAY Result)
 */
class PayPal
{
	/**
	 * Live system URL address
	 * @var string
	 */
	private static $Live = 'https://api-m.paypal.com/';

	/**
	 * Sandbox system URL address
	 * @var string
	 */
	private static $Sandbox = 'https://api-m.sandbox.paypal.com/';

	/**
	 * Use Live (false) or Sandbox (true) environment
	 * @var bool
	 */
	private $testMode = false;

	/**
	 * Access token from paypal
	 * @var string
	 */
	public $client_token = '';

	/**
	 * Access token from paypal
	 * @var string
	 */
	public $access_token = '';

	/**
	 * Token type from paypal
	 * @var string
	 */
	private $token_type = '';

	/**
	 * PayPal merchant client ID
	 * @var string
	 */
	private $client_id = '';

	/**
	 * PayPal merchant client secret
	 * @var string
	 */
	private $secret = '';

	/**
	 * Array of POST data
	 * @var array
	 */
	private $postData = array();

	/**
	 * Request GUID version 4
	 * @var array
	 */
	public $orderRequestGUID = '';

	/**
	 * Created order ID
	 * @var string
	 */
	public $orderID = '';

	/**
	 * Obcject constructor. Set initial parameters. https://developer.paypal.com/api/rest/authentication/
	 * @param string $client_id
	 * @param string $secret
	 * @param bool $testMode
	 * @return bool
	 */
	public function __construct($client_id="", $secret="", $testMode = false)
	{
		$this->client_id = (string)trim($client_id);
		$this->secret = (string)trim($secret);
		$this->testMode = (bool)$testMode;

		$this->orderRequestGUID = $this->GuidV4();

		return $this;
	}

	/**
	 * Config initializer. Set initial parameters. https://developer.paypal.com/api/rest/authentication/
	 * @param array $config
	 * @return bool
	 */
	public function Initialize($config)
	{
		$this->client_id = (string) trim($config['client_id']);
		$this->secret = (string) trim($config['secret']);
		$this->testMode = (bool)$config['testMode'];

		$this->orderRequestGUID = $this->GuidV4();

		return true;
	}

	/**
	 * Generate PayPal Access token. Set initial parameters. https://developer.paypal.com/api/rest/authentication/
	 * @return (bool|null|string)[]
	 */
	public function GenerateAccessToken()
	{
		$return = ["success" => false, "data" => null, "msg" => null];

		$data = [
			'grant_type' => 'client_credentials',
			'ignoreCache' => true,
			'return_authn_schemes' => true,
			'return_client_metadata' => true,
			'return_unconsented_scopes' => true
		];
		$headers=['Content-Type: application/x-www-form-urlencoded'];

		$result = $this->MakeRequest('v1/oauth2/token', http_build_query($data), $headers, "POST");

		if (isset($result->access_token)) {
			$this->access_token = $result->access_token;
			$this->token_type = $result->token_type;
			$return["success"] = true;
			$return["msg"] = "Access token gotten successfully.";
		} else {
			throw new Exception($result->error.". ".$result->error_description);
		}

		return $return;
	}

	/**
	 * Terminate PayPal Access token.
	 * @return (bool|null|string)[]
	 */
	public function TerminateAccessToken()
	{
		$return = ["success" => false, "data" => null, "msg" => null];

		$data = [
			'token' => $this->access_token,
			'token_type_hint' => "ACCESS_TOKEN"
		];
		$headers=['Content-Type: application/x-www-form-urlencoded'];

		$result = $this->MakeRequest('v1/oauth2/token/terminate', http_build_query($data), $headers, "POST");

		if (!isset($result->error) && !isset($result->error_description)) {
			$this->access_token = '';
			$this->token_type = '';
			$return["success"] = true;
			$return["msg"] = "Access token terminated successfully.";
		} else {
			throw new Exception($result->error.". ".$result->error_description);
		}

		return $return;
	}

	/**
	 * Get PayPal User info.
	 * @return (bool|null|string|object)[]
	 */
	public function UserInfo()
	{
		$return = ["success" => false, "data" => null, "msg" => null];

		$result = $this->MakeRequest('v1/identity/oauth2/userinfo?schema=paypalv1.1'); 

		if (isset($result->user_id)) {
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "User info gotten successfully.";
		} else {
			throw new Exception($result->name.". ".$result->message);
		}

		return $return;
	}

	/**
	 * Call this function to get client token.
	 * @param (int|string) $customer_id 
	 * @return (bool|null|string|object)[]
	 * TODO: make the lib work with client token, this generateClientToken() is not being used for request currently.
	 */
	public function generateClientToken($customer_id)
	{
		$return = ["success" => false, "data" => null, "msg" => null];

		$data = [
			'customer_id' => $customer_id
		];
		$headers=['Content-Type: application/json'];

		$result = $this->MakeRequest('v1/identity/generate-token', $data, $headers, "POST");

		if (isset($result->client_token) || isset($result->id_token)) {
			$this->client_token = $result;
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "Client token gotten successfully.";
		} else {
			throw new Exception($result->error.". ".$result->error_description);
		}

		return $return;
	}

	/**
	 * Create PayPal order, Read more here https://developer.paypal.com/docs/api/orders/v2/#orders_create.
	 * @return (bool|null|string)[]
	 */
	public function CreateOrder()
	{
		$return = ["success" => false, "data" => null, "msg" => null];
		$headers = [
			'Content-Type: application/json',
			'Prefer: return=representation',
			'PayPal-Request-Id: '.$this->orderRequestGUID
		];

		$result = $this->MakeRequest('v2/checkout/orders', $this->postData, $headers, "POST");

		if (isset($result->status) && $result->status == 'CREATED' || isset($result->status) && $result->status == 'APPROVED') {
			$this->orderID = $result->id;
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "Order created successfully.";
		} else {
			throw new Exception($result->details->issue." ".$result->description);
		}

		return $return;
	}
	
	/**
	 * Get PayPal order details. Read more here https://developer.paypal.com/docs/api/orders/v2/#orders_get
	 * @return (bool|null|string|object)[]
	 */
	public function ShowOrderDetails()
	{
		$return = ["success" => false, "data" => null, "msg" => null];

		$result = $this->MakeRequest('v2/checkout/orders/'.$this->orderID); 

		if (isset($result->status) && $result->status == 'CREATED') {
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "Order details gotten successfully.";
		} else {
			throw new Exception($result->details->issue." ".$result->description);
		}

		return $return;
		
	}

	/**
	 * Update PayPal order details. Read more here https://developer.paypal.com/docs/api/orders/v2/#orders_patch
	 * @return (bool|null|string|object)[]
	 * TODO: Working on updating orders.
	 */
	private function UpdateOrder() // Patch request // v2/checkout/orders/:order_id
	{
		
	}

	/**
	 * Get PayPal order details.
	 * @return (bool|null|string|object)[]
	 * TODO: Work on Authorize Payment Order.
	 */
	Private function AuthorizePaymentOrder()
	{ 
	}

	public function CapturePaymentOrder()
	{
		$return = ["success" => false, "data" => null, "msg" => null];
		$headers = [
			'Content-Type: application/json',
			'Prefer: return=representation',
			'PayPal-Request-Id: '.$this->orderRequestGUID
		];

		$result = $this->MakeRequest('v2/checkout/orders/'.$this->orderID.'/capture', null, $headers, "POST");

		if (isset($result->status) && $result->status == 'COMPLETED') {
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "Order created successfully.";
		} else {
			throw new Exception($result->details->issue." ".$result->description);
		}

		return $return;
	}

	/**
	 * Set the post data you are sending to PayPal. Set parameters
	 * @param array $postData
	 * @return void
	 */
	public function SetPostData($postData)
	{
		$this->postData = $postData;
	}

	/**
	 * @return array list of data to be sent to the PayPal API
	 */
	public function GetPostData()
	{
		return $this->postData;
	}

	/**
	 * Add value to request
	 * @param string $name Argument name
	 * @param mixed $value Argument value
	 * @return void
	 */
	public function AddValue($name, $value)
	{
		$this->postData[$name] = $value;
	}

	/**
	 * Add value to request
	 * @param mixed $data 
	 * @return string
	 */
	private function GuidV4($data = null) {
		// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
		$data = $data ?? random_bytes(16);
		assert(strlen($data) == 16);
	
		// Set version to 0100
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		// Set bits 6-7 to 10
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
	
		// Output the 36 character UUID.
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/** 
	 * @param mixed $apiPath
	 * @param array|null $postData
	 * @param array|null $Header
	 * @param string $type
	 * @param bool $testConnection
	 * @return string|object|bool
	**/
	Private function MakeRequest($apiPath, $postData=null, $Header=null, $type="GET")
	{
		try {
			$url = ($this->testMode) ? $this::$Sandbox.$apiPath : $this::$Live.$apiPath;
			$headers = ($Header == null) ? [] : $Header;

			$curl = curl_init();
	
			$options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => $type,
			);
			
			if($apiPath == 'v1/oauth2/token'){
				$options[CURLOPT_USERPWD] = "$this->client_id:$this->secret";
			} else {
				if (strtolower($this->token_type) == "bearer") {
					$headers[] = "Authorization: Bearer ".$this->access_token;
				}
			}
			
			if($type == 'POST' || $type == 'PUT' || $type == 'PATCH'){
				if (is_array($postData) || is_object($postData)) {
					$options[CURLOPT_POSTFIELDS] = json_encode($postData);
				} else {
					if ($postData !== null) {
						$options[CURLOPT_POSTFIELDS] = $postData;
					}
				}
			}

			$options[CURLOPT_HTTPHEADER] = $headers;

			curl_setopt_array($curl, $options);
			$response = curl_exec($curl);
			curl_close($curl);
	
			return json_decode($response);
		} catch(Exception $error) {
			throw new Exception($error->getMessage());
		}
	}
}