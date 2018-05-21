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
 * @version 0.9
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

 /**
  * Implements the filter class for the ConceptName filter
  * http://www.xbrl.org/Specification/periodFilters/REC-2009-06-22/periodFilters-REC-2009-06-22.html#sec-instant-duration-filter
  * CAN BE OPTIMIZED
  * (see ModelFormulaObject.py ModelInstantDuration class)
  */
class InstantDuration extends Filter
{
	/**
	 *
	 * @var string $variable QName (required)
	 */
	public $variable = null;

	/**
	 *
	 * @var string $boundary (required)
	 */
	public $boundary = null;

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

		if ( ! property_exists( $attributes, "variable" ) )
		{
			$log->formula_validation( "Filter", "Missing instant duration 'variable' attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
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

		}

		$result["variable"] = $this->variable;

		if ( ! property_exists( $attributes, "boundary" ) )
		{
			$log->formula_validation( "Filter", "Missing instant duration 'boundary' attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}
		else
		{
			$this->boundary = (string)$attributes->boundary;
		}

		$result["boundary"] = (string)$this->boundary;

		if ( $this->boundary != "start" && $this->boundary != "end" )
		{
			$log->formula_validation( "Filter", "Missing instant duration 'boundary' is not 'start' or 'end'", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
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
		// return null;
		$variable = $this->variable['originalPrefix']
			? "{$this->variable['originalPrefix']}:{$this->variable['name']}"
			: $this->variable['name'];

		if ( $this->boundary == "start" ||  $this->boundary == "end" )
		{
			return "(xfi:period(.)[ if (xfi:is-instant-period(.) and xfi:is-start-end-period(xfi:period(\$$variable))) then (xfi:period-instant(.) eq xfi:period-{$this->boundary}(xfi:period(\$$variable))) else fn:false() ])";
		}
		else
		{
			return "fn:false()";
		}
	}

	/**
	 * Filter an array of facts using the filters XPath query.
	 * @param XPath2NodeIterator $facts
	 * @return XPath2NodeIterator Returns the filtered list
	 */
	public function Filterx( $facts )
	{
	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_PERIOD );
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
