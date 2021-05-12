<?php

/**
 * XBRL taxonomy signing service
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use lyquidity\XMLSecLibs\XMLSecEnc;
use lyquidity\XMLSecLibs\XMLSecurityDSig;
use lyquidity\XMLSecLibs\XMLSecurityKey;

/**
 * Class to provide taxonomy signing and verification
 */
class XBRL_Signer
{
	public function __construct()
	{
		// Fake this so the class is loaded so the signature_alg default value can reference a ststic property
		new XMLSecurityDSig();
	}
	/**
	 * Sign and save the signed taxonomy.
	 * @param string $source The source of the JSON
	 * @param string $private_key_pem  Path to a PEM formatted file containing the private key or a string containing the private key
	 * @param string $signature_alg The name the hash algorithm to use (Default: SHA256)
	 * @return void
	 */
	public function sign_taxonomy( $source, $private_key_pem, $signature_alg = "SHA256" )
	{
		$json = $source;
		$filename = $this->get_source_filename( $source );

		if ( $filename )
		{
			$json = $this->get_json( $filename );
		}

		$taxonomy = $this->get_taxonomy( $json );

		// Is there a signature?  If so remove it and re-generate the string
		if ( isset( $taxonomy['signature_block'] ) )
		{
			unset( $taxonomy['signature_block'] );
			$json = json_encode( $taxonomy );
		}

		$signature = $this->generate_signature( $json, $private_key_pem, $signature_alg );
		$taxonomy['signature_block'] = array(
				'signature' => $signature,
				'algorithm' => 'sha256',
		);

		$json = json_encode( $taxonomy );

		if ( $filename )
		{
			$this->save_json( $json, $filename );
			return true;
		}
		else
		{
			return $json;
		}
	}

	/**
	 * Verify the taxonomy using a public key
	 * @param string $source          The source of the JSON
	 * @param string $public_key_pem  Path to a PEM formatted file containing a certificate or
	 *                                the public key or a string containing the public key for
	 *                                the private key used to generate the signature.
	 * @throws \Exception             Throws an exception if the taxonomy cannot be created or if the taxonomy
	 *                                is not signed or if there is a problem verifying.
	 * @return boolean				  True if the verification succeed.  False if something changed.
	 */
	function verify_taxonomy( $source, $public_key_pem )
	{
		$json = $source;
		$filename = $this->get_source_filename( $source );

		if ( $filename )
		{
			// Get the json from $source. It will throw an exception if there's a problem.
			$json = $this->get_json( $filename );
		}

		// Recreate the object hierarchy. It will throw an exception if there's a problem.
		$taxonomy = $this->get_taxonomy( $json );

		// Is there a signature?  If so remove it and re-generate the string
		if ( ! isset( $taxonomy['signature_block'] ) )
		{
			$message = "The taxonomy does not contain a signature block";
			throw new \Exception( $message );
		}

		// There is so grab it and delete it from the $taxonomy
		$signature_block = $taxonomy['signature_block'];
		unset( $taxonomy['signature_block'] );

		// Check the signature block
		if ( ! isset( $signature_block['signature'] ) || ! isset( $signature_block['algorithm'] ) )
		{
			$message = "The signature block is not valid";
			throw new \Exception( $message );
		}

		// The existing signature is base64 encoded
		$signature = base64_decode( $signature_block['signature'] );

		// Time to re-encode so the signature can be tested.
		$json = json_encode( $taxonomy );

		// The function openssl_pkey_get_public always works with URLs so if necessary convert
		// the $public_key_pem variable to a URL using a 'file' scheme.  If there is no scheme
		// or the scheme is a letter A-Z then a local path is being used and needs converting.
		$parts = parse_url( $public_key_pem );
		if ( ( ! isset( $parts['scheme'] ) || preg_match( "/^[A-Z]$/", $parts['scheme'] ) ) && ! isset( $parts['host'] ) )
		{
			$public_key_pem = "file://";
			if ( isset( $parts['scheme'] ) ) $public_key_pem .= $parts['scheme'] . ":";
			$public_key_pem .= $parts['path'];
		}

		// Check its possible to create a public key id
		$public_key = openssl_pkey_get_public( $public_key_pem );
		if ( $public_key === false )
		{
			$message = "Unable to create a key id from the public key resource $public_key_pem\n" . openssl_error_string();
			throw new \Exception( $message );
		}

		$result = openssl_verify( $json, $signature, $public_key, $signature_block['algorithm'] );
		if ( $result == -1 )
		{
			$message = "Error verifying: " . openssl_error_string();
		}

		// openssl_free_key( $public_key );

		return $result;
	}

