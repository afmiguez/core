<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include '../../gibbon.php';

$orphaned = '';
if (isset($_GET['orphaned'])) {
    if ($_GET['orphaned'] == 'true') {
        $orphaned = 'true';
    }
}

$gibbonModuleID = $_GET['gibbonModuleID'];
$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_POST['address']).'/module_manage_uninstall.php&gibbonModuleID='.$gibbonModuleID;
$URLDelete = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_POST['address']).'/module_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/System Admin/module_manage_uninstall.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    //Proceed!
    //Check if role specified
    if ($gibbonModuleID == '') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
    } else {
        $data = array('gibbonModuleID' => $gibbonModuleID);
        $sql = 'SELECT * FROM gibbonModule WHERE gibbonModuleID=:gibbonModuleID';
        $result = $pdo->select($sql, $data);

        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
        } else {
            $row = $result->fetch();
            $module = $row['name'];
            $partialFail = false;

            //Check for tables and views to remove, and remove them
            $tables = null;
            if (isset($_POST['remove'])) {
                $tables = $_POST['remove'];
            }
            if (is_array($tables)) {
                if (count($tables) > 0) {
                    foreach ($tables as $table) {
                        $type = null;
                        $name = null;
                        if (substr($table, 0, 5) == 'Table') {
                            $type = 'TABLE';
                            $name = substr($table, 6);
                        } elseif (substr($table, 0, 4) == 'View') {
                            $type = 'VIEW';
                            $name = substr($table, 5);
                        }
                        if ($type != null and $name != null) {
                            $sqlDelete = "DROP $type $name";
                            $partialFail &= !$pdo->statement($sqlDelete);
                        }
                    }
                }
            }

            //Get actions to remove permissions
            $data = array('gibbonModuleID' => $gibbonModuleID);
            $sql = 'SELECT * FROM gibbonAction WHERE gibbonModuleID=:gibbonModuleID';
            $result = $pdo->select($sql, $data);

            while ($row = $result->fetch()) {
                //Remove permissions
                $dataDelete = array('gibbonActionID' => $row['gibbonActionID']);
                $sqlDelete = 'DELETE FROM gibbonPermission WHERE gibbonActionID=:gibbonActionID';
                $partialFail &= !$pdo->delete($sqlDelete, $dataDelete);
            }

            //Remove actions
            $dataDelete = array('gibbonModuleID' => $gibbonModuleID);
            $sqlDelete = 'DELETE FROM gibbonAction WHERE gibbonModuleID=:gibbonModuleID';
            $partialFail &= !$pdo->delete($sqlDelete, $dataDelete);

            //Remove module
            $dataDelete = array('gibbonModuleID' => $gibbonModuleID);
            $sqlDelete = 'DELETE FROM gibbonModule WHERE gibbonModuleID=:gibbonModuleID';
            $partialFail &= !$pdo->delete($sqlDelete, $dataDelete);

            //Remove hooks
            $dataDelete = array('gibbonModuleID' => $gibbonModuleID);
            $sqlDelete = 'DELETE FROM gibbonHook WHERE gibbonModuleID=:gibbonModuleID';
            $partialFail &= !$pdo->delete($sqlDelete, $dataDelete);

            //Remove settings
            $dataDelete = array('scope' => $module);
            $sqlDelete = 'DELETE FROM gibbonSetting WHERE scope=:scope';
            $partialFail &= !$pdo->delete($sqlDelete, $dataDelete);

            //Remove notification events
            $dataDelete = array('module' => $module);
            $sqlDelete = 'DELETE gibbonNotificationEvent, gibbonNotificationListener FROM gibbonNotificationEvent LEFT JOIN gibbonNotificationListener ON (gibbonNotificationEvent.gibbonNotificationEventID=gibbonNotificationListener.gibbonNotificationEventID) WHERE gibbonNotificationEvent.moduleName=:module';
            $partialFail &= !$pdo->delete($sqlDelete, $dataDelete);

            if ($partialFail == true) {
                $URL .= '&return=warning2';
                header("Location: {$URL}");
            } else {
                // Clear the main menu from session cache
                $gibbon->session->forget('menuMainItems');

                $URLDelete .= $orphaned != 'true'
                    ? '&return=warning0'
                    : '&return=success0';
                header("Location: {$URLDelete}");
            }
        }
    }
}
