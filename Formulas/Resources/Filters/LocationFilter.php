<?php

/**
 * XBRL Formulas
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
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

namespace XBRL\Formulas\Resources\Filters;

use lyquidity\xml\QName;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Iterator\DocumentOrderNodeIterator;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the parent filter
  * http://www.xbrl.org/specification/tupleFilters/REC-2009-06-22/tupleFilters-REC-2009-06-22.html#sec-location-filter
  * CAN BE OPTIMIZED
  * HAS BEEN OPTIMIZED
  * Instead of using an XPath statement use the Filter to process
  * the 'location' and 'variable' specifications directlu
  * (see ModelFormulaObject.py ModelLocationFilter class)
  */
class LocationFilter extends Filter
{
	/**
	 * A qname instance or null
	 * @var string $variable
	 */
	public $variable;

	/**
	 * The qname expression or null
	 * @var string $location
	 */
	public $location;

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$attributes = $node->attributes();
		if ( ! property_exists( $attributes, 'variable' ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Tuple filters", "There is no variable attribute in the location filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			$namespaces = $node->getDocNamespaces(true);

			/**
			 * @var QName $qName
			 */
			// If there is no prefix it should not be resolved to a default namespace
			$variable = trim( $attributes->variable );
			$qName = strpos( $variable, ":" )
				? qname( $variable, $namespaces )
				: new QName( "", null, $variable );
			$this->variable = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);

			// $this->variable = (string)$attributes->variable;
			$result['variable'] = $this->variable;
		}

		if ( ! property_exists( $attributes, 'location' ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Tuple filters", "There is no location attribute in the location filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}
		else
		{
			$this->location = trim( $attributes->location );
			$result['location'] = $this->location;
		}

		$result = parent::storeFilter( $result, $localName );

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		return null;

		$variable = $this->variable['originalPrefix']
			? "{$this->variable['originalPrefix']}:{$this->variable['name']}"
			: "{$this->variable['name']}";
		return "(some \$candidate in {$this->location}, \$fact in \$$variable satisfies \$candidate is \$fact)";
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param XPath2NodeIterator $facts
	 * @param VariableSet $variableSet
	 * @return XPath2NodeIterator Returns the filtered list
	 */
	public function Filter( $facts, $variableSet )
	{
		// Look for the variable
		$vars = $variableSet->getBindingsAsVars();
		$clark = str_replace( "{}", "", "{{$this->variable['namespace']}}{$this->variable['name']}" );
		if ( ! isset( $vars[ $clark ] ) )
		{
			// Should probably throw some kind of error here
			throw XPath2Exception::withErrorCodeAndParam( "XPST0008", Resources::XPST0008, $clark );
		}

		/**
		 * @var \DOMNode[] $candidates
		 */
		$candidates = array();
		$var = $vars[ $clark ];
		if ( $var instanceof XPath2NodeIterator )
		{
			$candidates = array_map( function( $node ) { return $node->getUnderlyingObject(); }, $var->ToList() );
		}
		else
		{
			$candidates[] = $var->getUnderlyingObject();
		}

		$dom = dom_import_simplexml( $variableSet->xbrlInstance->getInstanceXml() );
		$doc = $dom->ownerDocument;
		$xpath = new \DOMXPath( $doc );

		$matched = array();
		$notMatched = array();

		foreach ( $facts as $fact )
		{
			$node = $fact->getUnderlyingObject();

			/**
			 * @var \DOMNode[] $candidates
			 */
			$locations = $xpath->query( $this->location, $node );

			// See if any $candidates and any $locations overlap
			foreach ( $candidates as /** @var \DOMElement $candidate */ $candidate )
			{
				foreach ( $locations as /** @var \DOMElement $location */ $location )
				{
					if ( $candidate->isSameNode( $location ) )
					{
						$matched[] = $fact->CloneInstance();
						continue 3;
					}
				}
			}

			$notMatched[] = $fact->CloneInstance();
		}

		return DocumentOrderNodeIterator::fromItemset( $this->complement ? $notMatched : $matched );

		return $facts;
	}

	/**
	 * Returns the set of aspects covered by this instance
	 *
	 * @var VariableSet $variableSet
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_LOCATION );
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		return parent::validate( $variableSet, $nsMgr );
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		return parent::getVariableRefs();
	}

}
