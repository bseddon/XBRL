<?php

/**
 * Example to generate instance document views for the Danish Business Authority as HTML
 * Uses compiled taxonomies to improve performance so this code sample assumes they have
 * been compiled using compile.php
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2019 Lyquidity Solutions Limited
 * @License: GPL 3.0
 *
 * Taxonomies are available here:
 * https://erhvervsstyrelsen.dk/tidligere-versioner
 *
 * The taxonomies can be viewed here using Yeti:
 * https://yeti2.corefiling.com/yeti/resources/yeti-gwt/Yeti.jsp
 *
 * Handy terms for the english retricted reader:
 *
 * Erhvervsstyrelsen: Business Authority
 *
 * Taxonomy terms:
 * --------------
 * kontoform fordelt på kort - og langfristet:	Account form broken down by short and long term
 * Resultatopgørelse (artsopdelt):				Profit and loss account (divided by class)
 * Resultatopgørelse (funktionsopdelt):			Income Statement (Functional)
 *
 * Report terms:
 * ------------
 * Årsrapport:									Annual Report
 * Nøgletal:									Key figures
 * Omsaetning:									Revenue
 * Bruttofortjeneste:							Gross Profit
 * Resultat før skat:							Profit before tax
 * Årets resultat:								Net profit
 * Egenkapital:									Equity
 * Balance:										Total Assets/Liabilities
 * Overført resultat							Carried forward
 * Selskabskapital								Share capital
 * I alt										Total
 */

namespace lyquidity\dfb;

register_shutdown_function( '\lyquidity\dfb\shutdownHandler' );

/* ------------------------------------------------------------
 * Change some environment parameters
 * ------------------------------------------------------------ */
// Set the maximum execution time to three minutyptes
set_time_limit(180);
// Make sure there is olenty of memory
ini_set( 'memory_limit', '512M' );
// Allow deep nesting which may be required for the XPath 2.0 processor when XDebug is used
ini_set('xdebug.max_nesting_level', 512);

/* ------------------------------------------------------------
 *  In lieu of implementing this as a class, use globals for
 *  the location variables so they can be accessed anywhere
 *  within the code.  There is nothing magical about the
 *  folders chosen here.  They need to be somewhere that can
 *  be written.
 * ------------------------------------------------------------ */
global $cacheLocation, $compiledLocation, $instancesLocation;

// This is a local location used to store the expanded taxonomy files because they come in a taxonomy package (zip) file.
$cacheLocation = __DIR__ . "/cache"; // !!! Change this

// This is a local location where the compiled version of the taxonomy will be stored.
$compiledLocation = __DIR__ . "/compiled"; // !!! Change this

// The location of the instances to be reported.  It could be a non-local location such as a web site.
$instancesLocation = __DIR__ . "/instances"; // !!! Change this

// The location to store generated HTML renderings
$htmlLocation = __DIR__ . "/html"; // !!! Change this

// The location of HTMLK JS and CSS assets
$htmlAssetsLocation = '../../assets';

// Use null for the default language
$languageCode = 'da';
// $languageCode = null;

// Allow formulas to be evaluated
global $use_xbrl_functions;
$use_xbrl_functions = true;

/* ------------------------------------------------------------
 *  Taxonomies and instances
 * ------------------------------------------------------------ */

// The set of instance documents to report
// There needs to be some mechanism to generate sets of company files.
$instanceGroupss = array( // !!! Change this
	'aarsrapport' => array(
		'10403782.2016.AARSRAPPORT.xml',
		// '15505281.2015.AARSRAPPORT.xml',
		// '49260016.2017.AARSRAPPORT.xml',
		// '81822514.2017.AARSRAPPORT.xml',
	),
	'andco' => array(
		'and co 2014.xml',
		'and co 2015.xml',
		'and co 2016.xml',
		'and co 2017.xml'
	)
);

$instances = $instanceGroupss['andco'];

/* ------------------------------------------------------------
 * Make the processor code accessible
 * ------------------------------------------------------------ */
require_once __DIR__ . '/vendor/autoload.php'; // !!! Change this
require_once __DIR__ . '/Observer.php';

// Make it a validating processor
\XBRL::setValidationState();

// Set the language to be returned from XBRL->getDefaultLanguage();
\XBRL::$specificLanguage = $languageCode;

// Use the debug log for more ready display of issues to the console
\XBRL_Log::getInstance()->debugLog();

// An observer is used to capture any validation information generated while processing taxonomies
$observer = new Observer();
\XBRL_Log::getInstance()->attach( $observer );

// Initialize the DFR class
\XBRL_DFR::Initialize( $cacheLocation );

