<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'jeecloud')) {
    echo __('API key not valid, you are not authorized to do this action (jeecloud)', __FILE__);
    die();
}

log::add('jeecloud','debug','API Call: ' . print_r($_POST, true));

$type = init('type');
$cts = init('cts');
$ts = init('ts');
$mid = init('mid');
$ddid = init('ddid');
$data = init('data');
$sdid = init('sdid');
$pmid = init('pmid');
// Example of action : relay setOn
// [type]  => action                                   -> type of message
// [cts]   => 1496056263991
// [ts]    => 1496056263991                            -> message timestamp
// [mid]   => bd7b0ad0dce749a58713f46bddc1f91a         -> message id
// [ddid]  => 5b96a8cbdf7749bba2a7d3736abf529b         -> device destination id
// [data]  => Array(                                   -> list of actions
//             [actions] => Array(
//                           [0] => Array(
//                                    [name] => setOn
//                                  )
//                          )
//           )
// [sdid]  => 5348a3a0ebe546779c8ca8027d762f83         -> Source device id (origin of action)
// [pmid]  => 3a9278c41c0f41f99036a4686b1f8638         ->
// [ddtid] => dt1b0e98187192404d84b37f23e3365fe1       ->
// [uid]   => 9d397de2172b4629a68110d44f01d83d         -> owner of the device
// [mv]    => 1

// switch toggle
// [type]  => action
// [cts]   => 1496057449029
// [ts]    => 1496057449029
// [mid]   => c9d59a37c1ac41b78b523f9e249e3f5c
// [sdid]  => 5348a3a0ebe546779c8ca8027d762f83
// [ddid]  => 5348a3a0ebe546779c8ca8027d762f83
// [data]  => Array(
//            [actions] => Array(
//                          [0] => Array(
//                                  [name] => toggle
//                                 )
//                         )
//           )
// [ddtid] => dta10097342d054bd5817361729ce8bf90
// [uid] => 9d397de2172b4629a68110d44f01d83d
// [boid] => 0
// [mv] => 1
switch ($type) {
    case 'ping':
        jeecloud::gotPing($ts);
        break;
    case 'action':
        jeecloud::gotAction($ts, $sdid, $ddid, $data);
        break;
}

return true;
