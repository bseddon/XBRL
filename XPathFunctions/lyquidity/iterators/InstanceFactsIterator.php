<?php
/**
 * XPath 2.0 for PHP
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *		 |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2017 Lyquidity Solutions Limited
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

namespace XBRL\functions\lyquidity\iterators;

use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\xml\xpath\XPathItem;
use lyquidity\xml\MS\XmlQualifiedNameTest;
use lyquidity\XPath2\IContextProvider;
use lyquidity\XPath2\Iterator\ChildOverDescendantsNodeIterator\NodeTest;
use lyquidity\XPath2\SequenceTypes;
use lyquidity\xml\schema\SchemaTypes;
use lyquidity\XPath2\Iterator\ChildOverDescendantsNodeIterator;
use lyquidity\XPath2\Iterator\ChildNodeIterator;
use lyquidity\XPath2\Iterator\AttributeNodeIterator;

define( "XFI_INSTANCE_ALL_FACTS", 1 );
define( "XFI_INSTANCE_ITEMS", 2 );
define( "XFI_INSTANCE_TUPLES", 3 );
define( "XFI_TUPLE_ITEMS", 4 );
define( "XFI_TUPLE_TUPLES", 5 );

/**
 * DescendantNodeIterator (final)
 */
class InstanceFactsIterator extends XPath2NodeIterator implements \Iterator
{
	/**
	 * A local copy of the context
	 * @var IContextProvider
	 */
	private $context;

	/**
	 * An iterator with the selected nodes
	 * @var XPath2NodeIterator
	 */
	private $iterator;

	/**
	 * The schema types instance
	 * @var SchemaTypes $types
	 */
	private $types;

	/**
	 * The test to apply to nodes to retrieve the substitution group attribute
	 * @var unknown
	 */
	private static $nodeTest = null;

	/**
	 * The valid substitution groups
	 * @var array
	 */
	private static $itemSubstitutionGroup = null;

	/**
	 * The valid substitution groups
	 * @var array
	 */
	private static $tupleSubstitutionGroup = null;

	/**
	 * A list of the valid groups for this test instance
	 * @var array
	 */
	private $substitutionGroups = null;

	/**
	 * Records whether the list includes nil facts (false) or excludes them (true)
	 * @var bool $nonNillOnly
	 */
	private $nonNilOnly = false;

	/**
	 * Function to initialize the static variables
	 */
	public static function __static()
	{
		InstanceFactsIterator::$itemSubstitutionGroup = array( \XBRL_Constants::$xbrliItem );
		InstanceFactsIterator::$tupleSubstitutionGroup = array( \XBRL_Constants::$xbrliTuple );
		InstanceFactsIterator::$nodeTest = XmlQualifiedNameTest::create( "substitutionGroup" );
	}

	/**
	 * Constructor
	 * @param IContextProvider $context
	 */
	public function __construct( $context )
	{
		$this->context = $context;
		$this->types = SchemaTypes::getInstance();
	}

