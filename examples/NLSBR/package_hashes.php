<?php

/**
 * PHP implementation of the Reporting package hash generator
 * see https://www.nba.nl/themas/ict/reporting-package-hash-generator/
 * Bill Seddon
 * 2021-06-11
 */

$zip = new ZipArchive();
if ( $zip->open( '... link to package zip file ...' ) !== true )
	return;

$hashes = array();

// Read the files and folders
for ( $index = 0; $index < $zip->numFiles; $index++ )
{
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
			$file = $dom->C14N( false, true );
			$dom = null;
			unset( $dom );

			break;
		}
	}

	$hashes[ $name ] = substr( base64_encode( hash( 'sha256', $file, true ) ), -16 );
}

$zip->close();

// Case insensitive sort
asort( $hashes, SORT_FLAG_CASE|SORT_STRING );

// Add the total hash to the list
$hashes['totalHash'] = substr( base64_encode( hash( 'sha256', join( '', array_values( $hashes ) ), true ) ), -16 );

// Save as a JSON file
file_put_contents( __DIR__ . '/sbr_hashes.json', json_encode( $hashes, JSON_PRETTY_PRINT ) );
