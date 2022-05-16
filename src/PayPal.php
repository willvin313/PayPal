<?php
/**
 * PayPal gateway class
 *
 * @author willvin
 * @copyright MIT
 * @version 0.0.2
 * @since 2022-05-15
 */

namespace willvin\PayPal;

/**
 *
 * Communication protocol version
 * @var double
 */
define('VERSION', '0.0.2');

/**
 *
 * PayPal class to PPL system
 * @param string $function Method name
 * @param array $ARG POST parameters
 * $client_id, $secret, $testMode = false
 * @return true
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
	 * Created order ID. 
	 * @var string
	 */
	public $orderID = '';

	/**
	 * Created payment link. Contains the link to redirect the user for payment.
	 * @var string 
	 */
	private $payLink = '';

	/**
	 * Object constructor. Set initial parameters. https://developer.paypal.com/api/rest/authentication/
	 * @param string $client_id
	 * @param string $secret
	 * @param bool $testMode
	 * @return true
	 */
	public function __construct($client_id="", $secret="", $testMode = false)
	{
		$this->client_id = (string)trim($client_id);
		$this->secret = (string)trim($secret);
		$this->testMode = (bool)$testMode;

		$this->orderRequestGUID = $this->GuidV4();

		return true;
	}

	/**
	 * Config initializer. Set initial parameters. https://developer.paypal.com/api/rest/authentication/
	 * @param array $config ["client_id" => CLIENT_ID, "secret" => SECRET, "testMode" => true]
	 * @return true
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
			throw new \Exception($result->error.". ".$result->error_description);
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
			throw new \Exception($result->error.". ".$result->error_description);
		}

		return $return;
	}

	/**
	 * Get PayPal merchant info.
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
			throw new \Exception($result->name.". ".$result->message);
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
			throw new \Exception($result->error.". ".$result->error_description);
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
			$this->payLink = $result->links[1]->href;
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "Order created successfully.";
		} else {
			throw new \Exception($result->details[0]->issue." ".$result->details[0]->description);
		}

		return $return;
	}

	/**
	 * Use this function to get the paypal approval link after the order has been created. It is optional.
	 * @return string PayPal link for approval
	 */
	public function GetApprovalLink()
	{
		return $this->payLink;
	}

	/**
	 * Use this function to reset the class variables, apart from client ID and client secret.
	 * @return void
	 */
	public function Clear()
	{
		$this->postData = "";
		$this->orderID = "";
		$this->payLink = "";
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
			throw new \Exception($result->details[0]->issue." ".$result->details[0]->description);
		}

		return $return;
		
	}

	/**
	 * Update PayPal order details. Read more here https://developer.paypal.com/docs/api/orders/v2/#orders_patch
	 * @return (bool|null|string|object)[]
	 * TODO: Work on updating orders.
	 */
	private function UpdateOrder() // Patch request // v2/checkout/orders/:order_id
	{
		
	}

	/**
	 * Authorize PayPal payment order.
	 * @return (bool|null|string|object)[]
	 * TODO: Work on Authorize Payment Order.
	 */
	Private function AuthorizePaymentOrder()
	{ 
	}
	
	/**
	 * Capture payment order after user approve the order.
	 * @param bool $test Optional, used for PHPUnit Test.
	 * @param mixed $data Optional, used for PHPUnit Test.
	 * @return array
	 */
	public function CapturePaymentOrder($test=false, $data=null)
	{
		$return = ["success" => false, "data" => null, "msg" => null];
		$headers = [
			'Content-Type: application/json',
			'Prefer: return=representation',
			'PayPal-Request-Id: '.$this->orderRequestGUID
		];
		$result = null;

		if (!$test) {
			$result = $this->MakeRequest('v2/checkout/orders/'.$this->orderID.'/capture', null, $headers, "POST");
		} else {
			$result = json_decode($data);
		}

		if (isset($result->status) && $result->status == 'COMPLETED') {
			$return["success"] = true;
			$return["data"] = $result;
			$return["msg"] = "Order created successfully.";
		} else {
			throw new \Exception($result->details[0]->issue." ".$result->details[0]->description);
		}

		return $return;
	}

	/**
	 * Set the post data you are sending to PayPal. Set parameters
	 * @param (array|object|json) $postData
	 * @return void
	 */
	public function SetPostData($postData)
	{
		if (is_array($postData)) {
			$this->postData = $postData;
		} else if(is_object($postData)) {
			$this->postData = $this->object_to_array($postData);
		} else {
			if ($this->is_json($postData)) {
				$this->postData = json_decode($postData, true);
			} else {
				throw new \Exception("The string parsed, is not a valid json string.");
			}
			
			
		}
	}

	/**
	 * Convert object to array.
	 * @param object $object 
	 * @return array
	 */
	private function object_to_array($object)
	{
		if (is_array($object) || is_object($object))
		{
			$result = [];
			foreach ($object as $key => $value)
			{
				$result[$key] = (is_array($value) || is_object($value)) ? $this->object_to_array($value) : $value;
			}
			return $result;
		}
		return $object;
	}

	/**
	 * Check if a string is a valid json data.
	 * @param mixed $string
	 * @return bool
	 */
	private function is_json($string) {
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * @return array Get the data to be sent to the PayPal API
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
	 * Generate a version 4 GUID string.
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
	 * Function checks if client_id & client secret is set.
	 * @return bool
	 */
	public function is_set(): bool
	{
		if (!empty($this->client_id) && !empty($this->secret)) {
			return true;
		} else {
			return false;
		}
	}

	/** 
	 * Make request to the paypal REST ApI.
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
		} catch(\Exception $error) {
			throw new \Exception($error->getMessage());
		}
	}
}