<?php

/**
 * A helper class for signing XML.
 *
 * This is a helper class for signing XML documents.
 *
 * @author Olav Morken, UNINETT AS.
 * @package simpleSAMLphp
 * @version $Id$
 */
class SimpleSAML_XML_Signer {


	/**
	 * The path to the simpleSAMLphp cert dir.
	 */
	private static $certDir = FALSE;

	/**
	 * The name of the ID attribute.
	 */
	private $idAttrName;

	/**
	 * The private key (as an XMLSecurityKey).
	 */
	private $privateKey;

	/**
	 * The certificate (as text).
	 */
	private $certificate;



	/**
	 * Constructor for the metadata signer.
	 *
	 * You can pass an list of options as key-value pairs in the array. This allows you to initialize
	 * a metadata signer in one call.
	 *
	 * The following keys are recognized:
	 *  - privatekey       The file with the private key, relative to the cert-directory.
	 *  - privatekey_pass  The passphrase for the private key.
	 *  - certificate      The file with the certificate, relative to the cert-directory.
	 *  - id               The name of the ID attribute.
	 *
	 * @param $options  Associative array with options for the constructor. Defaults to an empty array.
	 */
	public function __construct($options = array()) {
		assert('is_array($options)');

		if(self::$certDir === FALSE) {
			$config = SimpleSAML_Configuration::getInstance();
			self::$certDir = $config->getPathValue('certdir');
		}

		$this->idAttrName = FALSE;
		$this->privateKey = FALSE;
		$this->certificate = FALSE;

		if(array_key_exists('privatekey', $options)) {
			$pass = NULL;
			if(array_key_exists('privatekey_pass', $options)) {
				$pass = $options['privatekey_pass'];
			}

			$this->loadPrivateKey($options['privatekey'], $pass);
		}

		if(array_key_exists('certificate', $options)) {
			$this->loadCertificate($options['certificate']);
		}

		if(array_key_exists('id', $options)) {
			$this->setIdAttribute($options['id']);
		}
	}


	/**
	 * Set the private key.
	 *
	 * Will throw an exception if unable to load the private key.
	 *
	 * @param $file  The file which contains the private key. The path is assumed to be relative
	 *               to the cert-directory.
	 * @param $pass  The passphrase on the private key. Pass no value or NULL if the private key is unencrypted.
	 */
	public function loadPrivateKey($file, $pass = NULL) {
		assert('is_string($file)');
		assert('is_string($pass) || is_null($pass)');

		$keyFile = self::$certDir . $file;
		if (!file_exists($keyFile)) {
			throw new Exception('Could not find private key file "' . $keyFile . '".');
		}
		$keyData = file_get_contents($keyFile);
		if($keyData === FALSE) {
			throw new Exception('Unable to read private key file "' . $keyFile . '".');
		}

		$this->privateKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
		if($pass !== NULL) {
			$this->privateKey->passphrase = $pass;
		}
		$this->privateKey->loadKey($keyData, FALSE);
	}


	/**
	 * Set the certificate we should include in the signature.
	 *
	 * If this function isn't called, no certificate will be included.
	 * Will throw an exception if unable to load the certificate.
	 *
	 * @param $file  The file which contains the certificate. The path is assumed to be relative to
	 *               the cert-directory.
	 */
	public function loadCertificate($file) {
		assert('is_string($file)');

		$certFile = self::$certDir . $file;
		if (!file_exists($certFile)) {
			throw new Exception('Could not find certificate file "' . $certFile . '".');
		}

		$this->certificate = file_get_contents($certFile);
		if($this->certificate === FALSE) {
			throw new Exception('Unable to read certificate file "' . $certFile . '".');
		}
	}


	/**
	 * Set the attribute name for the ID value.
	 *
	 * @param $idAttrName  The name of the attribute which contains the id.
	 */
	public function setIDAttribute($idAttrName) {
		assert('is_string($idAttrName)');

		$this->idAttrName = $idAttrName;
	}

	/**
	 * Signs the given DOMElement and inserts the signature at the given position.
	 *
	 * The private key must be set before calling this function.
	 *
	 * @param $node  The DOMElement we should generate a signature for.
	 * @param $insertInto  The DOMElement we should insert the signature element into.
	 * @param $insertBefore  The element we should insert the signature element before. Defaults to NULL,
	 *                       in which case the signature will be appended to the element spesified in
	 *                       $insertInto.
	 */
	public function sign($node, $insertInto, $insertBefore = NULL) {
		assert('$node instanceof DOMElement');
		assert('$insertInto instanceof DOMElement');
		assert('is_null($insertBefore) || $insertBefore instanceof DOMElement ' .
			'|| $insertBefore instanceof DOMComment || $insertBefore instanceof DOMText');

		if($this->privateKey === FALSE) {
			throw new Exception('Private key not set.');
		}


		$objXMLSecDSig = new XMLSecurityDSig();
		$objXMLSecDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

		$options = array();
		if($this->idAttrName !== FALSE) {
			$options['id_name'] = $this->idAttrName;
		}

		$objXMLSecDSig->addReferenceList(array($node), XMLSecurityDSig::SHA1,
			array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
			$options);

		$objXMLSecDSig->sign($this->privateKey);


		if($this->certificate !== FALSE) {
			/* Add the certificate to the signature. */
			$objXMLSecDSig->add509Cert($this->certificate, TRUE);
		}


		$objXMLSecDSig->insertSignature($insertInto, $insertBefore);
	}
}

?>