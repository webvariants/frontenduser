<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

$dir = dirname(__FILE__);
sly_Loader::addLoadPath($dir.'/lib');

// since the FB API sucks we have to load it manually
require_once $dir.'/lib/Facebook/facebook.php';

$listener = array('WV16_FacebookConnect', 'clearCache');
sly_Core::dispatcher()->register('ALL_GENERATED',     $listener);
sly_Core::dispatcher()->register('SLY_CACHE_CLEARED', $listener);