	/**
	 * Sign and save the signed taxonomy.
	 * @param string $instanceFile A path to an instance document
	 * @param string $privateKeyFile  Path to a PEM formatted file containing the private key or a string containing the private key
	 * @param string $certificateFile Path to an x509 certificate document
	 * @param string $signedFile The path of the file to save
	 * @param string $signature_alg The name the hash algorithm to use (Default: SHA256)
	 * @return \DOMDocument A signed document to be saved
	 * @throws \Exception 
	 */
	public function sign_instance( $instanceFile, $privateKeyFile, $certificateFile, $signedFile, $signature_alg = XMLSecurityDSig::SHA256 )
	{
		if ( ! file_exists( $instanceFile ) )
		{
			throw new \Exception( "XML file does not exist" );
		}

		// Load the XML to be signed
		$doc = new DOMDocument();
		$doc->load( $instanceFile );

		// Load the certificate
		if ( ! file_exists( $certificateFile ) )
		{
			throw new \Exception( "Certificate file does not exist" );
		}

		// Load the private key
		if ( ! file_exists( $privateKeyFile ) )
		{
			throw new \Exception( "Key file does not exist" );
		}

		$signed = $this->sign_instance_dom( $doc, file_get_contents( $privateKeyFile ), file_get_contents( $certificateFile ), ! (bool)$signedFile, $signature_alg );
		if ( ! $signedFile )
		{
			$parts = pathinfo( $instanceFile );
			$signedFile = "{$parts['dirname']}/{$parts['filename']}.signature";
		}

		file_put_contents( $signedFile, $signed->saveXML() );			
	}

	/**
	 * Sign and save the signed taxonomy.
	 * @param \DOMDocument $instanceDom A DOMDocuent instance
	 * @param string $private_key_pem  A PEM string containing the private key
	 * @param string $certificate An x509 certificate document
	 * @param bool $separateFile True if the signature node should be returned
	 * @param string $signature_alg The name the hash algorithm to use (Default: SHA256)
	 * @return \DOMDocument
	 */
	public function sign_instance_dom( $instanceDom, $private_key_pem, $certificate, $separateFile = false, $signature_alg = XMLSecurityDSig::SHA256 )
	{
		// Create a new Security object 
		$objDSig = new XMLSecurityDSig();

		// Use the c14n exclusive canonicalization
		$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

		// Sign using SHA-256
		$objDSig->addReference(
			$instanceDom, 
			$signature_alg, 
			array('http://www.w3.org/2000/09/xmldsig#enveloped-signature')
		);
	
		// Create a new (private) Security key
		$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
		/*
		If key has a passphrase, set it using
		$objKey->passphrase = '<passphrase>';
		*/
		$objKey->loadKey( $private_key_pem, false );
	
		// Sign the XML file
		$objDSig->sign($objKey);
	
		// Add the associated public key to the signature
		$objDSig->add509Cert( $certificate );
		
		if ( $separateFile )
			return $objDSig->sigNode->ownerDocument;
		
		// Append the signature to the XML
		$objDSig->appendSignature($instanceDom->documentElement);

		return $instanceDom;
	}

	/**
	 * Verifies the signature of an Xml document
	 *
	 * @param string $signedXmlFile
	 * @param string $certificate An x509 certificate document
	 * @return bool
	 * @throws \Exception
	 */
	public function verity_instance( $signedXmlFile, $certificateFile = null )
	{
		if ( ! file_exists( $signedXmlFile ) )
		{
			throw new \Exception( "Signed file does not exist" );
		}
	
		// Load the XML to be signed
		$doc = new DOMDocument();
		$doc->load( $signedXmlFile );

		return $this->verity_instance_dom( $doc, $certificateFile && file_exists( $certificateFile ) ? file_get_contents( $certificateFile ) : null );
	}

