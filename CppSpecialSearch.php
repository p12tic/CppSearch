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

    /// Search engine
    protected $searchEngine;

    public function __construct()
    {
        parent::__construct( 'Search' );
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
        $term = str_replace("\n", " ", $wgRequest->getText('search', $titleParam));

        $this->sk = $this->getSkin();

        $this->show_results($term);
        /*
        } else {
            $this->goResult( $search );
        }*/
    }

    /**
     * If an exact title match can be found, jump straight ahead to it.
     *
     * @param $term String
     */
    public function goResult( $term ) {
        global $wgOut;
        $this->setup_page( $term );
        # Try to go to page as entered.
        $t = Title::newFromText( $term );
        # If the string cannot be used to create a title
        if( is_null( $t ) ) {
            return $this->show_results( $term );
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
        return $this->show_results( $term );
    }

    /**
     * @param $term String
     */
    public function show_results($term) {
        global $wgOut, $wgDisableTextSearch, $wgContLang, $wgScript;
        global $wgCppSearchExternalEngines, $wgCppSearchGroups;
        wfProfileIn( __METHOD__ );

        $search = $this->get_search_engine();

        $this->setup_page($term);

        // start rendering the page
        $wgOut->addHtml(
            Xml::openElement(
                'form',
                array(
                    'id' => 'search',
                    'method' => 'get',
                    'action' => $wgScript
                )
            )
        );
        $wgOut->addHtml(
            Xml::openElement( 'table', array( 'id'=>'mw-search-top-table', 'border'=>0, 'cellpadding'=>0, 'cellspacing'=>0 ) ) .
            Xml::openElement( 'tr' ) .
            Xml::openElement( 'td' ) . "\n" .
            $this->show_dialog( $term ) .
            Xml::closeElement('td') .
            Xml::closeElement('tr') .
            Xml::closeElement('table')
        );

        // Get number of results

        // fetch search results

        $wgOut->addHtml( "<div class='searchresults'>" );
        $wgOut->parserOptions()->setEditSection( false );

        $matches_html = array();
        
        foreach($wgCppSearchGroups as $group) {
            //run a separate search for each group
            $matches = $search->search_text_group($term, $group);
            
            if (!$matches) {
                continue;
            }

            // store results, if any
            if ($matches->numRows() > 0) {
                $matches_html[$group] = $this->show_matches($matches);
            }
            $matches->free();
        }

        if (empty($matches_html)) {
            // nothing found
            $wgOut->wrapWikiMsg( "<p class=\"mw-search-nonefound\">\n$1</p>", array( 'search-nonefound', wfEscapeWikiText( $term ) ) );
        } else {
            //show results from different groups
            $wgOut->addHtml("<table class='mw-cppsearch-groups'><tr>");
            foreach ($matches_html as $group => $html) {
                $wgOut->addHtml("<th>" . $group . "</th>");
            }
            $wgOut->addHtml("</tr><tr>");
            foreach ($matches_html as $group => $html) {
                $wgOut->addHtml("<td>" . $html . "</td>");
            }
            $wgOut->addHtml("</tr></table>");
        }
        
        $wgOut->addHtml( "</div>" );

        if ($wgCppSearchExternalEngines) {
            $wgOut->addHtml("<div class='mw-cppsearch-external'>");

            $engines = '';
            foreach ($wgCppSearchExternalEngines as $name => $url) {
                $url = str_replace( '$1', urlencode( $term ), $url );
                $engines = $engines . '<a href="' . $url . '">' . $name . '</a>, ';
            }
            $engines = substr($engines, 0, -2);
            
            $wgOut->addHtml(wfMsg('cppsearch_externalengines', $engines));
            $wgOut->addHtml("</div>");
        }

        wfProfileOut( __METHOD__ );
    }

    /**
     *
     */
    protected function setup_page($term)
    {
        global $wgOut, $wgExtensionAssetsPath;

        if( strval( $term ) !== ''  ) {
            $wgOut->setPageTitle( wfMsg( 'searchresults') );
            $wgOut->setHTMLTitle( wfMsg( 'pagetitle', wfMsg( 'searchresults-title', $term ) ) );
        }
        $wgOut->addExtensionStyle("{$wgExtensionAssetsPath}/CppSearch/CppSearch.css");
    }

    protected function show_dialog($term)
    {
        $out = Html::hidden( 'title', $this->getTitle()->getPrefixedText() );
        // Term box
        $out .= Html::input( 'search', $term, 'search', array(
            'id' => 'searchText',
            'size' => '50',
            'autofocus'
        ) ) . "\n";
        $out .= Xml::submitButton( wfMsg( 'searchbutton' ) ) . "\n";
        return $out;
    }

    /**
     * Show whole set of results
     *
     * @param $matches SearchResultSet
     */
    protected function show_matches( &$matches ) {
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
            $out .= $this->show_hit( $result, $terms );
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
    protected function show_hit($result, $terms) {
        global $wgLang;
        wfProfileIn( __METHOD__ );

        if ($result->isBrokenTitle()) {
            wfProfileOut( __METHOD__ );
            return "<!-- Broken link in search result -->\n";
        }

        $t = $result->getTitle();

        $title_snippet = $result->getTitleSnippet($terms);

        if ($title_snippet == '') {
            $title_snippet = null;
        }
        
        $link_t = clone $t;
        $link = $this->sk->linkKnown($link_t, $title_snippet);

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

    public function get_search_engine() {
        if ( $this->searchEngine === null ) {
            $this->searchEngine = CppSearchEngine::create();
        }
        return $this->searchEngine;
    }

}
