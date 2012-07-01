<?php
/*
    Copyright © 2004 Brion Vibber <brion@pobox.com>
    Copyright 2012 p12 <tir5c3@yahoo.co.uk>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * implements Special:Search - Run text & title search and display the output
 * @ingroup SpecialPage
 */
class CppSpecialSearch extends SpecialPage {

    public function __construct()
    {
        parent::__construct( 'CppSearch' );
    }

    /**
     * Entry point
     *
     * @param $par String or null
     */
    public function execute( $par ) {
        global $wgRequest, $wgUser, $wgOut;

        $this->setHeaders();
        $this->outputHeader();
        $wgOut->allowClickjacking();
        $wgOut->addModuleStyles( 'mediawiki.special' );

        // Strip underscores from title parameter; most of the time we'll want
        // text form here. But don't strip underscores from actual text params!
        $titleParam = str_replace( '_', ' ', $par );

        // Fetch the search term
        $search = str_replace( "\n", " ", $wgRequest->getText( 'search', $titleParam ) );

        $this->load( $wgRequest, $wgUser );

        if ( $wgRequest->getVal( 'fulltext' )
            || !is_null( $wgRequest->getVal( 'offset' ) )
            || !is_null( $wgRequest->getVal( 'searchx' ) ) )
        {
            $this->showResults( $search );
        } else {
            $this->goResult( $search );
        }
    }

    /**
     * Set up basic search parameters from the request and user settings.
     * Typically you'll pass $wgRequest and $wgUser.
     *
     * @param $request WebRequest
     * @param $user User
     */
    public function load( &$request, &$user ) {
        list( $this->limit, $this->offset ) = $request->getLimitOffset( 20, 'searchlimit' );
        $this->mPrefix = $request->getVal( 'prefix', '' );

        $this->sk = $this->getSkin();
        $this->fulltext = $request->getVal('fulltext');
    }

    /**
     * If an exact title match can be found, jump straight ahead to it.
     *
     * @param $term String
     */
    public function goResult( $term ) {
        global $wgOut;
        $this->setupPage( $term );
        # Try to go to page as entered.
        $t = Title::newFromText( $term );
        # If the string cannot be used to create a title
        if( is_null( $t ) ) {
            return $this->showResults( $term );
        }
        # If there's an exact or very near match, jump right there.
        $t = SearchEngine::getNearMatch( $term );

        if ( !wfRunHooks( 'SpecialSearchGo', array( &$t, &$term ) ) ) {
            # Hook requested termination
            return;
        }

        if( !is_null( $t ) ) {
            $wgOut->redirect( $t->getFullURL() );
            return;
        }
        # No match, generate an edit URL
        $t = Title::newFromText( $term );
        if( !is_null( $t ) ) {
            global $wgGoToEdit;
            wfRunHooks( 'SpecialSearchNogomatch', array( &$t ) );
            wfDebugLog( 'nogomatch', $t->getText(), false );

            # If the feature is enabled, go straight to the edit page
            if( $wgGoToEdit ) {
                $wgOut->redirect( $t->getFullURL( array( 'action' => 'edit' ) ) );
                return;
            }
        }
        return $this->showResults( $term );
    }

    /**
     * @param $term String
     */
    public function showResults( $term ) {
        global $wgOut, $wgDisableTextSearch, $wgContLang, $wgScript;
        wfProfileIn( __METHOD__ );

        $sk = $this->getSkin();

        $search = $this->getSearchEngine();
        $search->prefix = $this->mPrefix;
        $term = $search->transformSearchTerm($term);

        $this->setupPage( $term );

        /*if( $wgDisableTextSearch ) {
            global $wgSearchForwardUrl;
            if( $wgSearchForwardUrl ) {
                $url = str_replace( '$1', urlencode( $term ), $wgSearchForwardUrl );
                $wgOut->redirect( $url );
                wfProfileOut( __METHOD__ );
                return;
            }
            $wgOut->addHTML(
                Xml::openElement( 'fieldset' ) .
                Xml::element( 'legend', null, wfMsg( 'search-external' ) ) .
                Xml::element( 'p', array( 'class' => 'mw-searchdisabled' ), wfMsg( 'searchdisabled' ) ) .
                wfMsg( 'googlesearch',
                    htmlspecialchars( $term ),
                    htmlspecialchars( 'UTF-8' ),
                    htmlspecialchars( wfMsg( 'searchbutton' ) )
                ) .
                Xml::closeElement( 'fieldset' )
            );
            wfProfileOut( __METHOD__ );
            return;
        }*/

        $t = Title::newFromText( $term );

        // fetch search results
        $rewritten = $search->replacePrefixes($term);

        $textMatches = $search->searchText( $rewritten );


        // start rendering the page
        $wgOut->addHtml(
            Xml::openElement(
                'form',
                array(
                    'id' => ( $this->profile === 'advanced' ? 'powersearch' : 'search' ),
                    'method' => 'get',
                    'action' => $wgScript
                )
            )
        );
        $wgOut->addHtml(
            Xml::openElement( 'table', array( 'id'=>'mw-search-top-table', 'border'=>0, 'cellpadding'=>0, 'cellspacing'=>0 ) ) .
            Xml::openElement( 'tr' ) .
            Xml::openElement( 'td' ) . "\n" .
            $this->shortDialog( $term ) .
            Xml::closeElement('td') .
            Xml::closeElement('tr') .
            Xml::closeElement('table')
        );

        $filePrefix = $wgContLang->getFormattedNsText(NS_FILE).':';
        if( trim( $term ) === '' || $filePrefix === trim( $term ) ) {
            $wgOut->addHTML( $this->formHeader( $term, 0, 0 ) );
            $wgOut->addHTML( '</form>' );
            // Empty query -- straight view of search form
            wfProfileOut( __METHOD__ );
            return;
        }

        // Get number of results
        $textMatchesNum = $textMatches ? $textMatches->numRows() : 0;

        // show number of results and current offset
        $wgOut->addHTML( $this->formHeader( $term, $textMatchesNum, $textMatchesNum ) );

        $wgOut->addHtml( Xml::closeElement( 'form' ) );
        $wgOut->addHtml( "<div class='searchresults'>" );

        $wgOut->parserOptions()->setEditSection( false );

        if( $textMatches ) {

            // show results
            if( $numTextMatches > 0 ) {
                $wgOut->addHTML( $this->showMatches( $textMatches ) );
            }

            $textMatches->free();
        }
        if( $numTextMatches === 0 ) {
            $wgOut->wrapWikiMsg( "<p class=\"mw-search-nonefound\">\n$1</p>", array( 'search-nonefound', wfEscapeWikiText( $term ) ) );
        }
        $wgOut->addHtml( "</div>" );

        wfProfileOut( __METHOD__ );
    }

