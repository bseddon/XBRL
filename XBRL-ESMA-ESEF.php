<?php

/**
 * UK FRC taxonomy implementation
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

/**
 * Load the XBRL implementation
 */
require_once('XBRL.php');

/**
 * Define the namespaces of the entry points supported by this taxonomy
 * @var array
 */
$entrypoint_namespaces = array(
	"http://www.esma.europa.eu/taxonomy/2017-03-31/esef_all",
	"http://www.esma.europa.eu/taxonomy/2017-03-31/esef_cor",
	"http://www.esma.europa.eu/taxonomy/2017-03-31/technical"
);

/**
 * Register namespace to class map entries
 *
 * This call defines the namespaces that apply to the use of the XBRL decendent class defined in this file.
 * The static function XBRL::withtaxonomy() will use the information provided by this call to instantiate
 * the correct (this) class.
 */
XBRL::add_namespace_to_class_map_entries( $entrypoint_namespaces, "XBRL_ESMA_ESEF" );
// XBRL::add_namespace_to_class_map_entries( array('http://xbrl.ifrs.org/taxonomy/2017-03-09/ifrs-full'), "XBRL_ESMA_ESEF" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces, "XBRL_ESMA_ESEF" );

/**
 * Register XSD to compiled taxonomy entries
 */
XBRL::add_xsd_to_compiled_map_entries( array(
	"esef_all.xsd",
), "esef_all" );
XBRL::add_xsd_to_compiled_map_entries( array(
	"esef_cor.xsd",
), "esef_cor" );

/**
 * Implements an XBRL descendent for the UK GAAP taxonomy.
 * @author Bill Seddon
 */
class XBRL_ESMA_ESEF extends XBRL
{
	// TODO: Create this list programatically.  For example, all the entity officer member names, notes that has a string type, address lines, etc.
	/**
	 * An array of element ids that when they appear in a report their values should be treated as text.
	 * This has a specific meaning in the default report: the associated values are not shown tied to a
	 * specific financial year.
	 * @var array[string]
	 */
	private static $textItems = array();

	/**
	 * Elements for which the value should be used as a label.  Usually used with tuples.
	 * @var array[string]
	 */
	public static $labelItems = array();

	/**
	 * Default contructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get a list of all the members
	 * @param array $dimensionalNode
	 * @return array An array of the member ids
	 */
	private function getValidMembers( $dimensionalNode )
	{
		$result = array();
		if ( ! $dimensionalNode || $dimensionalNode['nodeclass'] !== 'dimensional' )
		{
			return $result;
		}

		if ( $dimensionalNode['taxonomy_element']['type'] === 'types:domainItemType' )
		{
			$result[ $dimensionalNode['taxonomy_element']['id'] ] = true;
		}

		if ( ! isset( $dimensionalNode['children'] ) )
		{
			return $result;
		}

		foreach ( $dimensionalNode['children'] as $nodeKey => $node )
		{
			$result += $this->getValidMembers( $node );
		}

		return $result;
	}

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action when the main taxonomy is loaded
	 */
	public function afterMainTaxonomy()
	{
		// Do nothing - for now
	}

	/**
	 * This function is overridden to add the members to the parent node before it is deleted
	 *
	 * @param array $dimensionalNode A node which has element 'nodeclass' === 'dimensional'
	 * @param array $parentNode The parent node so it can be updated
	 * @return bool True if the dimensional information should be deleted
	 */
	protected function beforeDimensionalPruned( $dimensionalNode, &$parentNode )
	{
		return parent::beforeDimensionalPruned( $dimensionalNode, $parentNode );

		// The dimensional information probably contains valid dimensional information
		// That indicate which members of possible hypercubes are valid for the nodes
		// of the parent.

		$members = $this->getValidMembers( $dimensionalNode );
		if ( count( $members ) )
		{
			$parentNode['members'] = $members;
		}

		return true;
	}

