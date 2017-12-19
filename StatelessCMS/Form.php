<?php

namespace Stateless;

use Stateless\Request;

/**
 * @brief Create and verify html forms
 */
class Form {
    public $name; /**< string Form name to validate submission */
    public $inputs; /**< Array of FormInput objects to populate the form */
    public $cipherKey; /**< Cipher key to encrypt the nonce field */
    public $method; /**< string HTTP method to use.  Default is POST */
    public $action; /**< Form action.  Default is empty */
    public $uuid; /**< User ID to validate submission.  Default is 0 */
    public $obid; /**< Object ID to validate submission.  Default is 0 */
    public $ttl; /**< Time (in seconds) for a form submission to live.  Default is 3600 seconds. */
    public $salt; /**< Salt string to apply to the nonce.  Default is "_" */
    public $pepperLength; /**< Length of the pepper to apply to the nonce.  Default is 2 characters */
    public $nonceKey; /**< The key for the hidden nonce field.  Default is "__nonce" */

    private $submitted;
    private $validated;

    /**
     * @brief Construct a new Form
     * @param mixed $name Form name to validate submission or Array of key values
     * @param array $inputs Array of FormInput objects to populate the form
     * @param string $cipherKey Cipher key to encrypt the nonce field
     * @param string $method HTTP method to use.  Default is POST
     * @param string $action Form action.  Default is empty
     * @param integer $uuid User ID to validate submission.  Default is 0
     * @param integer $obid Object ID to validate submission.  Default is 0
     * @param integer $ttl Time (in seconds) for a form submission to live.
     *  Default is 3600 seconds.
     * @param string $salt Salt string to apply to the nonce.  Default is "_"
     * @param integer $pepperLength Length of the pepper to apply to the nonce.
     *  Default is 2 characters
     * @param string $nonceKey The key for the hidden nonce field.  Default is
     *  "__nonce"
     */
    public function __construct(
        $name = "",
        $inputs = array(),
        $cipherKey = "",
        $method = "POST",
        $action = "",
        $uuid = 0,
        $obid = 0,
        $ttl = 3600,
        $salt = "_",
        $pepperLength = 2,
        $nonceKey = "__nonce"
    ) {
        // Check if first parameter is array
        if (is_array($name)) {
            $data = $name;

            if (array_key_exists("name", $data)) {
                $name = $data["name"];
            }

            if (array_key_exists("method", $data)) {
                $method = $data["method"];
            }

            if (array_key_exists("inputs", $data)) {
                $inputs = $data["inputs"];
            }

            if (array_key_exists("cipher_key", $data)) {
                $cipherKey = $data["cipher_key"];
            }

            if (array_key_exists("action", $data)) {
                $action = $data["action"];
            }

            if (array_key_exists("uuid", $data)) {
                $uuid = $data["uuid"];
            }

            if (array_Key_exists("obid", $data)) {
                $obid = $data["obid"];
            }

            if (array_key_exists("ttl", $data)) {
                $ttl = $data["ttl"];
            }
        
            if (array_key_exists("salt", $data)) {
                $salt = $data["salt"];
            }

            if (array_key_exists("pepper_length", $data)) {
                $pepperLength = $data["pepper_length"];
            }

            if (array_key_exists("nonce_key", $data)) {
                $nonceKey = $data["nonce_key"];
            }
        }

        // Set data
        $this->name = $name;
        $this->method = $method;
        $this->inputs = $inputs;
        $this->cipherKey = $cipherKey;
        $this->action = $action;
        $this->uuid = $uuid;
        $this->obid = $obid;
        $this->ttl = $ttl;
        $this->salt = $salt;
        $this->pepperLength = $pepperLength;
        $this->nonceKey = $nonceKey;


        if (method_exists($this, "init")) {
            $this->init();
        }

        $this->isSubmit();
        $this->isValid();
    }

    /**
     * @brief Check for a submission
     * @return boolean Returns if a submission exists in the request
     */
    public function isSubmit() {
        if (!isset($this->submitted)) {
            $payload = Request::getPayload();
            // Checck for the nonce key in the request payload
            if (
                !empty($payload) &&
                array_key_exists($this->nonceKey, $payload)
            ) {
                // Run callback
                if (method_exists($this, "onSubmit")) {
                    $this->onSubmit();
                }
    
                $this->submitted = true;
            }
            else {
                $this->submitted = false;
            }
        }

        return $this->submitted;
    }

    /**
     * @brief Check if the form's nonce and data is valid
     * @return boolean Returns if the form submission is valid
     */
    public function isValid() {
        if (!isset($this->validated)) {
            // Check for form submission
            if ($this->isSubmit()) {
                // Validate the nonce
                $valid = Crypto::validateNonce(
                    Request::getPayload()[$this->nonceKey],
                    $this->name,
                    $this->uuid,
                    $this->obid,
                    $this->ttl,
                    $this->salt,
                    $this->pepperLength,
                    $this->cipherKey
                );
    
                // Return if not valid
                if (!$valid) {
                    $this->validated = false;
                }
    
                // Check each input for validity
                foreach ($this->inputs as $input) {
                    if (!$input->isValid()) {
                        $this->validated = false;
                    }
                }
    
                // Run callback
                if (method_exists($this, "onValid")) {
                    $this->onValid();
                }
    
                // Checks passed
                $this->validated = true;
            }
            else {
                // No submission, not valid
                $this->validated = false;
            }
        }

        return $this->validated;
    }

    /**
     * @brief Get an array of the fields' input values
     * @return mixed Returns key/value pairs of the form, or false on failure
     */
    public function getValues() {
        $values = array();

        // Get each input value
        foreach ($this->inputs as $input) {
            $value = $input->getValue();
            if ($value !== false) {
                $values[$input->slug] = $input->getValue();
            }
        }

        // Return the values, or false if empty
        return empty($values) ? false : $values;
    }

    /**
     * @brief Output the form markup to the current output buffer
     */
    public function show() {
        // Output form tag
        echo 
            "<form method=\"" . $this->method . "\"" .
            "action=\"" . $this->action . "\" " .
            "name=\"" . $this->name . "\">"
        ;

        // Create a nonce
        $nonce = Crypto::nonce(
            $this->name,
            $this->uuid,
            $this->obid,
            $this->ttl,
            $this->salt,
            $this->pepperLength,
            $this->cipherKey
        );

        // Output nonce field
        echo Crypto::getNonceField(
            $this->nonceKey,
            $nonce
        );

        // Generate fields
        foreach ($this->inputs as $input) {
            echo $input->show();
        }
        
        // Closeup form
        echo "</form>";
    }
}