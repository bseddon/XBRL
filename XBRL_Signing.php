<?php

/**
 * XBRL signing
 *
 * @author Bill Seddon
 * @version 0.9
 * @copyright (C) 2021 Lyquidity Solutions Limited
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
 * This script provides functions to create signatures for taxonomies, 
 * packages and instance documents. It depends upon the xml-signer 
 * project and it is assumed to have been included by the caller.
 */

require_once __DIR__ . '/XBRL.php';

if ( ! class_exists( 'lyquidity\xmldsig\XAdES', true ) )
{
	throw new \Exception('The xml-signer project is not available');
}

if ( ! class_exists( 'lyquidity\OCSP\Ocsp', true ) )
{
	throw new \Exception('The Requester project is not available');
}

use lyquidity\xmldsig\InputResourceInfo;
use lyquidity\xmldsig\SignedDocumentResourceInfo;
use lyquidity\xmldsig\XAdES;
use lyquidity\xmldsig\xml\SignatureProductionPlace;
use lyquidity\xmldsig\xml\SignatureProductionPlaceV2;
use lyquidity\xmldsig\xml\SignerRole;
use lyquidity\xmldsig\xml\SignerRoleV2;
use lyquidity\xmldsig\XMLSecurityDSig;

class XAdES_XBRL extends XAdES
{
	/**
	 * Get the filename to use to save the signature
	 * Overridden to return the correct path
	 *
	 * @param string $location
	 * @param string $signatureName
	 * @return string
	 */
	protected function getSignatureFilename( $location, $signatureName = self::SignatureFilename )
	{
		return "$location$signatureName";
	}
}

class XBRL_Signing
{
	private static function traverseContents( &$package, &$signedFilename, &$contents, &$meta, $throwException = true )
	{
		$package->traverseContents( function( $path, $name, $type ) use( $package, &$contents, &$meta, &$signedFilename, $throwException ) 
		{
			/** @var XBRL_Package $package */
			if ( $type == PATHINFO_BASENAME )
			{
				$file = $package->getFile( "$path$name" );
				if ( strpos( $path, 'META-INF') === false )
				{
					if ( $name == $signedFilename && $throwException )
					{
						throw new \Exception('The package file already contains a signed hashes file');
					}
					else
						$contents[ "$path$name" ] = $file;
				}
				else
				{
					$meta[ "$path$name" ] = $file;
				}
			}

			return true;
		} );

		return array();
	}

	/**
	 * Add a signature to a package file
	 *
	 * @param string $sourcePackageFilename
	 * @param string $signedFilename
	 * @return void
	 * @throws \Exception
	 */
	public static function verifySignature( $sourcePackageFilename, $signedFilename = 'signed_hashes.xml' )
	{
		$package = XBRL_Package::getPackage( $sourcePackageFilename );

		$contents = array();
		$meta = array();

		self::traverseContents( $package, $signedFilename, $contents, $meta, false );

		$signature = '';

		// The signed file MUST exist
		if ( ! isset( $contents[ $signedFilename ] ) )
		{
			throw new \Exception('The package file does not contain the signed file.');
		}
		else
		{
			$signature = $contents[ $signedFilename ];
			unset( $contents[ $signedFilename ] );
		}

		$doc = new \DOMDocument();
		$doc->loadXML( $signature );
		$xpath = new \DOMXPath( $doc );
		$nodes = $xpath->query('/hashes/hash');
		unset( $xpath );
		unset( $doc );

		if ( $nodes->length != count( $contents ) + 1 )
		{
			throw new \Exception('The number of files in the package is not the same as the number of hashes in the signature file.');
		}

		$hashes = array();

		// Read the files and folders
		foreach ( $contents as $path => $content )
		{
			$ext = strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) );

			switch( $ext )
			{
				case "XML":
				case "XBRL":
				case "XSD":
				case "HTML":
				case "XHTML": //These files can be canonicalized
				{
					$dom = new DOMDocument();
					$dom->loadXML( $content );
					// Normalize the XML content
					$content = $dom->C14N( false, true );
					$dom = null;
					unset( $dom );

					break;
				}
			}

