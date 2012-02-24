<?php
/**
 * SMS Global Datasource
 *
 * API Documentation should be on hand when making calls using this datasorce.
 * http://smsglobal.com/docs/SOAP.pdf
 *
 * PHP 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the below copyright notice.
 *
 * @author     Tom Rothwell <tom@pollenizer.com>
 * @copyright  Copyright 2012, Pollenizer Pty. Ltd. (http://pollenizer.com)
 * @license    MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @since      CakePHP(tm) v 2.0.5
 * 
 */
class SmsGlobalSource extends DataSource {
    /**
     * var array $error
     */
    public $error = array();

    /**
     * var string $password
     */
    public $password = null;

    /**
     * var string $smsWsdl - The wsdl file to use with the soap client
     */
    public $smsWsdl = 'http://www.smsglobal.com/mobileworks/soapserver.php?wsdl';

    /**
     * var $soapClient
     */
    public $soapClient = null;

    /**
     * var string $ticketId - The ticketId to validate soap requests
     */
    public $ticketId = null;

    /**
     * var string $user
     */
    public $user = null;

    /**
     * Constructor - Sets the configuration
     */
    public function __construct($config) 
    {
        $this->user = $config['user'];
        $this->password = $config['password'];
        $this->init();
        parent::__construct($config);
    }

    /**
     * Validates the credentials and creates a ticket Id for API calls
     * @return boolean
     */
    public function init() 
    {
        try {
            $this->soapClient = @new SoapClient($this->smsWsdl, array('exceptions' => 1));
            //Validate user account details
            $params = array('user' => $this->user, 'password' => $this->password);
            $response = $this->query('apiValidateLogin', $params);
            if (!empty($response['resp']['@err'])) {
                $this->setError('Failed to validate');
                return false;
            }
            //Set the ticket id to process API calls
            $this->ticketId = $response['resp']['ticket'];
            return true;
        } catch (SoapClient $exception) {
            $this->setError($exception->faultstring);
            return false;
        }
    }
    /**
     * Returns the ticket id used for API call's
     * @return string $this->ticketId
     */
    public function getTicketId() 
    {
        return $this->ticketId;
    }

    /**
     * Sets an error, can also be emptied to trigger
     * a potential new API call. 
     * @param string $error
     * @return string $error
     */
    public function setError($error = null) 
    {
        $this->error = $error;
        return $error;
    }

    /**
     *  @return string $this->error
     */
    public function getError() 
    {
        return $this->error;
    }
    
    /**
     * Generic function to allow any SMS Global API call
     * @params
     *  string $type - The name within the wsdl to call
     *  mixed $params - Parameters to pass according to wsdl
     * @return mixed
     */
    public function query($type = null, $params = array()) 
    {
        switch ($type) {
        case 'getTicketId':
            return $this->getTicketId();
            break;
        case 'sendSms' :
            return $this->sendSms($params);
            break;
        case 'checkBalance' :
            return $this->checkBalance($params); //Should be a string
            break;
        case 'getError' :
            return $this->getError();
            break;
        } 

        if (!empty($this->error) || $this->soapClient == null) {
            return false;
        }

        try {
            $result = $this->soapClient->__call($type, $params);
            return $this->_xmlResponse($result);
        } catch (SoapFault $exception) {
            $this->setError($exception->faultstring);
            return false;
        }
    }

    /**
     * Sends an SMS via $this->query();
     * @param array $params
     * @return mixed
     */
    public function sendSms($params = array()) 
    {
        $schedule = 0;
        if (isset($params['schedule'])) {
            $schedule = $params['schedule'];
            unset($params['schedule']);
        }
        //Add in the default params required by the API
        $requiredParams = array(
            'ticket' => $this->ticketId,
            'unicode' => 0, //Depricated, should always be 0
            'msg_type' => 'text',
            'schedule' => $schedule,
        );
        $params = array_merge($params, $requiredParams);
        return $this->query('apiSendSms', $params);
    }

    /**
     * Sends a balance request call
     * @param string $iso - The country code to check for
     * @return mixed
     */
    public function checkBalance($iso = 'AU') 
    {
        $params = array(
            'ticket' => $this->ticketId,
            'iso_country' => $iso,
        );
        return $this->query('apiBalanceCheck', $params);
    }

    /**
     * Places the response into a readable array
     * @param string $result
     * @return mixed
     */
    private function _xmlResponse($result) 
    {
        try {
            return Xml::toArray(Xml::build($result));
        } catch (XmlException $exception) {
            $this->setError($exception->getMessage());
            return false;
        }
    }
}
