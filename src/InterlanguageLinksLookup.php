<?php

namespace SIL;

use SMW\Query\Language\Conjunction;
use SMW\Query\Language\SomeProperty;
use SMW\Query\Language\ValueDescription;

use SMW\Store;
use SMW\DIWikiPage;
use SMW\DIProperty;

use SMWPrintRequest as PrintRequest;
use SMWPropertyValue as PropertyValue;
use SMWQuery as Query;
use SMWDIBlob as DIBlob;

use Title;
use Language;

/**
 * @license GNU GPL v2+
 * @since 1.0
 *
 * @author mwjames
 */
class InterlanguageLinksLookup {

	/**
	 * @var CachedLanguageTargetLinks
	 */
	private $cachedLanguageTargetLinks;

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @since 1.0
	 *
	 * @param CachedLanguageTargetLinks $cachedLanguageTargetLinks
	 */
	public function __construct( CachedLanguageTargetLinks $cachedLanguageTargetLinks ) {
		$this->cachedLanguageTargetLinks = $cachedLanguageTargetLinks;
	}

	/**
	 * @since 1.0
	 *
	 * @param Store $store
	 */
	public function setStore( Store $store ) {
		$this->store = $store;
	}

	/**
	 * @since 1.0
	 *
	 * @param InterlanguageLink $interlanguageLink
	 *
	 * @return boolean|array
	 */
	public function tryCachedLanguageTargetLinks( InterlanguageLink $interlanguageLink ) {
		return $this->cachedLanguageTargetLinks->getLanguageTargetLinksFromCache( $interlanguageLink );
	}

	/**
	 * @since 1.0
	 *
	 * @param Title $title
	 *
	 * @return boolean|string
	 */
	public function tryCachedPageLanguageForTarget( Title $title ) {
		return $this->cachedLanguageTargetLinks->getPageLanguageFromCache( $title );
	}

	/**
	 * @since 1.0
	 *
	 * @param Title $title
	 */
	public function doInvalidateCachedLanguageTargetLinks( Title $title ) {

		$this->cachedLanguageTargetLinks
			->deleteLanguageTargetLinksFromCache( $this->findLinkReferencesForTarget( $title ) );

		$this->cachedLanguageTargetLinks
			->deletePageLanguageForTargetFromCache( $title );
	}

	/**
	 * @since 1.0
	 *
	 * @param InterlanguageLink $interlanguageLink
	 *
	 * @return array
	 */
	public function queryLanguageTargetLinks( InterlanguageLink $interlanguageLink ) {

		$languageTargetLinks = array();

		$queryResult = $this->queryOtherTargetLinksForInterlanguageLink( $interlanguageLink );

		while ( $resultArray = $queryResult->getNext() ) {
			foreach ( $resultArray as $row ) {

				$dataValue = $row->getNextDataValue();

				if ( $dataValue === false ) {
					continue;
				}

				$languageTargetLinks[ $dataValue->getWikiValue() ] = $row->getResultSubject()->getTitle();
			}
		}

		$this->cachedLanguageTargetLinks->saveLanguageTargetLinksToCache(
			$interlanguageLink,
			$languageTargetLinks
		);

		return $languageTargetLinks;
	}

	/**
	 * @since 1.0
	 *
	 * @param Title $title
	 *
	 * @return string
	 */
	public function findLastPageLanguageForTarget( Title $title ) {

		$propertyValues = $this->store->getPropertyValues(
			DIWikiPage::newFromTitle( $title ),
			new DIProperty( PropertyRegistry::SIL_CONTAINER )
		);

		if ( !is_array( $propertyValues ) || $propertyValues === array() ) {
			return '';
		}

		$containerSubject = end( $propertyValues );

		$propertyValues = $this->store->getPropertyValues(
			$containerSubject,
			new DIProperty( PropertyRegistry::SIL_LANG )
		);

		$languageCodeValue = end( $propertyValues );

		if ( $languageCodeValue instanceOf DIBlob ) {
			return $languageCodeValue->getString();
		}

		return '';
	}

	/**
	 * @since 1.0
	 *
	 * @param Title $title
	 *
	 * @return DIWikiPage[]|[]
	 */
	public function findLinkReferencesForTarget( Title $title ) {

		$linkReferences = array();

		$propertyValues = $this->store->getPropertyValues(
			DIWikiPage::newFromTitle( $title ),
			new DIProperty( PropertyRegistry::SIL_CONTAINER )
		);

		if ( !is_array( $propertyValues ) || $propertyValues === array() ) {
			return $linkReferences;
		}

		foreach ( $propertyValues as $containerSubject ) {

			$values = $this->store->getPropertyValues(
				$containerSubject,
				new DIProperty( PropertyRegistry::SIL_REF )
			);

			$linkReferences = array_merge( $linkReferences, $values );
		}

		return $linkReferences;
	}

	/**
	 * @return QueryResult
	 */
	private function queryOtherTargetLinksForInterlanguageLink( InterlanguageLink $interlanguageLink ) {

		$description = new Conjunction();

		$languageDataValue = $interlanguageLink->newLanguageDataValue();

		$linkReferenceDataValue = $interlanguageLink->newLinkReferenceDataValue();

		$description->addDescription(
			new SomeProperty(
				$linkReferenceDataValue->getProperty(),
				new ValueDescription( $linkReferenceDataValue->getDataItem(), null, SMW_CMP_EQ )
			)
		);

		$propertyValue = new PropertyValue( '__pro' );
		$propertyValue->setDataItem( $languageDataValue->getProperty() );

		$description->addPrintRequest(
			new PrintRequest( PrintRequest::PRINT_PROP, null, $propertyValue )
		);

		$query = new Query(
			$description,
			false,
			false
		);

		//	$query->sort = true;
		//	$query->sortkey = array( $languageDataValue->getProperty()->getLabel() => 'asc' );

		// set query limit to certain threshold

		return $this->store->getQueryResult( $query );
	}

}