try
{
	if ( ! file_exists( "$compiledLocation" ) )
	{
		$observer->addItem( "error", "The compiled folder location does not exist" );
		return;
	}

	global $reportModelStructureRuleViolations;
	$reportModelStructureRuleViolations = false;

	\XBRL_Global::reset();
	\XBRL_Types::reset();

	new \XBRL_IFRS();

	$instanceFilename = $instances[0];

	$observer->addItem( "action", "processing instance '$instanceFilename'" );

	// Initialize the cache
	$context = \XBRL_Global::getInstance();
	if ( ! $context->useCache )
	{
		$context->useCache = true;
		$context->cacheLocation = $cacheLocation;
		$context->initializeCache();
	}

	$document = "$instancesLocation/$instanceFilename";

	if ( ! file_exists( $document ) )
	{
		$observer->addItem( "error", "Unable to locate the instance document '$instanceFilename'" );
		return;
	}

	$schemaHRef = getInstanceTaxonomyHRef( $document, $context );
	if ( ! $schemaHRef )
	{
		$log->warning("Unable to find schemaRef");
		return;
	}

	// Look to see if there is an existing cached file from a previous report
	$instanceBasename = basename( $instanceFilename, '.xml' );
	if ( file_exists( "$compiledLocation/$instanceBasename.json" ) && file_exists( "$compiledLocation/$instanceBasename.meta" ) )
	{
		// Load the JSON file which contains the name of the taxonomy to use
		$json = file_get_contents( "$compiledLocation/$instanceBasename.meta" );
		$meta = json_decode( $json, true );
		$instance = \XBRL_Instance::FromInstanceCache( $compiledLocation, "{$meta['instance']}.json", $meta['namespace'], "$compiledLocation/{$meta['taxonomy']}" );
	}
	else
	{
		$schemaHRef = getInstanceTaxonomyHRef( $document );

		$pattern = '/^http:\/\/archprod\.service\.eogs\.dk\/taxonomy\/(?<version>\d{8})\/.*\.xsd$/';
		if ( ! preg_match( $pattern, $schemaHRef, $matches ) )
		{
			$observer->addItem("error", "The schema ref of '$instanceBasename.xml' is not valid: '$schemaHRef'");
			return;
		}

		$version = $matches['version'];

		// Use the version year to choose the correct compiled taxonomy
		$compiledTaxonomyFilename = "$compiledLocation/entryAll$version.json";
		if ( ! file_exists( $compiledTaxonomyFilename ) )
		{
			$observer->addItem( "error", "instance document taxonomy namespace is not a supported version '$version'" );
			return;
		}

		// Pass $compiledTaxonmyFilename which will reference the compiled taxonomy
		$instance = \XBRL_Instance::FromInstanceDocument( $document, $compiledTaxonomyFilename );
	}

	$formulas = null;
	$results = array();

	$instanceTaxonomy = $instance->getInstanceTaxonomy();
	$dfr = new \XBRL_DFR( $instanceTaxonomy );
	$presentationNetworks = $dfr->validateDFR( $formulas, false, $languageCode );
	// $presentationNetworks = array_slice( $presentationNetworks, 0, 1 );
	$renders = $dfr->renderPresentationNetworks( $presentationNetworks, $instance, $formulas, $observer, $languageCode, false, $results );

	// Delete/create a sub-folder for the HTML
	if ( is_dir( "$htmlLocation/$instanceBasename" ) )
	{
		// Function to recursively remove content and folders
		$rmrf = function ($dir) use ( &$rmrf )
		{
		    foreach ( glob( $dir ) as $file )
		    {
		        if ( is_dir( $file ) )
		        {
		            $rmrf( "$file/*" );
		            rmdir( $file );
		        }
		        else
		        {
		            unlink( $file );
		        }
		    }
		};

		$rmrf( "$htmlLocation/$instanceBasename" );
	}
	mkdir( "$htmlLocation/$instanceBasename", 0777, true );

	$indexHTML =
		"<html>\n" .
		"	<head>\n" .
		"		<title>XBRL Rendered Views Index</title>\n" .
		"		<link rel='stylesheet' id='bootstrap_style-css' href='http://www.xbrlquery.com/wp-content/themes/zerif-pro/css/bootstrap.min.css?ver=4.9.10' type='text/css' media='all'>\n" .
		"		<link rel='stylesheet' id='font-awesome_style-css' href='http://www.xbrlquery.com/wp-content/themes/zerif-pro/assets/css/font-awesome.min.css?ver=v1' type='text/css' media='all'>\n" .
		"		<style>\n" .
		"			body { margin-left: 20px; margin-right: 20px; }\n" .
		"		</style>\n" .
		"	</head>\n" .
		"	<body>\n" .
		"		<h1>XBRL Rendered Views Index</h1>\n" .
		"		<div class='primary'>\n" .
		"			%content%\n" .
		"		</div>\n" .
		"	</body>\n" .
		"</html>";

	$indexContent = "";
	$count = 0;
	foreach ( $renders as $role => $render )
	{
		$count++;

		if ( isset( $render['hasReport'] ) && ! $render['hasReport'] ) continue;

		// Generate an index file and a file for each of the networks
		foreach ( $render['entities'] as $entity => $networkHTML )
		{
			$html =
				"<!DOCTYPE html>\n" .
				"<html>\n" .
				"	<head>\n" .
				"		<title>XBRL Rendered View</title>\n" .
				"		<link href='https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T' crossorigin='anonymous'>\n" .
				"		<link rel='stylesheet' id='render-report-css' href='$htmlAssetsLocation/css/xbrl-render-report.css'>\n" .
				"		<script src='https://kit.fontawesome.com/d5b3603aa0.js'></script>\n" .
				"		<script type='text/javascript' src='https://code.jquery.com/jquery-1.12.4.min.js'></script>\n" .
				"		<script type='text/javascript' src='$htmlAssetsLocation/js/xbrl-render-report.js'></script>\n" .
				"		<style>\n" .
				"			body { margin-left: 20px; margin-right: 20px; }\n" .
				"		</style>\n" .
				"		<meta charset='utf-8'/>\n" .
				"	</head>\n" .
				"	<body>\n" .
				"		<div id='primary'>\n" . $networkHTML .
				"		</div>\n" .
				"	</body>\n" .
				"</html>";

			$filename = $count . ( $languageCode ? "-$languageCode" : "" );
			file_put_contents( "$htmlLocation/$instanceBasename/$filename.html", $html );

			$indexContent .=
				"			<div class='index-entry'>\n" .
				"				<a href='$filename.html' target='_blank' title='$role'>{$render['text']}</a>\n" .
				"			</div>\n";
		}

	}

	if ( empty( $indexContent ) )
	{
		$indexContent = "<div class='no-reports'>There are no reports to display</div>";
	}

	$filename = "index" . ( $languageCode ? "-$languageCode" : "" );
	file_put_contents( "$htmlLocation/$instanceBasename/$filename.html", str_replace("%content%", $indexContent, $indexHTML ) );

	echo "Index file written to '$htmlLocation/$instanceBasename/$filename.html'\n";
}
catch( \Exception $ex )
{
	echo $ex->getMessage();
	return;
}

