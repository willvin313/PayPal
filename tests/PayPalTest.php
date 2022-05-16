<?php
declare(strict_types=1);

use willvin\PayPal\PayPal;
use PHPUnit\Framework\TestCase;

class PayPalTest extends TestCase
{
    const CLIENT_ID = "";// Set you paypal merchant client ID here, to run test.
    const SECRET = ""; // Set you paypal merchant secret here, to run test.

    private $order =  '{
        "purchase_units": [
            {
                "items": [
                    {
                        "name": "T-Shirt",
                        "description": "Green XL",
                        "quantity": "1",
                        "unit_amount": {
                            "currency_code": "USD",
                            "value": "100.00"
                        }
                    }
                ],
                "amount": {
                    "currency_code": "USD",
                    "value": "100.00",
                    "breakdown": {
                        "item_total": {
                            "currency_code": "USD",
                            "value": "100.00"
                        }
                    }
                }
            }
        ],
        "application_context": {
            "return_url": "https://example.com/return",
            "cancel_url": "https://example.com/cancel"
        }
    }'; // Order data

    private $orderCapture =  '{
        "id": "97Y953627T008845P",
        "intent": "CAPTURE",
        "status": "COMPLETED",
        "purchase_units": [
            {
                "reference_id": "default",
                "amount": {
                    "currency_code": "USD",
                    "value": "100.00",
                    "breakdown": {
                        "item_total": {
                            "currency_code": "USD",
                            "value": "100.00"
                        },
                        "shipping": {
                            "currency_code": "USD",
                            "value": "0.00"
                        },
                        "handling": {
                            "currency_code": "USD",
                            "value": "0.00"
                        },
                        "insurance": {
                            "currency_code": "USD",
                            "value": "0.00"
                        },
                        "shipping_discount": {
                            "currency_code": "USD",
                            "value": "0.00"
                        }
                    }
                },
                "payee": {
                    "email_address": "john_merchant@example.com",
                    "merchant_id": "C7CYMKZDG8D6E"
                },
                "description": "T-Shirt",
                "items": [
                    {
                        "name": "T-Shirt",
                        "unit_amount": {
                            "currency_code": "USD",
                            "value": "100.00"
                        },
                        "tax": {
                            "currency_code": "USD",
                            "value": "0.00"
                        },
                        "quantity": "1",
                        "description": "Green XL"
                    }
                ],
                "shipping": {
                    "name": {
                        "full_name": "John Doe"
                    },
                    "address": {
                        "address_line_1": "1 Main St",
                        "admin_area_2": "San Jose",
                        "admin_area_1": "CA",
                        "postal_code": "95131",
                        "country_code": "US"
                    }
                },
                "payments": {
                    "captures": [
                        {
                            "id": "31H931502U998360B",
                            "status": "COMPLETED",
                            "amount": {
                                "currency_code": "USD",
                                "value": "100.00"
                            },
                            "final_capture": true,
                            "seller_protection": {
                                "status": "ELIGIBLE",
                                "dispute_categories": [
                                    "ITEM_NOT_RECEIVED",
                                    "UNAUTHORIZED_TRANSACTION"
                                ]
                            },
                            "seller_receivable_breakdown": {
                                "gross_amount": {
                                    "currency_code": "USD",
                                    "value": "100.00"
                                },
                                "paypal_fee": {
                                    "currency_code": "USD",
                                    "value": "3.98"
                                },
                                "net_amount": {
                                    "currency_code": "USD",
                                    "value": "96.02"
                                }
                            },
                            "links": [
                                {
                                    "href": "https://api.sandbox.paypal.com/v2/payments/captures/31H931502U998360B",
                                    "rel": "self",
                                    "method": "GET"
                                },
                                {
                                    "href": "https://api.sandbox.paypal.com/v2/payments/captures/31H931502U998360B/refund",
                                    "rel": "refund",
                                    "method": "POST"
                                },
                                {
                                    "href": "https://api.sandbox.paypal.com/v2/checkout/orders/97Y953627T008845P",
                                    "rel": "up",
                                    "method": "GET"
                                }
                            ],
                            "create_time": "2022-05-16T21:09:31Z",
                            "update_time": "2022-05-16T21:09:31Z"
                        }
                    ]
                }
            }
        ],
        "payer": {
            "name": {
                "given_name": "John",
                "surname": "Doe"
            },
            "email_address": "sb-bej4m7008058@personal.example.com",
            "payer_id": "87HA637EEKCEW",
            "address": {
                "address_line_1": "1 Main St",
                "admin_area_2": "San Jose",
                "admin_area_1": "CA",
                "postal_code": "95131",
                "country_code": "US"
            }
        },
        "create_time": "2022-05-16T20:45:50Z",
        "update_time": "2022-05-16T21:09:31Z",
        "links": [
            {
                "href": "https://api.sandbox.paypal.com/v2/checkout/orders/97Y953627T008845P",
                "rel": "self",
                "method": "GET"
            }
        ]
    }'; // Order capture data

    public function testInit()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $this->assertTrue($paypal->is_set());
        $this->assertClassHasAttribute('client_id', PayPal::class);
        $this->assertClassHasAttribute('secret', PayPal::class);
    }
    
    public function testInit2()
    {
        $paypal = new PayPal();
        $paypal->Initialize(["client_id" => $this::CLIENT_ID, "secret" => $this::SECRET, "testMode" => true]); 
        $this->assertTrue($paypal->is_set());
    }

    public function testGenerateAccessToken(){
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $paypal->GenerateAccessToken();
        $this->assertTrue(!empty($paypal->access_token));
    }

    public function testGetMerchantInfo()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $paypal->GenerateAccessToken();
        $user_info = $paypal->UserInfo()["data"];
        $this->assertStringContainsStringIgnoringCase('https://www.paypal.com/webapps/auth/identity/user/', $user_info->user_id);
    }

    public function testGenerateClientToken()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $paypal->GenerateAccessToken();
        $client_token = $paypal->generateClientToken(time())["data"];
        $this->assertTrue(!empty($client_token->client_token));
    }

    public function testSetGetPostData()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $paypal->SetPostData($this->order);
        $post_data = $paypal->GetPostData(); // Get post data as an array.
        $this->assertEquals(json_encode($post_data), json_encode(json_decode($this->order)));
    }

    public function testAddValue()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $paypal->SetPostData($this->order);
        $paypal->AddValue("intent", "CAPTURE"); // Add a value to the postData array.
        $post_data = $paypal->GetPostData(); // Get post data as an array.
        $this->assertEquals($post_data['intent'], "CAPTURE");
    }

    public function testCreateOrder()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $paypal->GenerateAccessToken();
        $paypal->SetPostData($this->order);
        $paypal->AddValue("intent", "CAPTURE"); // Add a value to the postData array.
        $create_order = $paypal->CreateOrder()["data"]; // Create order for client to make payment. And redirect client to payment page after this.
        $this->assertTrue(!empty($create_order->id));
        $this->assertEquals($create_order->id, $paypal->orderID);
        $this->assertContains($create_order->status, ["CREATED", "APPROVED"]);
        $this->assertEquals($create_order->links[1]->href, $paypal->GetApprovalLink());
        $this->assertEquals($create_order->links[1]->rel, "approve");
    }

    public function testCapturePaymentOrder()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $capture_order = $paypal->CapturePaymentOrder(true, $this->orderCapture)["data"]; // Create order for client to make payment. And redirect client to payment page after this.
        $this->assertTrue(!empty($capture_order->id));
        $this->assertEquals($capture_order->status, "COMPLETED");
        $this->assertEquals($capture_order, json_decode($this->orderCapture));
    }

    public function testTerminateAccessToken()
    {
        $paypal = new PayPal($this::CLIENT_ID, $this::SECRET, true);
        $terminate_token = $paypal->CapturePaymentOrder(true, $this->orderCapture)["success"]; // Create order for client to make payment. And redirect client to payment page after this.
        $this->assertTrue($terminate_token);
    }
}