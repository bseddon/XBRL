<?php

/**
 * PHP implementation of the Reporting package hash generator
 * see https://www.nba.nl/themas/ict/reporting-package-hash-generator/
 * Bill Seddon
 * 2021-06-12
 */

use lyquidity\xmldsig\InputResourceInfo;
use lyquidity\xmldsig\XAdES;

if ( ! class_exists( '\lyquidity\xmldsig\XAdES', false ) )
{
	throw new \Exception( 'The project xml-signer needs to be loaded' );
}

require __DIR__ . '/../../XBRL.php';

$zip = new \ZipArchive();
if ( $zip->open( '... path to package zip ...' ) !== true )
	return;

$hashes = array();

try
{
	// Read the files and folders
	for ( $index = 0; $index < $zip->numFiles; $index++ )
	{
		// Skip folders
		$name = $zip->getNameIndex( $index );
		if ( $name[ strlen( $name  ) -1 ] == '/' ) continue;

		$file = $zip->getFromIndex( $index );
		$ext = strtoupper( pathinfo( $name, PATHINFO_EXTENSION ) );

		switch( $ext )
		{
			case "XML":
			case "XBRL":
			case "XSD":
			case "HTML":
			case "XHTML": //These files can be canonicalized
			{
				$dom = new DOMDocument();
				$dom->loadXML( $file );
				// Normalize the XML content
				$file = $dom->C14N( false, true );
				$dom = null;
				unset( $dom );

				break;
			}
		}

		$hashes[ $name ] = substr( base64_encode( hash( 'sha256', $file, true ) ), -16 );
	}
}
catch( \Exception $ex )
{
	echo "Oops. " . $ex->getMessage();
	return;
}
finally
{
	$zip->close();
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

// Sign the hashes document
XAdES::signDocument(
	new InputResourceInfo( 
		$dom,
		InputResourceInfo::xmlDocument,
		__DIR__,
		'sbr_hashes.xml',
		null,
		false
	),
	'... path to certificate file ...',
	'... path to private key file ...'
);