return;

/**
 * Return the 'href' value of schemaRef tag
 * @param string $filename
 * @return string
 */
function getInstanceTaxonomyHRef( $filename )
{
	$dom = new \DOMDocument();
	$dom->load( $filename );
	$domXPath = new \DOMXPath( $dom );
	$domXPath->registerNamespace( 'xbrli', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] );
	$domXPath->registerNamespace( 'link', \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
	$nodes = $domXPath->query("/xbrli:xbrl/link:schemaRef");
	/** @var $domElement \DOMElement */
	$domElement = $nodes[0];
	return $domElement->getAttribute('xlink:href');

}

/**
 * Handler for 'set_error_handler' function
 * @param int $error_level Contains the level of the error raised, as an integer.
 * @param string $error_message Contains the error message, as a string.
 * @param string $error_file Contains the filename that the error was raised in, as a string.
 * @param int $error_line Contains the line number the error was raised at, as an integer.
 * @param array $error_context An array that points to the active symbol table at the point the error occurred
 */
function errorHandler( $error_level, $error_message, $error_file, $error_line, $error_context )
{
	$error = array(
			"level" => $error_level,
			"message" => $error_message,
			"file" => $error_file,
			"line" => $error_line,
	);

	switch ( $error_level )
	{
		case E_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_PARSE:
			$error['class'] = "fatal";
			break;

		case E_USER_ERROR:
		case E_RECOVERABLE_ERROR:
			$error['class'] = "error";
			break;

		case E_WARNING:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_USER_WARNING:
			$error['class'] = "warn";
			break;

		case E_NOTICE:
			return true; // Ignore notices

		case E_USER_NOTICE:
			$error['class'] = "info";
			break;

		case E_STRICT:
			$error['class'] = "debug";
			break;

		default:
			$error['class'] = "warn";
	}

	print_r( $error );
	error_log( print_r( $error, true ) );
}

/**
 * register_shutdown_function
 */
function shutdownHandler() //will be called when php script ends.
{
	$lasterror = error_get_last();

	if ( ! $lasterror ) return;

	switch ( $lasterror['type'] )
	{
		case E_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_USER_ERROR:
		case E_RECOVERABLE_ERROR:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_PARSE:
			print_r( $lasterror );
			error_log( print_r( $lasterror, true ) );
			break;
	}
}