    /**
     *
     */
    protected function setupPage( $term ) {
        global $wgOut;

        if( strval( $term ) !== ''  ) {
            $wgOut->setPageTitle( wfMsg( 'searchresults') );
            $wgOut->setHTMLTitle( wfMsg( 'pagetitle', wfMsg( 'searchresults-title', $term ) ) );
        }
        // add javascript specific to special:search
        $wgOut->addModules( 'mediawiki.special.search' );
    }

    /**
     * Show whole set of results
     *
     * @param $matches SearchResultSet
     */
    protected function showMatches( &$matches ) {
        global $wgContLang;
        wfProfileIn( __METHOD__ );

        $terms = $wgContLang->convertForSearchResult( $matches->termMatches() );

        $out = "";
        $infoLine = $matches->getInfo();
        if( !is_null($infoLine) ) {
            $out .= "\n<!-- {$infoLine} -->\n";
        }
        $out .= "<ul class='mw-search-results'>\n";
        while( $result = $matches->next() ) {
            $out .= $this->showHit( $result, $terms );
        }
        $out .= "</ul>\n";

        // convert the whole thing to desired language variant
        $out = $wgContLang->convert( $out );
        wfProfileOut( __METHOD__ );
        return $out;
    }

    /**
     * Format a single hit result
     *
     * @param $result SearchResult
     * @param $terms Array: terms to highlight
     */
    protected function showHit( $result, $terms ) {
        global $wgLang;
        wfProfileIn( __METHOD__ );

        if( $result->isBrokenTitle() ) {
            wfProfileOut( __METHOD__ );
            return "<!-- Broken link in search result -->\n";
        }

        $sk = $this->getSkin();
        $t = $result->getTitle();

        $titleSnippet = $result->getTitleSnippet($terms);

        if( $titleSnippet == '' )
            $titleSnippet = null;

        $link_t = clone $t;

        wfRunHooks( 'ShowSearchHitTitle',
                    array( &$link_t, &$titleSnippet, $result, $terms, $this ) );

        $link = $this->sk->linkKnown($link_t, $titleSnippet);

        //If page content is not readable, just return the title.
        //This is not quite safe, but better than showing excerpts from non-readable pages
        //Note that hiding the entry entirely would screw up paging.
        if( !$t->userCanRead() ) {
            wfProfileOut( __METHOD__ );
            return "<li>{$link}</li>\n";
        }

        // If the page doesn't *exist*... our search index is out of date.
        // The least confusing at this point is to drop the result.
        // You may get less results, but... oh well. :P
        if( $result->isMissingRevision() ) {
            wfProfileOut( __METHOD__ );
            return "<!-- missing page " . htmlspecialchars( $t->getPrefixedText() ) . "-->\n";
        }

        wfProfileOut( __METHOD__ );
        return "<li><div class='mw-search-result-heading'>{$link}</div>\n</li>\n";

    }

    protected function shortDialog( $term ) {
        $out = Html::hidden( 'title', $this->getTitle()->getPrefixedText() );
        // Term box
        $out .= Html::input( 'search', $term, 'search', array(
            'id' => $this->profile === 'advanced' ? 'powerSearchText' : 'searchText',
            'size' => '50',
            'autofocus'
        ) ) . "\n";
        $out .= Html::hidden( 'fulltext', 'Search' ) . "\n";
        $out .= Xml::submitButton( wfMsg( 'searchbutton' ) ) . "\n";
        return $out;
    }

    /**
     * @since 1.18
     */
    public function getSearchEngine() {
        if ( $this->searchEngine === null ) {
            $this->searchEngine = CppSearch::create();
        }
        return $this->searchEngine;
    }

    /**
     * Users of hook SpecialSearchSetupEngine can use this to
     * add more params to links to not lose selection when
     * user navigates search results.
     * @since 1.18
     */
    public function setExtraParam( $key, $value ) {
        $this->extraParams[$key] = $value;
    }

}
