<?php

/**
 * XBRL taxonomy signing service
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright (C) 2016 Lyquidity Solutions Limited
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

/**
 * Class to provide taxonomy signing and verification
 */
class XBRL_Signer
{
	/**
	 * Sign and save the signed taxonomy.
	 * @param string $source The source of the JSON
	 * @param string $private_key_pem  Path to a PEM formatted file containing the private key or a string containing the private key
	 * @param string $signature_alg The name the hash algorithm to use (Default: SHA256)
	 * @return string
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
			$message = "Unable to create a key id from the public key resource $pem\n" . openssl_error_string();
			throw new \Exception( $message );
		}

		$result = openssl_verify( $json, $signature, $public_key, $signature_block['algorithm'] );
		if ( $result == -1 )
		{
			$message = "Error verifying: " . openssl_error_string();
		}

		openssl_free_key( $public_key );

		return $result;
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
			$message = "Unable to create a key id from the private key resource $pem\n" . openssl_error_string();
			throw new \Exception( $message );
		}

		$signature = null;

		$result = openssl_sign( $content, $signature, $private_key, $signature_alg );
		if ( ! $result )
		{
			$message = "Error to signing: " . openssl_error_string();
		}

		openssl_free_key( $private_key );

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

}