			$hashes[ $path ] = substr( base64_encode( hash( 'sha256', $content, true ) ), -16 );
		}

		// Case insensitive 
		asort( $hashes, SORT_FLAG_CASE|SORT_STRING );

		// Compute the overall hash
		$hashes['totalHash'] = substr( base64_encode( hash( 'sha256', join( '', array_values( $hashes ) ), true ) ), -16 );

		foreach( $nodes as $node )
		{
			/** @var \DOMElement $node */
			$file = $node->getAttribute('file');
			$value = $node->getAttribute('value');

			if ( isset( $hashes[ $file ] ) )
			{
				if ( $value == $hashes[ $file ] ) continue;
				throw new \Exception("The hash value for file $file is not the same as the one in the signature.");
			}
			else
			{
				throw new \Exception("The file with name $file cannot be found in the package");
			}
		}

		$tempFile = sprintf('%s%ssignature-%s.xml', sys_get_temp_dir(), DIRECTORY_SEPARATOR, session_id() );

		try
		{
			file_put_contents( $tempFile, $signature );

			// Now check the signature
			XAdES::verifyDocument(
				$tempFile
			);
		}
		catch( \Exception $ex )
		{
			throw $ex;
		}
		finally
		{
			unlink( $tempFile );
		}
	}

	/**
	 * Add a signature to a package file
	 *
	 * @param string $sourcePackageFilename
	 * @param string $targetPackageFilename
	 * @param string $certificateFile
	 * @param string $keyFile
	 * @param boolean $addTimestamp
	 * @param boolean $addLTA
	 * @param string|SignatureProductionPlace|SignatureProductionPlaceV2 $signatureProductionPlace
	 * @param string|SignerRole|SignerRoleV2 $signerRole
	 * @param string $signedFilename
	 * @return void
	 * @throws \Exception
	 */
	public static function addSignature( 
		$sourcePackageFilename, 
		$targetPackageFilename, 
		$certificateFile, 
		$keyFile,
		$addTimestamp = false,
		$addLTA = false,
		$signatureProductionPlace = null, 
		$signerRole = null, 
		$signedFilename = 'signed_hashes.xml' )
	{
		$package = XBRL_Package::getPackage( $sourcePackageFilename );

		// Make sure there is a target
		if ( ! $targetPackageFilename )
			$targetPackageFilename = $sourcePackageFilename;

		$contents = array();
		$meta = array();

		self::traverseContents( $package, $signedFilename, $contents, $meta );

		$hashes = array();

		// Read the files and folders
		foreach ( $contents as $path => $content )
		{
			$ext = strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) );

			switch( $ext )
			{
				case "XML":
				case "XBRL":
				case "XSD":
				case "HTML":
				case "XHTML": //These files can be canonicalized
				{
					$dom = new DOMDocument();
					$dom->loadXML( $content );
					// Normalize the XML content
					$content = $dom->C14N( false, true );
					$dom = null;
					unset( $dom );

					break;
				}
			}

			$hashes[ $path ] = substr( base64_encode( hash( 'sha256', $content, true ) ), -16 );
		}

		// Case insensitive 
		asort( $hashes, SORT_FLAG_CASE|SORT_STRING );

		// Compute the overall hash
		$hashes['totalHash'] = substr( base64_encode( hash( 'sha256', join( '', array_values( $hashes ) ), true ) ), -16 );

		// Create an XML document
		$dom = new \DOMDocument();
		$dom->formatOutput = true;
		$root = $dom->createElement('hashes');
		$dom->appendChild( $root );
		foreach( $hashes as $file => $value )
		{
			$hash = $root->ownerDocument->createElement('hash');
			$root->appendChild( $hash );
			$hash->setAttribute( 'file', $file );
			$hash->setAttribute( 'value', $value );
		}

		if ( $signerRole && ! $signerRole instanceof SignerRole  && ! $signerRole instanceof SignerRoleV2 )
		{
			$signerRole = new SignerRoleV2( $signerRole );
		}

		if ( $signatureProductionPlace && ! $signatureProductionPlace instanceof SignatureProductionPlace  && ! $signatureProductionPlace instanceof SignatureProductionPlaceV2 )
		{
			$signatureProductionPlace = new SignatureProductionPlaceV2( $signatureProductionPlace );
		}

		$tempFolder = sprintf('%s%ssignature-%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, session_id() );
		if ( ! file_exists( $tempFolder ) )
		{
			mkdir( $tempFolder );
		}

		$tempFile = "$tempFolder/$signedFilename";

		try
		{
			$xml = $dom->saveXML( null, LIBXML_NOEMPTYTAG );

			// Sign the hashes document
			XAdES::signDocument(
				new InputResourceInfo(
					$xml,
					InputResourceInfo::string,
					dirname( $tempFile ),
					basename( $tempFile ),
					null,
					false
				),
				$certificateFile,
				$keyFile, 
				$signatureProductionPlace,
				$signerRole,
				array(
					'addTimestamp' => $addTimestamp
				)
			);

			if ( $addLTA )
			{
				XAdES::archiveTimestamp(
					new SignedDocumentResourceInfo(
						$tempFile,
						SignedDocumentResourceInfo::file,
						XAdES::SignatureRootId, // optional id
						dirname( $tempFile ),
						basename( $tempFile ),
						XMLSecurityDSig::generateGUID('archive-timestamp-')
					)
				);
			}

			// Update the package zip file
			if ( $sourcePackageFilename != $targetPackageFilename )
				copy( $sourcePackageFilename, $targetPackageFilename );

			$zip = new \ZipArchive();

			try
			{
				$zip->open( $targetPackageFilename );
				if ( ! $zip->addFile( $tempFile, $signedFilename ) )
				{
					throw new \Exception('Failed to add the signature to the package: ' . $zip->getStatusString() );
				}
			}
			catch( \Exception $ex )
			{
				unlink( $targetPackageFilename );
				throw $ex;
			}
			finally
			{
				if ( $zip )
					$zip->close();
			}
		}
		catch( \Exception $ex )
		{
			throw $ex;
		}
		finally
		{
			unlink( $tempFile );
			rmdir( $tempFolder );
		}

	}
}