	/**
	 * Verifies the signature of an Xml document
	 *
	 * @param \DOMDocument $signedDom
	 * @param string $certificate An x509 certificate document
	 * @return bool
	 * @throws \Exception
	 */
	public function verity_instance_dom( $signedDom, $certificate = null )
	{
		try
		{
			// Create a new Security object 
			$objXMLSecDSig  = new XMLSecurityDSig();
			$signatureDom = null;

			$objDSig = $objXMLSecDSig->locateSignature( $signedDom );
			if ( ! $objDSig )
			{
				// Look to see if there is a companion .signature file
				$parts = pathinfo( $signedDom->baseURI );
				$signatureFile = "{$parts['dirname']}/{$parts['filename']}.signature";
				if ( ! file_exists( $signatureFile ) )
				{
					throw new \Exception("Cannot locate a separate signature file");
				}

				$signatureDom = new \DOMDocument();
				$signatureDom->load( $signatureFile );
				$objDSig = $objXMLSecDSig->locateSignature( $signatureDom );
				if ( ! $objDSig )
				{
					throw new \Exception("Cannot locate Signature Node");
				}
			}
			$objXMLSecDSig->canonicalizeSignedInfo();
			$objXMLSecDSig->idKeys = array('wsu:Id');
			$objXMLSecDSig->idNS = array('wsu'=>'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');

			$retVal = $objXMLSecDSig->validateReference( $signatureDom ? $signedDom : null );
	
			if (! $retVal) 
			{
				throw new \Exception("Reference Validation Failed");
			}
			
			$objKey = $objXMLSecDSig->locateKey();
			if ( ! $objKey ) 
			{
				throw new \Exception("We have no idea about the key");
			}
			$key = NULL;
		
			$objKeyInfo = XMLSecEnc::staticLocateKeyInfo( $objKey, $objDSig );
	
			if ( ! $objKeyInfo->key && empty( $key ) ) 
			{
				// Load the certificate
				$objKey->loadKey( $certificate, TRUE) ;
			}
	
			if ( $objXMLSecDSig->verify( $objKey ) === 1 )
			{
				return true;
			} 
			else
			{
				throw new \Exception( "The signature is not valid: it may have been tampered with." );
			}
	
			print "\n";
		}
		catch( \Exception $ex )
		{
			print $ex->getMessage();
		}

		return false;
	}

	/* -----------------------------------------------
	 * Private functions
	 * -----------------------------------------------
	 */
	/**
	 * Sign content to produce a signature using a private key
	 * @param string $content  The content to be signed
	 * @param string $private_key_pem The private key in PEM format.  Can be a string containing the key or a path to a file containing the key.
	 * @param string $signature_alg The name the hash algorithm to use (Default: SHA256)
	 * @throws \Exception An exception will be thrown if the private key cannnot be opened or used or if there is a problem signing.
	 * @return string The signature
	 */
	private function generate_signature( $content, $private_key_pem, $signature_alg = "SHA256" )
	{
		// The function openssl_pkey_get_private always works with URLs so if necessary convert
		// the $private_key_pem variable to a URL using a 'file' scheme.  If there is no scheme
		// or the scheme is a letter A-Z then a local path is being used and needs converting.
		$parts = parse_url( $private_key_pem );
		if ( ( ! isset( $parts['scheme'] ) || preg_match( "/^[A-Z]$/", $parts['scheme'] ) ) && ! isset( $parts['host'] ) )
		{
			$private_key_pem = "file://";
			if ( isset( $parts['scheme'] ) ) $private_key_pem .= $parts['scheme'] . ":";
			$private_key_pem .= $parts['path'];
		}

		$private_key = openssl_pkey_get_private( $private_key_pem );

		if ( $private_key === false )
		{
			$message = "Unable to create a key id from the private key resource $private_key_pem\n" . openssl_error_string();
			throw new \Exception( $message );
		}

		$signature = null;

		$result = openssl_sign( $content, $signature, $private_key, $signature_alg );
		if ( ! $result )
		{
			$message = "Error to signing: " . openssl_error_string();
		}

		// openssl_free_key( $private_key );

		if ( ! $result )
			throw new \Exception( $message );

			return base64_encode( $signature );
	}

	/**
	 * Get the JSON from $source which might be a string, .json or a .zip file.
	 * @param string $source The source of the JSON
	 * @throws \Exception Exceptions will be thrown if the source is a file but the extension is not .json or .zip or if a zip file cannot be opened
	 * @return string A JSON string
	 */
	private function get_json( $source )
	{
		$json = $source; // Make a default assumption that the source is a JSON string

		// But it may be a file
		if ( file_exists( $source ) )
		{
			$extension = pathinfo( $source, PATHINFO_EXTENSION );

			if ( $extension == 'zip' )
			{
				$zip = new ZipArchive();
				if ( $zip->open( $source ) === true )
				{
					$filename = pathinfo( $source, PATHINFO_FILENAME );
					$json = $zip->getFromName( "$filename.json" );
					$zip->close();
				}
				else
				{
					$message = 'Failed to open zip file $source';
					throw new \Exception( $message );
				}
			}
			else if ( $extension != 'json' )
			{
				$message = "Only files with .zip or .json extension are supported";
				throw new \Exception( $message );
			}
			else
			{
				$json = file_get_contents( $source );
			}
		}

		return $json;
	}

