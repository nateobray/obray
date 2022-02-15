<?php

/*****************************************************************************
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Nathan A Obray
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the 'Software'), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *****************************************************************************/

if (!class_exists('OObject')) {
    die();
}

/********************************************************************************************************************
 *
 * OUsers:    User/Permission Manager
 ********************************************************************************************************************/
Class oUsers extends ODBO
{
    
    protected $table_definition = [
        'ouser_id' =>               array('primary_key' => TRUE),
        'ouser_first_name' =>       array('data_type' => 'varchar(128)', 'required' => FALSE, 'label' => 'First Name', 'error_message' => 'Please enter the user\'s first name'),
        'ouser_last_name' =>        array('data_type' => 'varchar(128', 'required' => FALSE, 'label' => 'Last Name', 'error_message' => 'Please enter the user\'s last name'),
        'ouser_email' =>            array('data_type' => 'varchar(128)', 'required' => TRUE, 'label' => 'Email Address', 'error_message' => 'Please enter the user\'s email address'),
        'ouser_permission_level' => array('data_type' => 'integer', 'required' => FALSE, 'label' => 'Permission Level', 'error_message' => 'Please specify the user\'s permission level'),
        'ouser_group' =>            array('data_type' => 'integer'),
        'ouser_status' =>           array('data_type' => 'varchar(20)', 'required' => FALSE, 'label' => 'Status', 'error_message' => 'Please specify the user\'s status'),
        'ouser_password' =>         array('data_type' => 'password', 'required' => TRUE, 'label' => 'Password', 'error_message' => 'Please specify the user\'s password'),
        'ouser_failed_attempts' =>  array('data_type' => 'integer', 'required' => FALSE, 'label' => 'Failed Logins'),
        'ouser_last_login' =>       array('data_type' => 'datetime', 'required' => FALSE, 'label' => 'Last Login'),
        'ouser_settings' =>         array('data_type' => 'text', 'required' => FALSE, 'label' => 'Settings')
    ];
    protected $table = 'ousers';

    
    protected $permissions = array(
        'object' => 'any',
        'add' => 1,
        'get' => 1,
        'update' => 1,
        'login' => 'any',
        'logout' => 'any',
        'count' => 1
    );

    /********************************************************************************************************************
     *
     * Login - creates the ouser session variable
     ********************************************************************************************************************/

    public function login($params)
    {

        // Validate the required parameters
        if (!isSet($params['ouser_email'])) {
            $this->throwError('Email is required', 500, 'ouser_email');
        }
        if (!isSet($params['ouser_password'])) {
            $this->throwError('Password is required', 500, 'ouser_password');
        }

        // Exit early if error;
        if ($this->isError()) {
            return;
        }

        // get user based on credentials
        $this->get(array(
                'ouser_email' => $params['ouser_email'],
                'ouser_password' => $params['ouser_password'])
        );

        // if the data is empty or more than one user is found
        if (empty($this->data) || count($this->data) > 1) {
            $this->get(array('ouser_email' => $params['ouser_email']));

            // If more than one user was found for the email & password
            if (count($this->data) > 1) {
                $this->throwError('Login Failed. Duplicate users found. Please contact support.');
                $this->logFailedLogin($params);
                return;
            }

            if (count($this->data) === 1) {
                $user = $this->data[0];
                $this->update(array(
                    'ouser_id' => $user->ouser_id,
                    'ouser_failed_attempts' => ($user->ouser_failed_attempts + 1)
                ));
                $this->data = array();
            }
            $this->throwError('Invalid login, make sure you have entered a valid email and password.');
            $this->logFailedLogin($params);
            return;
        }

        $user = $this->data[0];

        // if the user has exceeded the allowable login attempts
        if ($user->ouser_failed_attempts > __OBRAY_MAX_FAILED_LOGIN_ATTEMPTS__) {
            $this->throwError('This account has been locked due to multiple failed login attempts.');
            $this->logFailedLogin($params);
            return;
        }

        // if the user is not active
        if ($user->ouser_status !== 'active') {
            $this->throwError('This account is not active.');
            $this->logFailedLogin($params);
            return;
        }

        // Successful login
        $_SESSION['ouser'] = $user;
        $this->getRolesAndPermissions();
        $_SESSION['ouser']->ouser_settings = unserialize(base64_decode($_SESSION['ouser']->ouser_settings));
        $this->update(array(
            'ouser_id' => $_SESSION['ouser']->ouser_id,
            'ouser_failed_attempts' => 0,
            'ouser_last_login' => date('Y-m-d H:i:s')
        ));

    }

    /********************************************************************************************************************
     *
     * Logout - destroys the ouser session variable
     ********************************************************************************************************************/

    public function logout($params)
    {
        unset($_SESSION['ouser']);
        $this->data['logout'] = TRUE;
    }

    public function authorize($params = array())
    {

        if (!isSet($_SESSION['ouser'])) {
            $this->throwError('Forbidden', 403);
        } else if (isSet($params['level']) && $params['level'] < $_SESSION['ouser']->ouser_permission_level) {
            $this->throwError('Forbidden', 403);
        }

    }

    public function hasPermission($object)
    {
        if (isSet($this->permissions[$object]) && $this->permissions[$object] === 'any') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function setting($params = array())
    {

        if (!empty($params) && !empty($_SESSION['ouser']->ouser_id)) {

            if (!empty($params['key']) && isSet($params['value'])) {

                $_SESSION['ouser']->ouser_settings[$params['key']] = $params['value'];

                $this->route('/obray/OUsers/update/?ouser_id=' . $_SESSION['ouser']->ouser_id . '&ouser_settings=' . base64_encode(serialize($_SESSION['ouser']->ouser_settings)));

            } else if (!empty($params['key'])) {

                $this->data[$params['key']] = $_SESSION['ouser']->ouser_settings[$params['key']];

            }

        }

    }

    /************************************************************
     *
     * Get Roles & Permission
     ************************************************************/

    public function getRolesAndPermissions()
    {

        $sql = "SELECT oPermissions.opermission_code, oRoles.orole_code
						FROM oUserRoles
						JOIN oRoles ON oRoles.orole_id = oUserRoles.orole_id
					LEFT JOIN oRolePermissions ON oRolePermissions.orole_id = oUserRoles.orole_id
						JOIN oPermissions ON oPermissions.opermission_id = oRolePermissions.opermission_id
						WHERE oUserRoles.ouser_id = :ouser_id
				
				UNION 
				
					SELECT oPermissions.opermission_code, NULL AS orole_code
						FROM oUserPermissions
						JOIN oPermissions ON oPermissions.opermission_id = oUserPermissions.opermission_id
						WHERE oUserPermissions.ouser_id = :ouser_id";

        try {
            $statement = $this->dbh->prepare($sql);
            $statement->bindValue(':ouser_id', $_SESSION["ouser"]->ouser_id);
            $result = $statement->execute();
            $this->data = [];
            $statement->setFetchMode(PDO::FETCH_OBJ);
            while ($row = $statement->fetch()) {
                $this->data[] = $row;
            }

            $roles = array();
            $permissions = array();
            forEach ($this->data as $codes) {
                if (!empty($codes->orole_code) && !in_array($codes->orole_code, $roles)) {
                    $roles[] = $codes->orole_code;
                }
                if (!empty($codes->opermission_code) && !in_array($codes->opermission_code, $permissions)) {
                    $permissions[] = $codes->opermission_code;
                }
            }

            if (!empty($_SESSION["ouser"])) {
                $_SESSION["ouser"]->permissions = $permissions;
                $_SESSION["ouser"]->roles = $roles;
            }

            $this->data = array(
                "permissions" => $permissions,
                "roles" => $roles
            );

        } catch (Exception $e) {
            //$this->throwError($e);
            //$this->logError(oCoreProjectEnum::ODBO, $e);
        }

    }

    /**
     * @param $params
     */
    private function logFailedLogin($params)
    {
        if (defined('__OBRAY_ENABLED_FAILED_ATTEMPT_LOGGING__')) {
            $login = $this->route('/obray/OUserFailedAttempts/add/', array(
                'ouser_email' => $params["ouser_email"],
                'ouser_password' => $params["ouser_password"],
                'ouser_attempt_ip' => !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "Unknown",
                'ouser_attempt_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Unknown"), TRUE
            );
        }
    }

}
