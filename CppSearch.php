<?php
/*
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

$wgExtensionCredits['other'][] = array(
    'path'           => __FILE__,
    'name'           => 'CppSearch',
    'author'         => 'p12',
    'descriptionmsg' => 'C/C++ keyword search extension',
//  'url'            => '',
);

//Default settings
$wgCppSearchMaxResults = 100;
$wgCppSearchMaxResultCost = 4;
$wgCppSearchSplitWordCost = 2;
$wgCppSearchInsertCost = 3;
$wgCppSearchDeleteCost = 3;
$wgCppSearchReplaceCost = 2;
$wgCppSearchQueryWordLimit = 5;
$wgCppSearchCacheExpiry = 7200;
$wgCppSearchGroups = array ( 'cpp' );

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['CppSearchEngine'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSearchResult'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSearchResultSet'] = $dir . 'CppSearchEngine.php';
$wgAutoloadClasses['CppSpecialSearch'] = $dir . 'CppSpecialSearch.php';

$wgSpecialPages['Search'] = 'CppSpecialSearch';