	/**
	 * Returns the filename or null if $source does not reference a valid file
	 * @param string $source The source of the JSON
	 * @return NULL|string
	 */
	private function get_source_filename( $source )
	{
		return file_exists( $source )
		? $source
		: null;
	}

	/**
	 * Create a taxonomy array structure
	 * @param string $json The raw JSON
	 * @throws \Exception An exception will be thrown if there is a problem parsing the JSON
	 * @return array
	 */
	private function get_taxonomy( $json )
	{
		$taxonomy = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE )
		{
			$error = "Error accessing the taxonomy" . XBRL::json_last_error_msg();
			throw new \Exception( "$error" );
		}

		return $taxonomy;
	}

	/**
	 * Save the JSON to file. If the filename ends in .zip the file will be stored in a zip archive.
	 * @param string $json The raw JSON
	 * @param string $filename The name to use for the saved file
	 * @throws \Exception Throws an exception if the zip file cannot be created, if the JSOn file cannot be written or if the extension is invalid
	 */
	private function save_json( $json, $filename)
	{
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		$output_basename = pathinfo( $filename, PATHINFO_FILENAME );

		if ( $extension == 'zip' )
		{
			/**
			 * @var ZipArchive $zip
			 */
			$zip = new ZipArchive();
			$zip->open( $filename, ZipArchive::CREATE );
			$zip->addFromString( "$output_basename.json", $json );

			if ( $zip->close() === false )
			{
				$message = "Error closing zip file: " . $zip->getStatusString();
				throw new \Exception( $message );
			}
		}
		else if ( $extension != 'json' )
		{
			$message = "Only files with .zip or .json extension are supported";
			throw new \Exception( $message );
		}
		else
		{
			if ( file_put_contents( $filename, $json ) === false )
			{
				$message = "Failed to write ";
				throw new \Exception( $message );
			}
		}
	}

	/**
	 * Creates a root certificate and saves it in the designated folder
	 * @param string[] $dn A set of x509 distinguished names
	 * @param string $certificateLocation A path to the location the certificate should be stored
	 * @param string $privateKeyLocation A path to the location the certificate's private key should be stored
	 * @return boolean
	 */		
	function createRootCertificate( $dn, $certificateLocation, $privateKeyLocation )
	{
		$configTemplate = <<<EOT
[ req ]
default_bits					= 2048
distinguished_name				= req_distinguished_name
req_extensions					= v3_req
prompt							= no
encrypt_key						= no

#About the user for the request
[ req_distinguished_name ]

#Extensions to add to a certificate request for how it will be used
[ v3_req ]
basicConstraints                = CA:true
subjectAltName					= @alt_names

#The other names your server may be connected to as
[alt_names]
%email%
EOT;

		$config = str_replace( '%email%', isset( $dn['emailAddress'] ) ? 'email = ' . $dn['emailAddress'] : '', $configTemplate );
		file_put_contents( 'mem:config', $config );

		try
		{
			$configParams = array(
				'config' => 'mem:config',
				'digest_alg' => 'sha256',
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			);

			// Generate a new private (and public) key pair
			if ( ! $privkey = openssl_pkey_new( $configParams ) )
				return false;

			$dn = array(
				'countryName'					=> $dn[ 'countryName' ] ?? null,
				'stateOrProvinceName'			=> $dn[ 'stateOrProvinceName' ]?? null,
				'localityName'					=> $dn[ 'localityName' ] ?? 'Example',
				'organizationName'				=> $dn[ 'organizationName' ] ?? 'Example Co',
				'commonName'					=> $dn[ 'commonName' ] ?? 'Example Co',
				'emailAddress'					=> $dn[ 'emailAddress' ] ?? 'signing@example.com',
				'organizationalUnitName'		=> $dn[ 'organizationalUnitName' ] ?? 'Certification'
			);
			$dn = array_filter( $dn );
			// generates a certificate signing request
			if ( ! $csr = openssl_csr_new( $dn, $privkey, $configParams ) )
				return false;

			// This creates a self-signed cert that is valid for $duration days
			if ( ! $sscert = openssl_csr_sign( $csr, null, $privkey, 365, $configParams ) )
				return false;

			// expport the certificate and the private key
			if ( ! openssl_x509_export( $sscert, $certout ) )
				return false;

			if ( ! openssl_pkey_export( $privkey, $pkout, null, $configParams ) )
				return false;

			file_put_contents( $certificateLocation, $certout );
			file_put_contents( $privateKeyLocation, $pkout);
		}
		catch( \Exception $ex )
		{
			throw new $ex;
		}
		finally
		{
			unlink('mem:config');
		}

		return true;
	}

	/**
	 * Creates a client certificate and saves it in a specified location
	 * @param string[] $dn A set of x509 distinguished names
	 * @param string $rootCert This will be the 'issuer' certificate
	 * @param string $rootPkey The key of the root certificate used to sign the client certificate
	 * @param string $certificateLocation A path to the location the certificate should be stored
	 * @param string $privateKeyLocation A path to the location the certificate's private key should be stored
	 * @param string $LEI An optional legal entity identifier to include in the client certificate
	 * @param string $role A role to include in the client certificate
	 * @param string $crl The address of the CRL source
	 * @return boolean
	 */
	function createClientCertificate( $dn, $rootCert, $rootPkey, $certificateLocation, $privateKeyLocation, $LEI, $role, $crl )
	{

		$configTemplate = <<<EOT
[ req ]
default_bits					= 2048
distinguished_name				= req_distinguished_name
req_extensions					= v3_req
x509_extensions					= x509_ext
prompt							= no
encrypt_key						= no

#About the user for the request
[ req_distinguished_name ]

#Extensions to add to a certificate request for how it will be used
[ v3_req ]
subjectAltName					= @alt_names

#The other names your server may be connected to as
[alt_names]
%email%

[x509_ext]
basicConstraints                = CA:FALSE
keyUsage                        = critical, nonRepudiation, digitalSignature, keyEncipherment
extendedKeyUsage                = critical, clientAuth
%oids%
%crl%
EOT;

		$config = str_replace( '%email%', isset( $dn['emailAddress'] ) ? 'email = ' . $dn['emailAddress'] : '', $configTemplate );
		$config = str_replace( '%crl%', $crl ? 'crlDistributionPoints = ' . $crl : '', $config );
		$oids = ( $LEI ? '1.3.6.1.4.1.52266.1 = ASN1:PRINTABLESTRING:' . $LEI . "\n" : '' );
		$oids .= ( $role ? '1.3.6.1.4.1.52266.2 = ASN1:PRINTABLESTRING:' . $role . "\n" : '' );
		$config = str_replace( '%oids%',  $oids, $config );

		file_put_contents('mem:config', $config );

		try
		{
			$configParams = array(
				'config' => 'mem:config',
				'digest_alg' => 'sha256',
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			);

			// Generate a new private (and public) key pair
			if ( ! $privkey = openssl_pkey_new( $configParams ) )
				return false;

			$dn = array(
				'countryName'				=> $dn[ 'countryName' ] ?? null,
				'stateOrProvinceName'		=> $dn[ 'stateOrProvinceName' ]?? null,
				'localityName'				=> $dn[ 'localityName' ] ?? 'Example',
				'organizationName'			=> $dn[ 'organizationName' ] ?? 'Example Co',
				'commonName'				=> $dn[ 'commonName' ] ?? 'Example Co',
				'emailAddress'				=> $dn[ 'emailAddress' ] ?? 'signing@example.com',
				'organizationalUnitName'	> $dn[ 'organizationalUnitName' ] ?? 'Certification'
			);
			$dn = array_filter( $dn );

			// generates a certificate signing request
			if ( ! $csr = openssl_csr_new( $dn, $privkey, $configParams ) )
				return false;

			// This creates a self-signed cert that is valid for $duration days
			if ( ! $sscert = openssl_csr_sign( $csr, $rootCert, $rootPkey, 365, $configParams ) )
				return false;

			// expport the certificate and the private key
			if ( ! openssl_x509_export( $sscert, $certout ) )
				return false;

			if ( ! openssl_pkey_export( $privkey, $pkout, null, $configParams ) )
				return false;

			file_put_contents( $certificateLocation, $certout );
			file_put_contents( $privateKeyLocation, $pkout);
		}
		catch( \Exception $ex )
		{
			throw $ex;
		}
		finally
		{
			unlink('mem:config');
		}

		return true;
	}


}