	/**
	 * Static Constructor creates an instance from an iterator created by the caller
	 * @param XPath2Context $context
	 * @param XPath2NodeIterator $sourceIterator
	 * @param int One of XFI_INSTANCE_ALL_FACTS, XFI_INSTANCE_ITEMS, XFI_INSTANCE_TUPLES, XFI_TUPLE_ITEMS
	 * @param bool $nonNilOnly (optional) Default is false
	 * @return InstanceFactsIterator
	 */
	public static function fromIterator( $context, $sourceIterator, $mode, $nonNilOnly = false )
	{
		if ( ! $sourceIterator instanceof XPath2NodeIterator )
		{
			return EmptyIterator::$Shared;
		}

		$test = new NodeTest( SequenceTypes::$Element );
		$iterator = $mode == XFI_TUPLE_ITEMS || $mode == XFI_TUPLE_TUPLES
			? ChildNodeIterator::fromNodeTest($context, $test, $sourceIterator )
			: ChildOverDescendantsNodeIterator::fromParts( $context, array( $test ), $sourceIterator );

		$result = new InstanceFactsIterator( $context );
		$result->nonNilOnly = $nonNilOnly;
		$result->iterator = $iterator;

		if ( $mode == XFI_INSTANCE_ALL_FACTS )
		{
			$result->substitutionGroups = array_merge( InstanceFactsIterator::$itemSubstitutionGroup, InstanceFactsIterator::$tupleSubstitutionGroup );
		}
		else if ( $mode == XFI_INSTANCE_ITEMS || $mode == XFI_TUPLE_ITEMS )
		{
			$result->substitutionGroups = InstanceFactsIterator::$itemSubstitutionGroup;
		}
		else if ( $mode == XFI_INSTANCE_TUPLES || $mode == XFI_TUPLE_TUPLES )
		{
			$result->substitutionGroups = InstanceFactsIterator::$tupleSubstitutionGroup;
		}

		return $result;
	}

	/**
	 * CloneInstance
	 * @return XPath2NodeIterator
	 */
	public function CloneInstance()
	{
		$result = new InstanceFactsIterator( $this->context );
		$result->nonNilOnly = $this->nonNilOnly;
		$result->iterator = $this->iterator->CloneInstance();
		$result->substitutionGroups = $this->substitutionGroups;
		$result->Reset();
		return $result;
	}

	/**
	 * NextItem
	 * @return XPathItem
	 */
	protected function NextItem()
	{
		/**
		 * @var \XBRL_Instance $instance
		 */
		$instance = $this->context->xbrlInstance;

		while ( true )
		{
			if ( ! $this->iterator->MoveNext() )
			{
				return null;
			}

			$node = $this->iterator->getCurrent();

			if ( $this->nonNilOnly )
			{
				// Look for an xsi:nil and ignore the element if there is one
				$nodeTest = XmlQualifiedNameTest::create( "nil", SCHEMA_INSTANCE_NAMESPACE );
				$attributes = AttributeNodeIterator::fromNodeTest( $this->context, $nodeTest, XPath2NodeIterator::Create( $node ) );
				if ( $attributes->MoveNext() )
				{
					// There is an attrubute.  It is true?
					if ( filter_var( $attributes->getCurrent()->getValue(), FILTER_VALIDATE_BOOLEAN ) )
					{
						continue;
					}
				}
			}

			// Look up the node namespace in the instance taxonomy
			$taxonomy = $instance->getTaxonomyForNamespace( $node->getNamespaceURI() );
			if ( ! $taxonomy )
			{
				continue;
			}

			$prefix = $taxonomy->getPrefix();

			$taxonomyElement = $this->types->getElement( $node->getLocalName(), $prefix  );
			if ( ! $taxonomyElement )
			{
				continue;
			}

			// This node MUST have a substitution group attribute and the attribute MUST resolve to xbrli:tuple or xbrli:item
			if ( ! isset( $taxonomyElement['substitutionGroup'] ) )
			{
				continue;
			}

			if ( ! $this->types->resolveToSubstitutionGroup( $taxonomyElement['substitutionGroup'], $this->substitutionGroups ) )
			{
				continue;
			}

			// If the node value is empty themn apply the schema default (if there is one)
			if ( isset( $taxonomyElement['default'] ) && empty( $node->getUnderlyingObject()->nodeValue ) )
			{
				$node->getUnderlyingObject()->nodeValue = $taxonomyElement['default'];
			}

			return $node->CloneInstance();
		}

	}

	/**
	 * Allow the iterators to be reset
	 */
	public function Reset()
	{
		parent::Reset();
		$this->iterator->Reset();
	}

	/**
	 * Return this iterator
	 * @return InstanceFactsIterator
	 */
	public function getIterator()
	{
		return $this;
	}
}

InstanceFactsIterator::__static();

?>
