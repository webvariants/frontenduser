<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

sly_Loader::addLoadPath(_WV16_PATH.'plugins/google_friendconnect/lib');
sly_Core::dispatcher()->register('ALL_GENERATED', array('WV16_FriendConnect', 'clearCache'));
