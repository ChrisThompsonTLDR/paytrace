<?php

namespace Christhompsontldr\Paytrace;

use GuzzleHttp\Client;
use Carbon\Carbon;

class Paytrace
{
    private $username, $password;

    private $transactionMap = [
        'username' => 'UN',
        'password' => 'PSWD',
        'terms' => 'TERMS',
        'method' => 'METHOD',
        'transactionType' => 'TRANXTYPE',
        'cc' => 'CC',
        'expirationMonth' => 'EXPMNTH',
        'expirationYear' => 'EXPYR',
        'price' => 'AMOUNT',
        'cvv' => 'CSC',
        'address' => 'BADDRESS',
        'zip' => 'BZIP',
        'firstBillingDate' => 'START',
        'numberOfBillingCycles' => 'TOTALCOUNT',
        'frequency' => 'FREQUENCY',
        'customerId' => 'CUSTID',
    ];

    private $customerMap = [
        'name' => 'BNAME',
        'id' => 'CUSTID',
        'cc' => 'CC',
        'expirationMonth' => 'EXPMNTH',
        'expirationYear' => 'EXPYR',
    ];

    /**
     * Response from Paytrace
     *
     * @var array
     */
    protected $response = [];

    /**
     * Was the previous transaction successful
     *
     * @var boolean
     */
    protected $success = false;

    /**
     * user friendly message
     *
     * @var string
     */
    protected $message = '';

    /**
     * transaction id from PayTrace
     *
     * @var integer
     */
    protected $transactionId = '';

    /**
     * customer id from PayTrace
     *
     * @var integer
     */
    protected $customerId = '';

    /**
     * subscription id from PayTrace
     *
     * @var mixed
     */
    protected $subscriptionId = '';

    //  SETTERS

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function createCustomer($params = [])
    {
        //  add the method
        $params['method'] = 'CreateCustomer';

        $values = [];

        //  map keys to Paytrace's
        foreach ($params as $key => $val) {
            if (isset($this->customerMap[$key])) {
                $values[] = $this->customerMap[$key] . '~' . $val;
            }
        }

        $this->process($params);

        return $this;
    }

    /**
     * Single transaction
     *
     * @param mixed $params
     */
    public function sale($params = [])
    {
        //  add the transaction type to the params
        $params['method']          = 'ProcessTranx';
        $params['transactionType'] = 'Sale';

        $this->process($params);

        return $this;
    }

    /**
     * Recurring transaction
     *
     * @param mixed $params
     */
    public function subscription($params = [])
    {
        //  add the transaction type to the params
        $params['method']          = 'createrecur';
        $params['transactionType'] = 'Sale';

        //  build customer if needed
        if (isset($params['customer'])) {
            $customer = new self();
            $customer->setUsername($this->username);
            $customer->setPassword($this->password);

            //  move cc up to customer
            $customerParams                    = $params['customer'];
            $customerParams['cc']              = $params['cc'];
            $customerParams['expirationMonth'] = $params['expirationMonth'];
            $customerParams['expirationYear']  = $params['expirationYear'];
            $customerParams['terms']           = 'Y';
            $paytraceCustomer                  = $customer->createCustomer($customerParams);

            if ($paytraceCustomer->customerId) {
                $params['customerId'] = $paytraceCustomer->customerId;
            }
        }

        //  default to starting today
        if (!isset($params['firstBillingDate'])) {
            $params['firstBillingDate'] = 'now';
        }

        //  default to a lot
        if (!isset($params['numberOfBillingCycles'])) {
            $params['numberOfBillingCycles'] = 999;
        }

        //  convert from human readable to paytrace
        if (isset($params['frequency'])) {
            if (strtolower($params['frequency']) == 'yearly') {
                $params['frequency'] = 1;
            }
            elseif (strtolower($params['frequency']) == 'monthly') {
                $params['frequency'] = 3;
            }
        }

        //  format it
        $params['firstBillingDate'] = \Carbon\Carbon::parse($params['firstBillingDate'])->format('m/d/Y');

        $this->process($params);

        return $this;
    }

    private function process($params = [])
    {
        //  reset
        $this->success = false;
        $this->response = [];
        $this->message = '';
        $this->transactionId = '';

        $params = array_merge($params, [
            'username' => $this->username,
            'password' => $this->password,
            'terms'    => 'Y'
        ]);

        $values = [];

        //  map keys to Paytrace's
        foreach ($params as $key => $val) {
            if (isset($this->transactionMap[$key])) {
                $values[] = $this->transactionMap[$key] . '~' . $val;
            }
        }

        //URL encode the request
        $postfields['parmlist'] = implode('|', $values);

        $client = new Client();
        $response = (string) $client->post(config('paytrace.endpoint'), [
            'form_params' => $postfields,
            'headers' => [
                'MIME-Version' => '1.0',
                'Content-type' => 'application/x-www-form-urlencoded',
                'Contenttransfer-encoding' => 'text'
            ]
        ])->getBody();

        $messages = [];

        foreach (explode('|', $response) as $piece) {
            //  ignore empties
            if (empty($piece)) { continue; }

            //  error found
            if (strpos($piece, 'ERROR~') === 0) {
                list($key, $parts) = explode('.', $piece, 2);

                $messages[] = trim(implode('.', (array) $parts));

                $this->success = false;
            } else {
                list($key, $val) = explode('~', $piece, 2);

                if ($key == 'APPCODE' && $val != '') {
                    $this->success = true;
                }
                elseif ($key == 'TRANSACTIONID') {
                    $this->transactionId = $val;
                }
                elseif ($key == 'CUSTID') {
                    $this->customerId = $val;
                }
                elseif ($key == 'RECURID') {
                    $this->subscriptionId = $val;
                }
            }
        }

        $this->message = trim(implode(' ', $messages));
    }
}