	/**
	 * This function provides an opportunity for a taxonomy to sanitize and/or translate a string
	 *
	 * @param string $text The text to be sanitized
	 * @param string $type An optional element type such as num:integer
	 * @param string $language An optional language locale
	 * @return string The sanitized string
	 */
	public function sanitizeText( $text, $type = null, $language = null )
	{
		$text = preg_replace( "/\[heading\]/", "", $text );
		$text = preg_replace( "/\[Dimension\]/", "", $text );
		$text = preg_replace( "/\[default\]/", "", $text );
		$text = str_replace( utf8_encode( '£' ), "&pound;", $text ); // This is necessary to make sure the whole of the unicode character is replaced.

		return rtrim( $text );
	}

	/**
	 * Returns the value of $elemment formatted according to the type defined in the taxonomy
	 * @param array $element A representation of an element from an instance document
	 * @param XBRL_Instance $instance An instance of an instance class related to the $element
	 * @param bool $includeCurrency True if the returned monetary value should include a currency symbol
	 * @return mixed
	 */
	public function formattedValue( $element, $instance = null, $includeCurrency = true )
	{
		$value = $element['value'];
		$type = XBRL_Instance::getElementType( $element );

		switch ( $type )
		{
			case XBRL_Constants::$xbrliMonetaryItemType:
			case 'xbrli:sharesItemType':
				$element['value'] = str_replace( ',', '', $element['value'] );
				return parent::formattedValue( $element, $instance, $includeCurrency );

			case 'types:fixedItemType':
				return parent::formattedValue( $element, $instance, $includeCurrency );

			default:
				return parent::formattedValue( $element, $instance, $includeCurrency );
		}
	}

	/**
	 * Return the value of the element after removing any formatting.
	 * @param array $element
	 * @return float
	 * {@inheritDoc}
	 * @see XBRL::removeValueFormatting()
	 */
	public function removeNumberValueFormatting( $element )
	{
		return parent::removeNumberValueFormatting( $element );
	}

	/**
	 * Gets the alignment for the element based on the type
	 * @param string $namespace
	 * @param string $name
	 * @return string The alignment to use
	 */
	public function valueAlignmentForNamespace( $namespace, $name )
	{
		$prefix = "";

		switch ( $namespace )
		{
			default:
				return parent::valueAlignmentForNamespace( $namespace, $name );
		}

		$type = "$prefix:$name";

		switch ( $type )
		{
			default:
				return "left";
		}

	}

	/**
	 * Get the default currency
	 */
	public function getDefaultCurrency()
	{
		return "GBP";
	}

	/**
	 * Return a default for the language code. Can be overridden.
	 */
	public function getDefaultLanguage()
	{
		return 'en';
	}

	/**
	 * Returns True if the $key is for a row that should be excluded.
	 * Overloads the implementation in XBRL
	 * @param string $key The key to lookup to determine whether the row should be excluded
	 * @param string $type The type of the item being tested (defaults to null)
	 * @return boolean
	 */
	public function excludeFromOutput( $key, $type = null )
	{
		if ( $key === 'http://www.xbrl.org/uk/cd/role/XBRL-Document-Information' ) return true;
		return parent::excludeFromOutput( $key, $type );
	}

	/**
	 * Returns true if instance documents associated with the taxonomy normally provide opening balances.
	 * If they do not, then a user of the taxonomy knows to compute an opening balance from available information.
	 * Override in a descendent implementation.
	 * @return boolean
	 */
	public function openingBalancesSupplied()
	{
		return true;
	}

	/**
	 * Returns true if the element value with the $key is defined as one to display as text
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as text
	 * @param string $type The type of the element
	 * @return boolean Defaults to false
	 */
	public function treatAsText( $key, $type )
	{
		if ( in_array( $key, XBRL_IFRS::$textItems ) ) return true;
		return parent::treatAsText( $key, $type );
	}

	/**
	 * Returns true if the element value with the $key is defined as one that should be used as a label - usually in tuple
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as a label
	 * @return boolean Defaults to false
	 */
	public function treatAsLabel( $key )
	{
		if ( isset( XBRL_IFRS::$labelItems[ $key ] ) ) return XBRL_IFRS::$labelItems[ $key ];
		return parent::treatAsLabel( $key );
	}

	/**
	 * Whether all roles should be used when collecting primary items,
	 * @return bool True if all roles are to be used as the basis for collecting primary items
	 */
	public function useAllRoles()
	{
		return true;
	}
}

?>